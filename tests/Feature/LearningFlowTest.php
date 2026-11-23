<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US-15 s.d. US-19 — BR-17 s.d. BR-36 */
class LearningFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function assignment(string $code = 'ISO-01', string $nik = '100101'): Assignment
    {
        return Assignment::where('employee_id', Employee::where('nik', $nik)->value('id'))
            ->where('program_id', Program::where('code', $code)->value('id'))->firstOrFail();
    }

    /** Mengerjakan tes dengan jumlah jawaban benar tertentu. */
    private function doTest(Assignment $a, string $kind, ?int $correctCount = null): AssessmentAttempt
    {
        $this->post(route('learn.test.start', [$a, $kind]))->assertRedirect();
        $attempt = AssessmentAttempt::where('assignment_id', $a->id)->whereNull('submitted_at')->latest('id')->firstOrFail();
        foreach ($attempt->answers()->with('question.options')->get() as $i => $ans) {
            $right = $correctCount === null || $i < $correctCount;
            $ids = $right ? $ans->question->correctOptionIds() : [$ans->question->options->firstWhere('is_correct', false)->id];
            $this->postJson(route('learn.test.answer', [$a, $attempt]), ['question_id' => $ans->question_id, 'option_ids' => $ids])->assertOk();
        }
        $this->post(route('learn.test.submit', [$a, $attempt]))->assertRedirect(route('learn.test.result', [$a, $attempt]));

        return $attempt->fresh();
    }

    private function completeLessons(Assignment $a): void
    {
        foreach ($a->program->orderedLessons() as $lesson) {
            $this->get(route('learn.lesson', [$a, $lesson]))->assertOk();
            $this->travel(2)->minutes();
            $this->postJson(route('learn.progress', [$a, $lesson]))->assertJson(['ok' => true]);
        }
    }

    public function test_beranda_dan_urutan_alur_belajar(): void
    {
        $this->actingAsNik('100101');
        $a = $this->assignment();
        $this->get(route('employee.home'))->assertOk()->assertSee($a->program->name);

        // Materi terkunci sebelum pre-test (BR-21)
        $lesson = $a->program->orderedLessons()->first();
        $this->get(route('learn.lesson', [$a, $lesson]))->assertRedirect(route('learn.program', $a));
        // Post-test belum boleh
        $this->post(route('learn.test.start', [$a, 'POST']))->assertSessionHasErrors();

        $pre = $this->doTest($a, 'PRE', 5);
        $this->assertSame($pre->score, $a->fresh()->pre_score);
        $this->assertSame('IN_PROGRESS', $a->fresh()->status);
        // Pre-test hanya sekali
        $this->post(route('learn.test.start', [$a, 'PRE']))->assertSessionHasErrors();

        // Materi kedua terkunci sebelum materi pertama selesai (BR-18)
        $second = $a->program->orderedLessons()[1];
        $this->get(route('learn.lesson', [$a, $second]))->assertRedirect();

        // Teks: "Tandai selesai" ditolak sebelum waktu minimum (BR-19)
        $this->get(route('learn.lesson', [$a, $lesson]))->assertOk();
        $this->postJson(route('learn.progress', [$a, $lesson]))->assertJson(['ok' => false]);
        $this->travel(2)->minutes();
        $this->postJson(route('learn.progress', [$a, $lesson]))->assertJson(['ok' => true]);
        $this->get(route('learn.lesson', [$a, $second]))->assertOk();
    }

    public function test_remedial_jeda_dan_reset_materi(): void
    {
        $this->actingAsNik('100101');
        $a = $this->assignment();
        $this->doTest($a, 'PRE');
        $this->completeLessons($a);

        $first = $this->doTest($a, 'POST', 5); // 50 → gagal
        $this->assertFalse($first->passed);
        // Jeda 60 menit (BR-23)
        $this->post(route('learn.test.start', [$a, 'POST']))->assertSessionHasErrors();
        $this->travel(61)->minutes();
        $this->assertFalse($this->doTest($a, 'POST', 5)->passed);
        $this->travel(61)->minutes();
        $this->assertFalse($this->doTest($a, 'POST', 5)->passed);

        // Percobaan ke-3 gagal → siklus baru, materi harus diulang (BR-24)
        $a->refresh();
        $this->assertSame(2, $a->cycle_no);
        $this->travel(61)->minutes();
        $this->post(route('learn.test.start', [$a, 'POST']))->assertSessionHasErrors();
        $this->completeLessons($a);
        $passed = $this->doTest($a, 'POST');
        $this->assertTrue($passed->passed);
        $this->assertSame(100, $a->fresh()->final_score, 'Nilai akhir = nilai tertinggi (BR-25)');
    }

    public function test_lulus_survei_lalu_sertifikat_terbit_dan_dapat_diverifikasi(): void
    {
        $this->actingAsNik('100101');
        $a = $this->assignment();
        $this->doTest($a, 'PRE');
        $this->completeLessons($a);
        $this->doTest($a, 'POST', 9); // 90 → lulus
        $this->assertNull($a->fresh()->certificate, 'Belum terbit sebelum survei (BR-30)');

        $answers = ['relevance' => 5, 'clarity' => 4, 'access' => 4, 'duration' => 3, 'benefit' => 5, 'comment' => 'Bagus'];
        $this->post(route('learn.survey.store', $a), ['answers' => $answers])->assertRedirect(route('learn.program', $a));

        $a->refresh();
        $this->assertSame(Assignment::COMPLETED, $a->status);
        $this->assertSame(90, $a->final_score);
        $cert = $a->certificate;
        $this->assertMatchesRegularExpression('#^BLJ/ISO-01/\d{4}/\d{5}$#', $cert->certificate_no); // BR-34
        $this->assertSame(now()->year.'-12-31', $cert->valid_until->toDateString()); // BR-36

        $this->get(route('learn.certificate', $a))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Verifikasi publik: tanpa login, tanpa NIK (BR-35)
        auth()->logout();
        $this->get(route('certificates.verify', $cert->verify_token))->assertOk()
            ->assertSee('Sertifikat VALID')->assertSee($a->employee->full_name)->assertDontSee($a->employee->nik);
    }

    public function test_video_tidak_bisa_dilompati(): void
    {
        $this->actingAsNik('100101');
        $a = $this->assignment('K3-01');
        $program = $a->program;
        // Buat materi video tiruan berdurasi 300 detik di urutan pertama
        $file = \App\Models\StoredFile::create(['storage_key' => 'x.mp4', 'original_name' => 'x.mp4', 'duration_seconds' => 300, 'renditions' => ['360p' => 'x-360p.mp4']]);
        $module = $program->modules()->first();
        $module->lessons()->increment('sort_order');
        $video = $module->lessons()->create(['title' => 'Video', 'type' => 'VIDEO', 'file_id' => $file->id, 'sort_order' => 0, 'completion_threshold' => 0.9]);

        $this->doTest($a, 'PRE');
        $this->postJson(route('learn.progress', [$a, $video]), ['position' => 0])->assertOk();
        // Lompat ke detik 290 tanpa waktu berlalu → ditolak (posisi dibatasi waktu nyata)
        $r = $this->postJson(route('learn.progress', [$a, $video]), ['position' => 290])->json();
        $this->assertLessThan(10, $r['max_position']);
        $this->assertFalse($r['completed']);
        // Menonton normal 5 menit
        for ($i = 0; $i < 30; $i++) {
            $this->travel(10)->seconds();
            $r = $this->postJson(route('learn.progress', [$a, $video]), ['position' => ($i + 1) * 10])->json();
        }
        $this->assertTrue($r['completed']);
    }

    public function test_selesai_setelah_tenggat_menjadi_selesai_terlambat(): void
    {
        $this->actingAsNik('100101');
        $a = $this->assignment();
        $a->update(['due_at' => now()->subDay(), 'status' => Assignment::OVERDUE]);
        $this->doTest($a, 'PRE');
        $this->completeLessons($a);
        $this->doTest($a, 'POST');
        $this->post(route('learn.survey.store', $a), ['answers' => ['relevance' => 4, 'clarity' => 4, 'access' => 4, 'duration' => 4, 'benefit' => 4]]);
        $this->assertSame(Assignment::COMPLETED_LATE, $a->fresh()->status); // BR-41
    }

    public function test_karyawan_lain_tidak_bisa_mengakses(): void
    {
        $a = $this->assignment();
        $this->actingAsNik('100010')->get(route('learn.program', $a))->assertForbidden();
    }
}
