<?php

namespace Tests\Feature;

use App\Events\EmployeesChanged;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Program;
use App\Services\AssignmentEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** US-11–US-14 — BR-10 s.d. BR-16 */
class AssignmentEngineTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function engine(): AssignmentEngine
    {
        return app(AssignmentEngine::class);
    }

    public function test_tenggat_sesuai_tipe_program(): void
    {
        $e = $this->engine();
        $onb = Program::where('code', 'ONB-01')->first();   // 90 hari
        $annual = Program::where('code', 'K3-01')->first();

        $this->assertSame('2026-05-01', $e->dueDate($onb, Carbon::parse('2026-01-31'))->toDateString());
        // Wajib Tahunan: 31 Desember
        $this->assertSame('2026-12-31', $e->dueDate($annual, Carbon::parse('2026-01-01'), 2026)->toDateString());
        // Masuk di tengah tahun (≤ 90 hari sebelum akhir tahun): +90 hari lebih lambat → dipakai (Q-03)
        $this->assertSame('2027-02-08', $e->dueDate($annual, Carbon::parse('2026-11-10'), 2026)->toDateString());

        $onb->deadline_days = null; // default 365
        $this->assertSame('2027-01-01', $e->dueDate($onb, Carbon::parse('2026-01-01'))->toDateString());
    }

    public function test_tidak_ada_penugasan_ganda_pada_periode_sama(): void
    {
        $emp = Employee::where('nik', '100101')->first();
        $program = Program::where('code', 'ISO-01')->first();
        $this->assertNull($this->engine()->assign($emp, $program, 'MANUAL'), 'Sudah ditugaskan oleh seeder');
        $this->assertSame(1, Assignment::where('employee_id', $emp->id)->where('program_id', $program->id)->count());

        // Periode berikutnya boleh (US-13)
        $this->assertNotNull($this->engine()->assign($emp, $program, 'MANUAL', periodYear: 2027, at: Carbon::create(2027, 1, 1)));
    }

    public function test_aturan_dinamis_saat_karyawan_pindah_jabatan(): void
    {
        $emp = Employee::where('nik', '100001')->first(); // L&D Officer di HO — tidak kena K3-02
        $k302 = Program::where('code', 'K3-02')->first();
        $this->assertFalse(Assignment::where('employee_id', $emp->id)->where('program_id', $k302->id)->exists());

        $emp->update(['position_id' => Position::where('code', 'HSEOFF')->value('id')]);
        EmployeesChanged::dispatch([$emp->id]);

        $this->assertTrue(Assignment::where('employee_id', $emp->id)->where('program_id', $k302->id)->exists());
    }

    public function test_onboarding_hanya_untuk_karyawan_masuk_setelah_aturan_berlaku(): void
    {
        $onb = Program::where('code', 'ONB-01')->first();
        $this->assertTrue(Assignment::where('program_id', $onb->id)->whereHas('employee', fn ($q) => $q->where('nik', '100102'))->exists());
        $this->assertFalse(Assignment::where('program_id', $onb->id)->whereHas('employee', fn ($q) => $q->where('nik', '100101'))->exists());
    }

    public function test_penugasan_ulang_wajib_tahunan(): void
    {
        $n = $this->engine()->assignAnnual(2027);
        $this->assertGreaterThan(100, $n);
        $this->assertSame(0, $this->engine()->assignAnnual(2027), 'Dijalankan dua kali tidak menggandakan');
        $a = Assignment::where('period_year', 2027)->first();
        $this->assertSame('2027-12-31', $a->due_at->toDateString());
        $this->assertTrue(Assignment::where('period_year', 2026)->exists(), 'Riwayat periode sebelumnya tetap');
    }

    public function test_perpanjangan_dan_pengecualian_tercatat(): void
    {
        $this->actingAsNik('100001');
        $a = Assignment::where('status', Assignment::OVERDUE)->first();
        $this->post(route('admin.assignments.exempt', $a), ['type' => 'EXTEND', 'reason_category' => 'SAKIT', 'reason' => 'pendek'])
            ->assertSessionHasErrors('reason');
        $this->post(route('admin.assignments.exempt', $a), ['type' => 'EXTEND', 'reason_category' => 'SAKIT',
            'reason' => 'Rawat inap dua minggu, surat dokter terlampir', 'new_due_at' => now()->addDays(30)->toDateString()])->assertSessionHas('success');
        $a->refresh();
        $this->assertNotSame(Assignment::OVERDUE, $a->status);
        $this->assertSame(now()->addDays(30)->toDateString(), $a->due_at->toDateString());

        $this->post(route('admin.assignments.exempt', $a), ['type' => 'EXEMPT', 'reason_category' => 'TUGAS_KHUSUS', 'reason' => 'Penugasan luar negeri hingga akhir tahun'])
            ->assertSessionHas('success');
        $this->assertSame(Assignment::EXEMPTED, $a->fresh()->status);
        $this->assertSame(2, $a->exemptions()->count());
    }

    public function test_pratinjau_dan_simpan_aturan(): void
    {
        $this->actingAsNik('100001');
        $program = Program::where('code', 'K3-02')->first();
        $payload = ['program_id' => $program->id, 'name' => 'Site saja', 'effective_from' => now()->toDateString(), 'logic' => 'AND',
            'items' => [['field' => 'location_type', 'op' => 'in', 'values' => ['SITE']]]];
        $json = $this->postJson(route('admin.rules.preview'), $payload)->assertOk()->json();
        $site = Employee::where('is_active', true)->whereHas('workLocation', fn ($q) => $q->where('type', 'SITE'))->count();
        $this->assertSame($site, $json['total']);

        $this->post(route('admin.rules.store'), $payload)->assertRedirect();
        $this->assertSame($site, Assignment::where('program_id', $program->id)->whereHas('employee', fn ($q) => $q->whereHas('workLocation', fn ($l) => $l->where('type', 'SITE')))->count());
    }

    public function test_halaman_penugasan_dapat_dibuka(): void
    {
        $this->actingAsNik('100001');
        foreach ([route('admin.rules.index'), route('admin.rules.create'), route('admin.assignments.index'), route('admin.assignments.create'),
            route('admin.assignments.show', Assignment::first()), route('admin.assignments.export')] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
