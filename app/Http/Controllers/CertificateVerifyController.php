<?php

namespace App\Http\Controllers;

use App\Models\Certificate;

/**
 * Halaman verifikasi publik terbatas dari QR sertifikat (BR-35):
 * hanya nama, program, tanggal, dan status valid — tanpa data pribadi lain.
 */
class CertificateVerifyController extends Controller
{
    public function __invoke(string $token)
    {
        $cert = Certificate::with('assignment.employee', 'assignment.program')->where('verify_token', $token)->first();

        return response()->view('certificates.verify', ['cert' => $cert], $cert ? 200 : 404)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
