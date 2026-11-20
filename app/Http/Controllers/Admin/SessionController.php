<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Program;
use App\Models\SessionParticipant;
use App\Models\TrainingSession;
use App\Models\WorkLocation;
use App\Services\SessionService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;

/** Sesi klasikal: jadwal, peserta, QR, absensi (US-23). */
class SessionController extends Controller
{
    public function __construct(private SessionService $sessions) {}

    public function index(Request $request)
    {
        $sessions = TrainingSession::with('program')->withCount(['participants', 'participants as present_count' => fn ($q) => $q->where('attendance_status', 'PRESENT')])
            ->when($request->query('waktu', 'mendatang') === 'mendatang', fn ($q) => $q->where('end_at', '>=', now()->subHours(2))->orderBy('start_at'))
            ->when($request->query('waktu') === 'lalu', fn ($q) => $q->where('end_at', '<', now())->orderByDesc('start_at'))
            ->paginate(20)->withQueryString();

        return view('admin.sessions.index', ['sessions' => $sessions, 'programs' => Program::published()->orderBy('code')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'title' => 'required|string|max:255',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'location' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);
        $s = $this->sessions->create($data);

        return redirect()->route('admin.sessions.show', $s)->with('success', 'Sesi dibuat. Tambahkan peserta.');
    }

    public function show(Request $request, TrainingSession $session)
    {
        $session->load(['program', 'participants.employee.department', 'participants.employee.workLocation']);
        $invited = $session->participants->pluck('employee_id');
        $candidates = Assignment::with('employee.workLocation', 'employee.department')
            ->whereIn('program_id', Program::where('code', $session->program->code)->pluck('id'))->open()
            ->whereNotIn('employee_id', $invited)
            ->whereHas('employee', fn ($q) => $q->where('is_active', true)->when($request->query('lokasi'), fn ($w, $l) => $w->where('work_location_id', $l)))
            ->get()->sortBy('employee.full_name');
        $checkinUrl = route('checkin.show', $session->qr_token);
        $qr = (new QRCode(new QROptions(['outputBase64' => true, 'scale' => 8])))->render($checkinUrl);

        return view('admin.sessions.show', ['s' => $session, 'candidates' => $candidates, 'qr' => $qr, 'checkinUrl' => $checkinUrl,
            'locations' => WorkLocation::orderBy('name')->get()]);
    }

    public function invite(Request $request, TrainingSession $session)
    {
        $ids = $request->validate(['employee_ids' => 'required|array', 'employee_ids.*' => 'integer|exists:employees,id'])['employee_ids'];
        $r = $this->sessions->invite($session, $ids);

        return back()->with('success', "{$r['added']} peserta ditambahkan.".($r['full'] ? " {$r['full']} tidak masuk karena kapasitas penuh." : ''));
    }

    public function attendance(Request $request, SessionParticipant $participant)
    {
        $data = $request->validate([
            'attendance_status' => 'required|in:INVITED,PRESENT,ABSENT',
            'reason' => 'nullable|string|max:255',
            'offline_score' => 'nullable|integer|min:0|max:100',
        ]);
        $isCorrection = $participant->attendance_status !== 'INVITED' && $participant->attendance_status !== $data['attendance_status'];
        if ($isCorrection && blank($data['reason'] ?? null)) {
            return back()->withErrors(['reason' => 'Koreksi kehadiran wajib disertai alasan.']);
        }
        $this->sessions->mark($participant, $data['attendance_status'], $data['reason'] ?? null, isset($data['offline_score']) ? (int) $data['offline_score'] : null);

        return back()->with('success', 'Kehadiran '.$participant->employee->full_name.' disimpan.');
    }

    /** Lembar daftar hadir untuk dicetak (bukti offline). */
    public function printSheet(TrainingSession $session)
    {
        $session->load('program', 'participants.employee.department');

        return view('admin.sessions.print', ['s' => $session]);
    }
}
