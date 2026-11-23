<?php

namespace App\Http\Controllers;

use App\Models\StoredFile;
use Illuminate\Http\Request;

/**
 * Menyajikan berkas hanya lewat URL bertanda tangan & berbatas waktu + wajib login (Brief 9.2, BR-46).
 * Mendukung HTTP Range sehingga video dapat diputar bertahap (progressive download).
 */
class FileController extends Controller
{
    public function show(Request $request, StoredFile $file, string $variant = 'original')
    {
        $key = $file->path($variant);
        abort_unless($key, 404);
        $disk = $file->disk();
        abort_unless($disk->exists($key), 404);

        if (method_exists($disk, 'path') && is_file($disk->path($key))) {
            $name = $variant === 'original' ? $file->original_name : pathinfo($file->original_name, PATHINFO_FILENAME).'-'.$variant.'.'.pathinfo($key, PATHINFO_EXTENSION);

            return response()->file($disk->path($key), [
                'Cache-Control' => 'private, max-age=3600',
                'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.addslashes($name).'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // Disk S3/MinIO: alihkan ke URL sementara milik storage
        return redirect()->away($disk->temporaryUrl($key, now()->addMinutes(30)));
    }
}
