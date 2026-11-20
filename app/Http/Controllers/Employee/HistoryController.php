<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Services\CertificateService;
use Illuminate\Http\Request;

/** Riwayat training & unduh ulang sertifikat (US-19). */
class HistoryController extends Controller
{
    use Concerns;

    public function index(Request $request)
    {
        $items = Assignment::with(['program', 'certificate'])->where('employee_id', $request->user()->employee_id)
            ->orderByDesc('period_year')->orderByDesc('completed_at')->orderBy('due_at')->get();

        return view('employee.history', ['items' => $items]);
    }

    public function certificate(Assignment $assignment, CertificateService $certificates)
    {
        $a = $this->own($assignment);
        $cert = $a->certificate;
        abort_unless($cert, 404, 'Sertifikat belum terbit.');
        $file = $cert->file && $cert->file->disk()->exists($cert->file->storage_key) ? $cert->file : $certificates->render($cert);

        return response($file->disk()->get($file->storage_key), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => (request()->boolean('inline') ? 'inline' : 'attachment').'; filename="'.$file->original_name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
