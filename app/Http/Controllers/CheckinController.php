<?php

namespace App\Http\Controllers;

use App\Models\TrainingSession;
use App\Services\SessionService;
use Illuminate\Http\Request;

/** QR check-in sesi klasikal dari HP karyawan (BR-43). */
class CheckinController extends Controller
{
    public function show(Request $request, string $token)
    {
        $s = TrainingSession::with('program')->where('qr_token', $token)->firstOrFail();
        $p = $s->participants()->where('employee_id', $request->user()->employee_id)->first();

        return view('employee.checkin', ['s' => $s, 'p' => $p]);
    }

    public function store(Request $request, string $token, SessionService $sessions)
    {
        $s = TrainingSession::with('program')->where('qr_token', $token)->firstOrFail();
        abort_unless($request->user()->employee, 403);
        $result = $sessions->checkin($s, $request->user()->employee);

        return $result === 'OK'
            ? back()->with('success', 'Kehadiran Anda tercatat. Terima kasih!')
            : back()->withErrors([$result]);
    }

    /** Jadwal sesi klasikal milik karyawan. */
    public function mySessions(Request $request)
    {
        $items = \App\Models\SessionParticipant::with('session.program')->where('employee_id', $request->user()->employee_id)
            ->get()->sortByDesc(fn ($p) => $p->session->start_at);

        return view('employee.sessions', ['items' => $items]);
    }
}
