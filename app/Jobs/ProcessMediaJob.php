<?php

namespace App\Jobs;

use App\Models\StoredFile;
use App\Services\MediaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Konversi media di latar belakang (US-07 AC1). Dijalankan oleh `php artisan queue:work`. */
class ProcessMediaJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 2;

    public function __construct(public int $fileId) {}

    public function handle(MediaService $media): void
    {
        if ($file = StoredFile::find($this->fileId)) {
            $media->process($file);
        }
    }
}
