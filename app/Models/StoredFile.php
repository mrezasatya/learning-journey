<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/** Tabel `files` — semua berkas disimpan di server aplikasi, tidak pernah lewat URL publik (BR-46, 9.2). */
class StoredFile extends Model
{
    protected $table = 'files';

    protected $guarded = ['id'];

    protected $casts = ['renditions' => 'array'];

    public function disk()
    {
        return Storage::disk($this->getAttribute('disk') ?: config('blj.content_disk'));
    }

    public function isReady(): bool
    {
        return $this->processing_status === 'READY';
    }

    public function path(?string $variant = null): ?string
    {
        if ($variant === null || $variant === 'original') {
            return $this->storage_key;
        }
        if (str_starts_with($variant, 'page-')) {
            $i = (int) substr($variant, 5) - 1;

            return $this->renditions['pages'][$i] ?? null;
        }

        return $this->renditions[$variant] ?? null;
    }

    /** URL bertanda tangan & berbatas waktu (Brief 9.2). */
    public function signedUrl(string $variant = 'original', int $minutes = 120): string
    {
        return URL::temporarySignedRoute('files.show', now()->addMinutes($minutes), ['file' => $this->id, 'variant' => $variant]);
    }

    /** Resolusi video yang tersedia, dari kecil ke besar. */
    public function videoVariants(): array
    {
        return array_values(array_filter(array_keys(config('blj.video_renditions')), fn ($k) => isset($this->renditions[$k])));
    }

    public function humanSize(): string
    {
        $b = $this->size_bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $u) {
            if ($b < 1024) {
                return round($b, 1).' '.$u;
            }
            $b /= 1024;
        }

        return round($b, 1).' TB';
    }
}
