<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryFollowup;
use App\Services\AuditLogger;
use App\Services\MediaService;
use App\Support\Spreadsheet;
use Illuminate\Http\Request;

/**
 * Daftar Rekomendasi Teguran Tertulis & tindak lanjut (BR-40, BR-41, US-27).
 * Sistem TIDAK menerbitkan teguran — Admin memverifikasi; penerbitan melalui mekanisme IR sesuai PP/PKB.
 */
class OverdueController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'RECOMMENDED');
        $items = $this->query($request, $status)->paginate(30)->withQueryString();
        $counts = DisciplinaryFollowup::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');

        return view('admin.overdue.index', compact('items', 'status', 'counts'));
    }

    public function export(Request $request)
    {
        $rows = $this->query($request, $request->query('status', 'RECOMMENDED'))->get()->map(fn ($f) => [
            $f->assignment->employee->nik, $f->assignment->employee->full_name, $f->assignment->employee->department?->name,
            $f->assignment->employee->supervisor?->full_name, $f->assignment->program->code, $f->assignment->program->name,
            $f->assignment->periodLabel(), $f->assignment->due_at->format('Y-m-d'), $f->assignment->statusLabel(),
            DisciplinaryFollowup::STATUSES[$f->status], $f->letter_no, $f->letter_date?->format('Y-m-d'), $f->notes,
        ]);

        return Spreadsheet::download('rekomendasi-teguran-'.now()->format('Ymd').'.xlsx', ['Teguran' => [
            ['NIK', 'Nama', 'Departemen', 'Atasan', 'Kode', 'Program', 'Periode', 'Tenggat', 'Status training', 'Status teguran', 'No. surat', 'Tgl surat', 'Catatan'], $rows]]);
    }

    public function update(Request $request, DisciplinaryFollowup $followup, MediaService $media)
    {
        $data = $request->validate([
            'action' => 'required|in:verify,issue,cancel',
            'letter_no' => 'required_if:action,issue|nullable|string|max:100',
            'letter_date' => 'required_if:action,issue|nullable|date|before_or_equal:today',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notes' => 'required_if:action,cancel|nullable|string|max:2000',
        ], ['notes.required_if' => 'Alasan pembatalan wajib diisi.', 'letter_no.required_if' => 'Nomor surat wajib diisi.', 'letter_date.required_if' => 'Tanggal surat wajib diisi.']);

        $before = $followup->only(['status', 'letter_no', 'letter_date', 'notes']);
        match ($data['action']) {
            'verify' => $followup->status === 'RECOMMENDED' ? $followup->status = 'VERIFIED' : abort(409),
            'issue' => in_array($followup->status, ['RECOMMENDED', 'VERIFIED'], true) ? $followup->status = 'ISSUED' : abort(409),
            'cancel' => $followup->status !== 'ISSUED' ? $followup->status = 'CANCELLED' : abort(409),
        };
        if ($data['action'] === 'issue') {
            $followup->letter_no = $data['letter_no'];
            $followup->letter_date = $data['letter_date'];
            if ($request->hasFile('document')) {
                $followup->document_file_id = $media->store($request->file('document'), 'disciplinary', false)->id;
            }
        }
        if (! empty($data['notes'])) {
            $followup->notes = $data['notes'];
        }
        $followup->handled_by = $request->user()->id;
        $followup->save();
        AuditLogger::log('disciplinary.'.$data['action'], $followup, $before, $followup->only(['status', 'letter_no', 'letter_date', 'notes']));

        return back()->with('success', [
            'verify' => 'Rekomendasi diverifikasi. Lanjutkan proses penerbitan melalui Industrial Relations.',
            'issue' => 'Tindak lanjut teguran dicatat. Karyawan tetap wajib menyelesaikan training (BR-41).',
            'cancel' => 'Rekomendasi dibatalkan.',
        ][$data['action']]);
    }

    private function query(Request $request, string $status)
    {
        return DisciplinaryFollowup::with(['assignment.employee.department', 'assignment.employee.supervisor', 'assignment.program', 'assignment.exemptions', 'handler', 'document'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $s) => $q->whereHas('assignment.employee', fn ($e) => $e->where('nik', 'like', "%$s%")->orWhere('full_name', 'like', "%$s%")))
            ->orderBy('created_at');
    }
}
