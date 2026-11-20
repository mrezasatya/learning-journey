<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Program;
use App\Services\MediaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Menambahkan materi VIDEO dan PDF contoh (dibuat otomatis, tanpa konten berhak cipta).
 * Dilewati bila FFmpeg / Poppler tidak terpasang.
 */
class DemoMediaSeeder extends Seeder
{
    public function run(MediaService $media): void
    {
        if (app()->environment('testing')) {
            return; // tes otomatis tidak perlu konversi media (lambat)
        }
        $finder = new ExecutableFinder;
        $disk = $media->disk();

        // --- Video contoh 45 detik (pola uji + nada) untuk K3-01 ---
        $k3 = Program::where('code', 'K3-01')->where('version', 1)->first();
        if ($k3 && $finder->find('ffmpeg') && ! Lesson::where('title', 'Video: Pengantar K3 di Area Kerja')->exists()) {
            $key = 'content/demo/'.Str::uuid().'.mp4';
            $disk->makeDirectory(dirname($key));
            $p = new Process(['ffmpeg', '-y', '-f', 'lavfi', '-i', 'testsrc2=size=1280x720:rate=25', '-f', 'lavfi', '-i', 'sine=frequency=440:sample_rate=44100',
                '-t', '45', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '30', '-c:a', 'aac', '-shortest', $disk->path($key)]);
            $p->setTimeout(300)->run();
            if ($p->isSuccessful()) {
                $file = $media->register($key, 'pengantar-k3.mp4', 'video/mp4', filesize($disk->path($key)), false);
                $media->process($file);
                $module = $k3->modules()->orderBy('sort_order')->first();
                $module->lessons()->increment('sort_order');
                $module->lessons()->create(['title' => 'Video: Pengantar K3 di Area Kerja', 'type' => 'VIDEO', 'file_id' => $file->id, 'sort_order' => 1, 'completion_threshold' => 0.9]);
            }
        }

        // --- PDF contoh 3 halaman untuk ONB-01 ---
        $onb = Program::where('code', 'ONB-01')->where('version', 1)->first();
        if ($onb && $finder->find('pdftoppm') && ! Lesson::where('title', 'Dokumen: Ringkasan Peraturan Perusahaan')->exists()) {
            $html = '<style>body{font-family:DejaVu Sans,sans-serif;color:#1F2937} h1{color:#003854} .p{page-break-after:always;padding:40px}</style>';
            $pages = [
                ['Ringkasan Peraturan Perusahaan', 'Dokumen contoh (dummy) untuk demo BLJ. Berisi ringkasan hak dan kewajiban karyawan.'],
                ['Jam Kerja & Kehadiran', 'Karyawan wajib hadir sesuai jadwal kerja yang ditetapkan. Keterlambatan dan ketidakhadiran dicatat oleh sistem kehadiran.'],
                ['Kewajiban Training', 'Setiap karyawan wajib menyelesaikan training wajib yang ditugaskan sebelum tenggat. Keterlambatan ditindaklanjuti sesuai ketentuan yang berlaku.'],
            ];
            foreach ($pages as $i => [$t, $b]) {
                $html .= '<div class="p"'.($i === count($pages) - 1 ? ' style="page-break-after:auto"' : '').'><h1>'.$t.'</h1><p style="font-size:16px;line-height:1.6">'.$b.'</p><p style="margin-top:400px;color:#4B5563">Halaman '.($i + 1).' dari '.count($pages).'</p></div>';
            }
            $key = 'content/demo/'.Str::uuid().'.pdf';
            $disk->put($key, Pdf::loadHTML($html)->setPaper('a4')->output());
            $file = $media->register($key, 'ringkasan-peraturan.pdf', 'application/pdf', $disk->size($key), false);
            $media->process($file);
            $module = $onb->modules()->orderBy('sort_order')->skip(1)->first();
            $module->lessons()->increment('sort_order');
            $module->lessons()->create(['title' => 'Dokumen: Ringkasan Peraturan Perusahaan', 'type' => 'PDF', 'file_id' => $file->id, 'sort_order' => 1, 'min_seconds' => 10]);
        }
    }
}
