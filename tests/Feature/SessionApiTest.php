<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Program;
use App\Models\SessionParticipant;
use App\Models\TrainingSession;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** US-23, US-24, US-30, pengaturan */
class SessionApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_qr_checkin_hanya_pada_waktu_sesi(): void
    {
        $program = Program::where('code', 'K3-02')->first();
        $s = TrainingSession::where('title', 'like', '%Demo QR%')->first();
        $this->actingAsNik('100101')->get(route('checkin.show', $s->qr_token))->assertOk();
        $this->post(route('checkin.store', $s->qr_token))->assertSessionHas('success');
        $this->assertSame('PRESENT', SessionParticipant::where('session_id', $s->id)->where('employee_id', Employee::where('nik', '100101')->value('id'))->value('attendance_status'));

        $future = TrainingSession::where('start_at', '>', now()->addDays(5))->first();
        $this->post(route('checkin.store', $future->qr_token))->assertSessionHasErrors();

        // Karyawan yang tidak ditugaskan program ditolak
        $this->assertFalse(Assignment::where('program_id', $program->id)->whereHas('employee', fn ($q) => $q->where('nik', '100001'))->exists());
        $this->actingAsNik('100001')->post(route('checkin.store', $s->qr_token))->assertSessionHasErrors();
    }

    public function test_koreksi_kehadiran_wajib_alasan(): void
    {
        $this->actingAsNik('100001');
        $p = SessionParticipant::where('attendance_status', 'PRESENT')->first();
        $this->post(route('admin.sessions.attendance', $p), ['attendance_status' => 'ABSENT'])->assertSessionHasErrors('reason');
        $this->post(route('admin.sessions.attendance', $p), ['attendance_status' => 'ABSENT', 'reason' => 'Salah pindai QR'])->assertSessionHas('success');
        $this->assertSame('ABSENT', $p->fresh()->attendance_status);
        $this->get(route('admin.sessions.show', $p->session_id))->assertOk();
        $this->get(route('admin.sessions.index'))->assertOk();
    }

    public function test_penyelesaian_manual_wajib_bukti_dan_terbit_sertifikat(): void
    {
        $this->actingAsNik('100001');
        $a = Assignment::where('status', Assignment::NOT_STARTED)->first();
        $this->post(route('admin.assignments.manual', $a), ['notes' => 'Ikut sesi offline di site'])->assertSessionHasErrors('evidence');
        $this->post(route('admin.assignments.manual', $a), ['notes' => 'Ikut sesi offline di site', 'score' => 85,
            'evidence' => UploadedFile::fake()->create('daftar-hadir.pdf', 50, 'application/pdf')])->assertSessionHas('success');
        $a->refresh();
        $this->assertTrue($a->isDone());
        $this->assertSame('MANUAL', $a->completion_method);
        $this->assertSame(85, $a->final_score);
        $this->assertNotNull($a->certificate);
    }

    public function test_api_nonaktif_default_dan_berfungsi_saat_diaktifkan(): void
    {
        $this->getJson('/api/v1/compliance')->assertNotFound();

        config(['blj.api.enabled' => true, 'blj.api.token' => 'rahasia-uji-123']);
        $this->getJson('/api/v1/compliance')->assertUnauthorized();
        $auth = ['Authorization' => 'Bearer rahasia-uji-123'];
        $this->getJson('/api/v1/compliance?period='.now()->year, $auth)->assertOk()->assertJsonStructure(['summary' => ['total', 'rate']]);
        $this->getJson('/api/v1/employees/100101/trainings', $auth)->assertOk()->assertJsonPath('nik', '100101');

        $res = $this->postJson('/api/v1/sync/employees', ['employees' => [
            ['nik' => '300001', 'full_name' => 'Dari ESS', 'position_code' => 'HSEOFF', 'grade_code' => '3A', 'department_code' => 'HSE',
                'work_location_code' => 'SITE-SUL', 'employment_status' => 'PROYEK', 'join_date' => now()->toDateString(), 'supervisor_nik' => '100012'],
            ['nik' => '300002', 'full_name' => 'Data Salah', 'position_code' => 'X'],
        ]], $auth)->assertOk()->json();
        $this->assertSame(1, $res['processed']);
        $this->assertSame(1, $res['failed']);
        $new = Employee::where('nik', '300001')->first();
        $this->assertTrue($new->assignments()->exists(), 'Aturan penugasan dievaluasi untuk karyawan dari ESS');
    }

    public function test_pengaturan_dapat_diubah(): void
    {
        $this->actingAsNik('100001')->get(route('admin.settings.edit'))->assertOk();
        $payload = collect(Settings::EDITABLE)->mapWithKeys(fn ($v, $k) => [$k => is_array(Settings::get($k)) ? implode(',', Settings::get($k)) : Settings::get($k)])->all();
        $payload['annual_late_join_days'] = 60;
        $this->put(route('admin.settings.update'), $payload)->assertSessionHas('success');
        $this->assertSame(60, Settings::get('annual_late_join_days'));
    }
}
