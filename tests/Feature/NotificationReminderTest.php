<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Assignment;
use App\Models\DisciplinaryFollowup;
use App\Models\Employee;
use App\Models\Program;
use App\Services\AssignmentEngine;
use App\Services\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US-20 s.d. US-22 — BR-37 s.d. BR-40, BR-48 */
class NotificationReminderTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function budiIso(): Assignment
    {
        return Assignment::where('employee_id', Employee::where('nik', '100101')->value('id'))
            ->where('program_id', Program::where('code', 'ISO-01')->value('id'))->first();
    }

    private function notes(string $nik, string $type): int
    {
        return AppNotification::where('user_id', $this->user($nik)->id)->where('type', $type)->where('channel', 'IN_APP')->count();
    }

    public function test_notifikasi_saat_ditugaskan(): void
    {
        $emp = Employee::where('nik', '100101')->first();
        app(AssignmentEngine::class)->assign($emp, Program::where('code', 'ONB-01')->first(), 'MANUAL');
        $this->assertSame(1, $this->notes('100101', 'ASSIGNED'));
    }

    public function test_pengingat_bertahap_tanpa_duplikasi_dan_salinan_atasan(): void
    {
        $a = $this->budiIso();
        AppNotification::query()->delete();
        $a->update(['due_at' => now()->addDays(25)->endOfDay()]);
        $svc = app(ReminderService::class);

        $svc->sendReminders();
        $svc->sendReminders(); // dijalankan dua kali → tetap satu
        $this->assertSame(1, $this->notes('100101', 'REMINDER'));
        $this->assertSame(1, $this->notes('100010', 'TEAM_REMINDER'), 'H-30: salinan ke atasan');
        $this->assertTrue($a->reminderLogs()->where('reminder_code', 'H-30')->exists());

        $this->travel(19)->days(); // sisa 6 hari → H-7
        $svc->sendReminders();
        $this->assertTrue($a->reminderLogs()->where('reminder_code', 'H-7')->exists());
        $this->assertFalse($a->reminderLogs()->where('reminder_code', 'H-14')->exists(), 'Ambang terlewat tidak dikirim mundur');
    }

    public function test_h_plus_1_overdue_dan_rekomendasi_teguran(): void
    {
        $a = $this->budiIso();
        $a->update(['due_at' => now()->subDay()]);
        AppNotification::query()->delete();
        $this->artisan('blj:daily')->assertSuccessful();

        $this->assertSame(Assignment::OVERDUE, $a->fresh()->status);
        $this->assertSame('RECOMMENDED', DisciplinaryFollowup::where('assignment_id', $a->id)->value('status'));
        $this->assertSame(1, $this->notes('100101', 'OVERDUE'));
        $this->assertSame(1, $this->notes('100010', 'TEAM_OVERDUE'));
        $this->assertGreaterThanOrEqual(1, $this->notes('100001', 'ADMIN_OVERDUE'));
    }

    public function test_tim_saya_hanya_anggota_tim(): void
    {
        $this->actingAsNik('100101')->get(route('team.index'))->assertForbidden();

        $res = $this->actingAsNik('100010')->get(route('team.index'))->assertOk();
        $res->assertSee('Budi Setiawan')->assertDontSee('Maya Anggraini'); // Maya bukan anggota tim 100010
        $this->get(route('team.export'))->assertOk();

        // Division Head melihat tim tidak langsung (Q-05)
        $this->actingAsNik('100901')->get(route('team.index', ['lingkup' => 'semua']))->assertOk()->assertSee('Budi Setiawan');
        $this->actingAsNik('100901')->get(route('team.index'))->assertDontSee('Budi Setiawan');
    }

    public function test_halaman_notifikasi(): void
    {
        $this->test_notifikasi_saat_ditugaskan();
        $n = AppNotification::where('user_id', $this->user('100101')->id)->where('channel', 'IN_APP')->first();
        $this->actingAsNik('100101')->get(route('notifications.index'))->assertOk()->assertSee($n->title);
        $this->get(route('notifications.open', $n))->assertRedirect();
        $this->assertNotNull($n->fresh()->read_at);
        $this->actingAsNik('100010')->get(route('notifications.open', $n))->assertForbidden();
    }
}
