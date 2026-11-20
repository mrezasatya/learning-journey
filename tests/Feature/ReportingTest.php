<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\DisciplinaryFollowup;
use App\Models\Program;
use App\Services\ComplianceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US-25 s.d. US-28 — BR-40, BR-41 */
class ReportingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_ringkasan_kepatuhan_benar(): void
    {
        $a = Assignment::where('period_year', now()->year)->take(3)->get();
        $a[0]->update(['status' => Assignment::COMPLETED, 'completed_at' => now()]);
        $a[1]->update(['status' => Assignment::OVERDUE]);
        $a[2]->update(['status' => Assignment::EXEMPTED]);

        $r = app(ComplianceReport::class);
        $rows = $r->rows(now()->year);
        $s = $r->summary($rows);
        $expected = Assignment::where('status', '!=', 'EXEMPTED')->where(fn ($q) => $q->where('period_year', now()->year)->orWhere('period_year', 0))
            ->whereHas('employee', fn ($q) => $q->where('is_active', true))->count();
        $this->assertSame($expected, $s['total'], 'Dikecualikan tidak dihitung');
        $this->assertSame(1, $s['done']);
        $this->assertGreaterThanOrEqual(1, $s['overdue']);
        $this->assertSame($rows->count(), $r->breakdown($rows, 'program')->sum('total'));
    }

    public function test_halaman_dashboard_laporan_dan_ekspor(): void
    {
        $this->actingAsNik('100001');
        $p = Program::where('code', 'K3-01')->first();
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Indikator keberhasilan');
        $this->get(route('admin.dashboard', ['export' => 1]))->assertOk();
        $this->get(route('admin.reports.index'))->assertOk();
        $this->get(route('admin.reports.evaluation', $p))->assertOk()->assertSee('Analisis butir soal');
        $this->get(route('admin.reports.evaluation', [$p, 'export' => 1]))->assertOk();
        $this->get(route('admin.overdue.index'))->assertOk();
        $this->get(route('admin.overdue.export'))->assertOk();
        $this->get(route('home'))->assertRedirect(route('admin.dashboard')); // Admin diarahkan ke dashboard
        $this->actingAsNik('100010')->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_alur_verifikasi_dan_pencatatan_teguran(): void
    {
        $this->actingAsNik('100001');
        $a = Assignment::where('period_year', now()->year)->first();
        $a->update(['status' => Assignment::OVERDUE]);
        $f = DisciplinaryFollowup::create(['assignment_id' => $a->id, 'status' => 'RECOMMENDED']);

        $this->post(route('admin.overdue.update', $f), ['action' => 'verify'])->assertSessionHas('success');
        $this->post(route('admin.overdue.update', $f), ['action' => 'issue'])->assertSessionHasErrors(['letter_no', 'letter_date']);
        $this->post(route('admin.overdue.update', $f), ['action' => 'issue', 'letter_no' => 'ST/HC/2026/011', 'letter_date' => now()->toDateString()])->assertSessionHas('success');

        $f->refresh();
        $this->assertSame('ISSUED', $f->status);
        $this->assertSame(Assignment::OVERDUE, $a->fresh()->status, 'Tetap wajib menyelesaikan (BR-41)');
        $this->assertSame(2, AuditLog::where('entity', 'DisciplinaryFollowup')->count());
        $this->post(route('admin.overdue.update', $f), ['action' => 'cancel', 'notes' => 'x'])->assertStatus(409);
    }
}
