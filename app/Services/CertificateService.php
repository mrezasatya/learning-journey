<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\StoredFile;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Sertifikat otomatis: PDF + nomor unik + QR verifikasi (BR-33 s.d. BR-36). */
class CertificateService
{
    public function issue(Assignment $a): Certificate
    {
        if ($a->certificate) {
            return $a->certificate;
        }

        $cert = DB::transaction(function () use ($a) {
            $program = $a->program;
            $year = ($a->completed_at ?? now())->year;
            $prefix = "BLJ/{$program->code}/{$year}/";
            $last = Certificate::where('certificate_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('certificate_no')->value('certificate_no');
            $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

            return Certificate::create([
                'assignment_id' => $a->id,
                'certificate_no' => $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT), // BR-34
                'issued_at' => $a->completed_at ?? now(),
                'valid_until' => $program->type === 'ANNUAL' ? Carbon::create($a->period_year ?: $year, 12, 31) : null, // BR-36
                'verify_token' => Str::random(40),
            ]);
        });
        $this->render($cert);

        return $cert;
    }

    /** (Re)generate PDF sertifikat dan simpan sebagai berkas privat. */
    public function render(Certificate $cert): StoredFile
    {
        $cert->load('assignment.employee', 'assignment.program');
        $verifyUrl = route('certificates.verify', $cert->verify_token);
        $qr = (new QRCode(new QROptions(['outputInterface' => QRGdImagePNG::class, 'scale' => 6, 'outputBase64' => true])))->render($verifyUrl);

        $pdf = Pdf::loadView('certificates.pdf', ['cert' => $cert, 'a' => $cert->assignment, 'qr' => $qr, 'verifyUrl' => $verifyUrl])
            ->setPaper('a4', 'landscape')->output();

        $disk = config('blj.content_disk');
        $key = 'certificates/'.$cert->issued_at->format('Y').'/'.Str::slug(str_replace('/', '-', $cert->certificate_no)).'.pdf';
        Storage::disk($disk)->put($key, $pdf);
        $file = $cert->file ?? new StoredFile;
        $file->fill(['disk' => $disk, 'storage_key' => $key, 'original_name' => 'Sertifikat-'.str_replace('/', '-', $cert->certificate_no).'.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => strlen($pdf), 'processing_status' => 'READY'])->save();
        $cert->update(['file_id' => $file->id]);

        return $file;
    }
}
