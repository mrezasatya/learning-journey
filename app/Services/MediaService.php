<?php

namespace App\Services;

use App\Jobs\ProcessMediaJob;
use App\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Penyimpanan & konversi media (Storage adapter, Brief 9.3):
 * - Video  → 360p/480p/720p MP4 (US-07), durasi via ffprobe
 * - PDF    → gambar per halaman (dibaca di HP tanpa aplikasi tambahan), jumlah halaman
 * - PPT(X) → PDF via LibreOffice (US-08) → gambar per halaman
 * Semua konversi berjalan di antrian latar belakang (worker).
 */
class MediaService
{
    public const VIDEO_MIMES = ['mp4', 'm4v', 'mov', 'webm'];
    public const DOC_MIMES = ['pdf'];
    public const SLIDE_MIMES = ['ppt', 'pptx'];
    public const IMAGE_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('blj.content_disk'));
    }

    /** Simpan unggahan & jadwalkan pemrosesan bila perlu. */
    public function store(UploadedFile $upload, string $folder = 'content', bool $process = true): StoredFile
    {
        $ext = strtolower($upload->getClientOriginalExtension());
        $key = trim($folder, '/').'/'.now()->format('Y/m').'/'.Str::uuid().'.'.$ext;
        $this->disk()->putFileAs(dirname($key), $upload, basename($key));

        return $this->register($key, $upload->getClientOriginalName(), $upload->getMimeType(), $upload->getSize(), $process);
    }

    /** Daftarkan berkas yang sudah ada di disk (dipakai seeder & sertifikat). */
    public function register(string $key, string $originalName, ?string $mime, int $size, bool $process = true): StoredFile
    {
        $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $needsProcessing = $process && in_array($ext, [...self::VIDEO_MIMES, ...self::DOC_MIMES, ...self::SLIDE_MIMES], true);

        $file = StoredFile::create([
            'disk' => config('blj.content_disk'),
            'storage_key' => $key,
            'original_name' => $originalName,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'checksum' => $this->localPath($key) ? hash_file('sha256', $this->localPath($key)) : null,
            'processing_status' => $needsProcessing ? 'PENDING' : 'READY',
            'uploaded_by' => auth()->id(),
        ]);

        if ($needsProcessing) {
            ProcessMediaJob::dispatch($file->id);
        }

        return $file;
    }

    public function process(StoredFile $file): void
    {
        $file->update(['processing_status' => 'PROCESSING', 'processing_error' => null]);
        $ext = strtolower(pathinfo($file->storage_key, PATHINFO_EXTENSION));
        try {
            match (true) {
                in_array($ext, self::VIDEO_MIMES, true) => $this->processVideo($file),
                in_array($ext, self::SLIDE_MIMES, true) => $this->processSlides($file),
                in_array($ext, self::DOC_MIMES, true) => $this->processPdf($file, $file->storage_key),
                default => null,
            };
            $file->update(['processing_status' => 'READY']);
        } catch (\Throwable $e) {
            $file->update(['processing_status' => 'FAILED', 'processing_error' => mb_substr($e->getMessage(), 0, 2000)]);
            report($e);
        }
    }

    private function processVideo(StoredFile $file): void
    {
        $src = $this->requireLocal($file->storage_key);
        $probe = $this->run(['ffprobe', '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=height:format=duration', '-of', 'json', $src]);
        $info = json_decode($probe, true);
        $duration = (int) round((float) ($info['format']['duration'] ?? 0));
        $sourceHeight = (int) ($info['streams'][0]['height'] ?? 720);

        $renditions = [];
        foreach (config('blj.video_renditions') as $name => $height) {
            if ($height > $sourceHeight && $renditions) {
                continue; // jangan upscale; minimal satu rendisi tetap dibuat
            }
            $key = preg_replace('/\.\w+$/', "-$name.mp4", $file->storage_key);
            $out = $this->disk()->path($key);
            $this->run(['ffmpeg', '-y', '-i', $src, '-vf', "scale=-2:$height", '-c:v', 'libx264', '-preset', 'veryfast',
                '-crf', $height <= 360 ? '30' : '27', '-c:a', 'aac', '-b:a', '96k', '-movflags', '+faststart', $out], 3600);
            $renditions[$name] = $key;
        }
        $file->update(['duration_seconds' => max(1, $duration), 'renditions' => $renditions]);
    }

    private function processSlides(StoredFile $file): void
    {
        $src = $this->requireLocal($file->storage_key);
        $outDir = dirname($src);
        $this->run(['soffice', '--headless', '--norestore', '-env:UserInstallation=file:///tmp/blj-lo-'.getmypid(),
            '--convert-to', 'pdf', '--outdir', $outDir, $src], 600);
        $pdfKey = preg_replace('/\.\w+$/', '.pdf', $file->storage_key);
        if (! $this->disk()->exists($pdfKey)) {
            throw new RuntimeException('Konversi PPT ke PDF gagal.');
        }
        $this->processPdf($file, $pdfKey);
        $file->update(['renditions' => array_merge($file->renditions ?? [], ['pdf' => $pdfKey])]);
    }

    private function processPdf(StoredFile $file, string $pdfKey): void
    {
        $src = $this->requireLocal($pdfKey);
        $prefix = preg_replace('/\.\w+$/', '-page', $src);
        $this->run(['pdftoppm', '-jpeg', '-jpegopt', 'quality=80', '-scale-to', '1400', $src, $prefix], 600);
        $dir = dirname($src);
        $base = basename($prefix);
        $pages = collect(scandir($dir))->filter(fn ($f) => str_starts_with($f, $base.'-') && str_ends_with($f, '.jpg'))
            ->sort(SORT_NATURAL)->values()
            ->map(fn ($f) => dirname($pdfKey).'/'.$f)->all();
        if (! $pages) {
            throw new RuntimeException('PDF tidak memiliki halaman yang dapat dibaca.');
        }
        $file->update(['page_count' => count($pages), 'renditions' => array_merge($file->renditions ?? [], ['pages' => $pages])]);
    }

    private function requireLocal(string $key): string
    {
        $path = $this->localPath($key);
        if (! $path) {
            // Disk S3/MinIO: unduh sementara ke lokal untuk diproses (disederhanakan di prototipe)
            throw new RuntimeException('Pemrosesan media memerlukan disk lokal pada prototipe.');
        }

        return $path;
    }

    private function localPath(string $key): ?string
    {
        $disk = $this->disk();
        if (! method_exists($disk, 'path')) {
            return null;
        }
        $path = $disk->path($key);

        return is_file($path) ? $path : null;
    }

    private function run(array $cmd, int $timeout = 120): string
    {
        $p = new Process($cmd);
        $p->setTimeout($timeout);
        $p->run();
        if (! $p->isSuccessful()) {
            throw new RuntimeException($cmd[0].' gagal: '.mb_substr($p->getErrorOutput(), -800));
        }

        return $p->getOutput();
    }

    public static function lessonTypeFor(string $ext): ?string
    {
        $ext = strtolower($ext);

        return match (true) {
            in_array($ext, self::VIDEO_MIMES, true) => 'VIDEO',
            in_array($ext, self::DOC_MIMES, true) => 'PDF',
            in_array($ext, self::SLIDE_MIMES, true) => 'SLIDE',
            in_array($ext, self::IMAGE_MIMES, true) => 'IMAGE',
            default => null,
        };
    }
}
