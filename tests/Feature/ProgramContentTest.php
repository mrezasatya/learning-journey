<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Question;
use App\Services\ProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US-06, US-09, US-10 — BR-07, BR-08, BR-09, BR-15 */
class ProgramContentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_membuat_program_draft_lalu_terbit_setelah_lengkap(): void
    {
        $this->actingAsNik('100001');
        $this->post(route('admin.programs.store'), ['code' => 'ISO-99', 'name' => 'Uji', 'type' => 'ONCE', 'category' => 'ISO', 'deadline_days' => 400])
            ->assertSessionHasErrors('deadline_days'); // BR-15: maks 365

        $this->post(route('admin.programs.store'), ['code' => 'ISO-99', 'name' => 'Uji', 'type' => 'ONCE', 'category' => 'ISO', 'deadline_days' => 60, 'sequential' => 1])
            ->assertRedirect();
        $program = Program::where('code', 'ISO-99')->first();
        $this->assertSame('DRAFT', $program->status);

        // Belum ada modul → tidak bisa terbit (BR-07)
        $this->post(route('admin.programs.publish', $program))->assertSessionHasErrors();

        $this->post(route('admin.modules.store', $program), ['title' => 'Modul 1']);
        $module = $program->modules()->first();
        $this->post(route('admin.lessons.store', $module), ['title' => 'Artikel', 'type' => 'TEXT', 'body' => '<p>Isi <script>alert(1)</script></p>'])->assertRedirect();
        $this->assertStringNotContainsString('script', $module->lessons()->first()->body, 'HTML dibersihkan');

        $program->assessments()->update(['question_count' => 2]);
        foreach ([1, 2] as $i) {
            $this->post(route('admin.questions.store', $program), ['type' => 'SINGLE', 'difficulty' => 'EASY', 'text' => "Soal $i",
                'options' => ['A', 'B', '', ''], 'correct' => ['0']])->assertRedirect();
        }
        $this->post(route('admin.questions.store', $program), ['type' => 'SINGLE', 'difficulty' => 'EASY', 'text' => 'Dua jawaban',
            'options' => ['A', 'B'], 'correct' => ['0', '1']])->assertSessionHasErrors('correct');

        $this->post(route('admin.programs.publish', $program))->assertSessionHas('success');
        $this->assertSame('PUBLISHED', $program->fresh()->status);
    }

    public function test_versi_baru_menyalin_isi_dan_mengarsipkan_versi_lama_saat_terbit(): void
    {
        $this->actingAsNik('100001');
        $v1 = Program::where('code', 'K3-01')->first();
        $service = app(ProgramService::class);
        $v2 = $service->newVersion($v1);

        $this->assertSame(2, $v2->version);
        $this->assertSame('DRAFT', $v2->status);
        $this->assertSame($v1->lessons()->count(), $v2->lessons()->count());
        $this->assertSame($v1->questionBank->questions()->count(), Question::where('bank_id', $v2->questionBank->id)->count());

        $this->assertSame([], $service->publish($v2));
        $this->assertSame('ARCHIVED', $v1->fresh()->status);
    }

    public function test_impor_soal_dari_template(): void
    {
        $this->actingAsNik('100001');
        $program = Program::where('code', 'ISO-01')->first();
        $before = $program->questionBank->questions()->count();
        $path = tempnam(sys_get_temp_dir(), 'q').'.csv';
        file_put_contents($path, "tipe,tingkat,modul_ke,pertanyaan,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,jawaban,pembahasan\n"
            ."SINGLE,EASY,1,Apa itu mutu?,Kesesuaian dengan persyaratan,Harga murah,,,,A,\n"
            ."TRUE_FALSE,MEDIUM,,ISO adalah standar internasional.,,,,,,BENAR,\n");
        $file = new \Illuminate\Http\UploadedFile($path, 'soal.csv', 'text/csv', null, true);
        $this->post(route('admin.questions.import', $program), ['file' => $file])->assertSessionHas('success');
        $this->assertSame($before + 2, $program->questionBank->questions()->count());
    }

    public function test_halaman_admin_konten_dapat_dibuka(): void
    {
        $this->actingAsNik('100001');
        $p = Program::where('code', 'K3-01')->first();
        foreach ([route('admin.programs.index'), route('admin.programs.create'), route('admin.programs.show', $p), route('admin.programs.edit', $p),
            route('admin.questions.index', $p), route('admin.questions.create', $p),
            route('admin.surveys.index'), route('admin.surveys.create')] as $url) {
            $this->get($url)->assertOk();
        }
        // Program berpeserta: materi dikunci (BR-09)
        $this->get(route('admin.lessons.create', $p->modules()->first()))->assertRedirect();
    }
}
