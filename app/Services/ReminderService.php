<?php

namespace App\Services;

use App\Adapters\Notification\Notifier;
use App\Models\Assignment;
use App\Models\DisciplinaryFollowup;
use App\Models\ReminderLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Pengingat, eskalasi, dan overdue (BR-37 s.d. BR-40, US-20, US-22).
 * Dijalankan harian oleh scheduler: `php artisan blj:daily`.
 */
class ReminderService
{
    public function __construct(private Notifier $notifier) {}

    /** @return array{overdue:int, reminders:int, recommendations:int} */
    public function runDaily(): array
    {
        $overdue = $this->markOverdue();
        $reminders = $this->sendReminders();

        return ['overdue' => $overdue->count(), 'reminders' => $reminders, 'recommendations' => $overdue->count()];
    }

    /** "Saat ditugaskan" (BR-38). */
    public function assigned(Assignment $a): void
    {
        if (! $this->log($a, 'ASSIGNED') || ! ($user = $a->employee->user)) {
            return;
        }
        $this->notifier->send($user, 'ASSIGNED', 'Training wajib baru: '.$a->program->name,
            "Anda ditugaskan mengikuti \"{$a->program->name}\". Tenggat: {$a->due_at->translatedFormat('d F Y')}.",
            route('learn.program', $a));
    }

    /** H+1 setelah tenggat → Overdue; notifikasi Karyawan, Atasan, Admin; rekomendasi teguran (BR-39, BR-40). */
    public function markOverdue(): Collection
    {
        $items = Assignment::with('employee.user', 'employee.supervisor.user', 'program')
            ->where(fn ($q) => $q->whereIn('status', [Assignment::NOT_STARTED, Assignment::IN_PROGRESS])->where('due_at', '<', now())
                // juga penugasan yang sudah lewat tenggat sejak dibuat dan belum masuk daftar rekomendasi
                ->orWhere(fn ($w) => $w->where('status', Assignment::OVERDUE)->whereDoesntHave('followup')))
            ->get();

        foreach ($items as $a) {
            $a->update(['status' => Assignment::OVERDUE]);
            DisciplinaryFollowup::firstOrCreate(['assignment_id' => $a->id], ['status' => 'RECOMMENDED']);
            if ($this->log($a, 'OVERDUE')) {
                if ($u = $a->employee->user) {
                    $this->notifier->send($u, 'OVERDUE', 'Overdue: '.$a->program->name,
                        "Training wajib \"{$a->program->name}\" melewati tenggat {$a->due_at->translatedFormat('d F Y')}. Anda tetap wajib menyelesaikannya segera.",
                        route('learn.program', $a));
                }
                if ($s = $a->employee->supervisor?->user) {
                    $this->notifier->send($s, 'TEAM_OVERDUE', "Anggota tim overdue: {$a->employee->full_name}",
                        "{$a->employee->full_name} ({$a->employee->nik}) belum menyelesaikan \"{$a->program->name}\" (tenggat {$a->due_at->translatedFormat('d F Y')}). Mohon ditindaklanjuti.",
                        route('team.index', ['status' => 'OVERDUE']));
                }
            }
        }
        if ($items->isNotEmpty()) { // admin menerima ringkasan, bukan satu notifikasi per karyawan
            foreach ($this->admins() as $admin) {
                $this->notifier->send($admin, 'ADMIN_OVERDUE', $items->count().' penugasan baru menjadi overdue',
                    'Daftar rekomendasi teguran telah diperbarui. Verifikasi sebelum diproses melalui Industrial Relations.',
                    \Illuminate\Support\Facades\Route::has('admin.overdue.index') ? route('admin.overdue.index') : route('admin.assignments.index', ['status' => 'OVERDUE']));
            }
        }

        return $items;
    }

    /** H-90, H-30, H-14, H-7, H-1 ke karyawan; salinan ke Atasan mulai H-30 (BR-38). */
    public function sendReminders(): int
    {
        $days = collect(Settings::get('reminder_days', [90, 30, 14, 7, 1]))->map(fn ($d) => (int) $d)->sort()->values();
        $copyFrom = (int) Settings::get('supervisor_copy_from_days', 30);
        $sent = 0;
        Assignment::with('employee.user', 'employee.supervisor.user', 'program')
            ->whereIn('status', [Assignment::NOT_STARTED, Assignment::IN_PROGRESS])
            ->where('due_at', '<=', now()->addDays($days->max() + 1))
            ->chunkById(200, function ($chunk) use ($days, $copyFrom, &$sent) {
                foreach ($chunk as $a) {
                    $left = $a->daysLeft();
                    // Ambang terdekat yang sudah tercapai; ambang lebih jauh yang terlewat tidak dikirim mundur
                    $t = $days->first(fn ($d) => $left <= $d);
                    if ($t === null || ! $this->log($a, 'H-'.$t)) {
                        continue;
                    }
                    $sent++;
                    $when = $left <= 0 ? 'hari ini' : "dalam {$left} hari ({$a->due_at->translatedFormat('d F Y')})";
                    if ($u = $a->employee->user) {
                        $this->notifier->send($u, 'REMINDER', "Pengingat H-{$t}: {$a->program->name}",
                            "Training wajib \"{$a->program->name}\" berakhir {$when}. Progres tersimpan otomatis — lanjutkan kapan saja.",
                            route('learn.program', $a));
                    }
                    if ($t <= $copyFrom && ($s = $a->employee->supervisor?->user)) {
                        $this->notifier->send($s, 'TEAM_REMINDER', "Salinan pengingat H-{$t}: {$a->employee->full_name}",
                            "{$a->employee->full_name} belum menyelesaikan \"{$a->program->name}\" yang berakhir {$when}.",
                            route('team.index', ['q' => $a->employee->nik]), ['IN_APP']);
                    }
                }
            });

        return $sent;
    }

    /** Gagal post-test 3× → notifikasi Atasan & Admin (BR-24). */
    public function relearn(Assignment $a): void
    {
        $text = "{$a->employee->full_name} ({$a->employee->nik}) belum lulus post-test \"{$a->program->name}\" setelah seluruh percobaan dan wajib mengulang materi.";
        if ($s = $a->employee->supervisor?->user) {
            $this->notifier->send($s, 'RELEARN', 'Perlu mengulang materi: '.$a->employee->full_name, $text, route('team.index', ['q' => $a->employee->nik]));
        }
        foreach ($this->admins() as $admin) {
            $this->notifier->send($admin, 'RELEARN', 'Perlu mengulang materi: '.$a->employee->full_name, $text, route('admin.assignments.show', $a), ['IN_APP']);
        }
    }

    /** Mencatat pengingat; false bila sudah pernah dikirim (mencegah ganda). */
    private function log(Assignment $a, string $code): bool
    {
        return ReminderLog::firstOrCreate(['assignment_id' => $a->id, 'reminder_code' => $code], ['sent_at' => now()])->wasRecentlyCreated;
    }

    private function admins(): Collection
    {
        return User::whereHas('roles', fn ($q) => $q->where('code', Role::ADMIN_LD))->get();
    }
}
