<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttemptAnswer;
use App\Models\Program;
use App\Models\Question;
use App\Services\AuditLogger;
use App\Services\ProgramService;
use App\Support\Spreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Bank soal per program/modul (US-09). */
class QuestionController extends Controller
{
    public function index(Request $request, Program $program, ProgramService $programs)
    {
        $programs->ensureDefaults($program);
        $questions = $program->questionBank->questions()->with(['options', 'module'])
            ->when($request->query('difficulty'), fn ($q, $d) => $q->where('difficulty', $d))
            ->when($request->query('module_id'), fn ($q, $m) => $q->where('module_id', $m))
            ->orderByDesc('is_active')->orderBy('id')->paginate(30)->withQueryString();

        return view('admin.questions.index', compact('program', 'questions'));
    }

    public function create(Program $program)
    {
        return view('admin.questions.form', ['program' => $program, 'question' => new Question(['type' => 'SINGLE', 'difficulty' => 'MEDIUM'])]);
    }

    public function store(Request $request, Program $program)
    {
        $data = $this->validated($request, $program);
        $question = DB::transaction(fn () => $this->save(new Question(['bank_id' => $program->questionBank->id]), $data));
        AuditLogger::log('question.create', $question, null, ['text' => $question->text]);

        return redirect()->route('admin.questions.index', $program)->with('success', 'Soal ditambahkan.');
    }

    public function edit(Question $question)
    {
        $question->load('options');

        return view('admin.questions.form', ['program' => $question->bank->program, 'question' => $question]);
    }

    public function update(Request $request, Question $question)
    {
        $program = $question->bank->program;
        if (AttemptAnswer::where('question_id', $question->id)->exists()) {
            return back()->withErrors(['Soal sudah pernah dikerjakan peserta. Nonaktifkan soal ini dan buat soal baru agar riwayat nilai tetap valid.']);
        }
        $data = $this->validated($request, $program);
        DB::transaction(fn () => $this->save($question, $data));
        AuditLogger::log('question.update', $question, null, ['text' => $question->text]);

        return redirect()->route('admin.questions.index', $program)->with('success', 'Soal diperbarui.');
    }

    public function toggle(Question $question)
    {
        $question->update(['is_active' => ! $question->is_active]);
        AuditLogger::log($question->is_active ? 'question.activate' : 'question.deactivate', $question);

        return back()->with('success', $question->is_active ? 'Soal diaktifkan.' : 'Soal dinonaktifkan.');
    }

    public function destroy(Question $question)
    {
        if (AttemptAnswer::where('question_id', $question->id)->exists()) {
            return back()->withErrors(['Soal sudah pernah dikerjakan sehingga tidak dapat dihapus — nonaktifkan saja.']);
        }
        AuditLogger::log('question.delete', $question, ['text' => $question->text]);
        $question->delete();

        return back()->with('success', 'Soal dihapus.');
    }

    // ---------- Impor Excel (US-09 AC2) ----------

    public function template()
    {
        return Spreadsheet::download('template-bank-soal.xlsx', [
            'Soal' => [['tipe', 'tingkat', 'modul_ke', 'pertanyaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'jawaban', 'pembahasan'], [
                ['SINGLE', 'MEDIUM', '1', 'Contoh soal pilihan ganda?', 'Jawaban benar', 'Pengecoh 1', 'Pengecoh 2', 'Pengecoh 3', '', 'A', ''],
                ['MULTI', 'HARD', '', 'Contoh soal kompleks (pilih semua yang benar)?', 'Benar 1', 'Salah', 'Benar 2', '', '', 'A,C', ''],
                ['TRUE_FALSE', 'EASY', '', 'Contoh pernyataan benar/salah.', '', '', '', '', '', 'BENAR', ''],
            ]],
            'Petunjuk' => [['Kolom', 'Isi'], [
                ['tipe', 'SINGLE (1 jawaban) / MULTI (>1 jawaban) / TRUE_FALSE'],
                ['tingkat', 'EASY / MEDIUM / HARD'],
                ['modul_ke', 'Nomor urut modul (opsional)'],
                ['jawaban', 'Huruf opsi benar, mis. A atau A,C. Untuk TRUE_FALSE isi BENAR atau SALAH'],
            ]],
        ]);
    }

    public function import(Request $request, Program $program)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,csv,txt|max:10240']);
        $file = $request->file('file');
        $rows = Spreadsheet::read($file->getRealPath(), $file->getClientOriginalExtension());
        $modules = $program->modules()->get()->values();
        $errors = [];
        $valid = [];
        foreach ($rows as $line => $r) {
            $type = strtoupper((string) ($r['tipe'] ?? ''));
            $diff = strtoupper((string) ($r['tingkat'] ?? 'MEDIUM'));
            $text = trim((string) ($r['pertanyaan'] ?? ''));
            $answer = strtoupper(str_replace(' ', '', (string) ($r['jawaban'] ?? '')));
            if (! array_key_exists($type, Question::TYPES)) { $errors[] = "Baris $line: tipe tidak valid"; continue; }
            if (! array_key_exists($diff, Question::DIFFICULTIES)) { $errors[] = "Baris $line: tingkat tidak valid"; continue; }
            if ($text === '') { $errors[] = "Baris $line: pertanyaan kosong"; continue; }
            if ($type === 'TRUE_FALSE') {
                if (! in_array($answer, ['BENAR', 'SALAH'], true)) { $errors[] = "Baris $line: jawaban harus BENAR/SALAH"; continue; }
                $options = ['Benar', 'Salah'];
                $correct = [$answer === 'BENAR' ? 0 : 1];
            } else {
                $options = [];
                foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $l) {
                    if (($v = trim((string) ($r["opsi_$l"] ?? ''))) !== '') $options[ord(strtoupper($l)) - 65] = $v;
                }
                $correct = array_map(fn ($c) => ord($c) - 65, array_filter(explode(',', $answer)));
                if (count($options) < 2) { $errors[] = "Baris $line: minimal 2 opsi"; continue; }
                if (! $correct || array_diff($correct, array_keys($options))) { $errors[] = "Baris $line: jawaban tidak cocok dengan opsi"; continue; }
                if ($type === 'SINGLE' && count($correct) !== 1) { $errors[] = "Baris $line: SINGLE harus tepat 1 jawaban"; continue; }
                if ($type === 'MULTI' && count($correct) < 2) { $errors[] = "Baris $line: MULTI harus ≥ 2 jawaban benar"; continue; }
            }
            $moduleNo = (int) ($r['modul_ke'] ?? 0);
            $valid[] = ['type' => $type, 'difficulty' => $diff, 'text' => $text, 'explanation' => $r['pembahasan'] ?? null,
                'module_id' => $moduleNo > 0 ? $modules[$moduleNo - 1]->id ?? null : null,
                'options' => array_values($options), 'correct' => array_values(array_map(fn ($c) => array_search($c, array_keys($options)), $correct))];
        }

        if ($errors) {
            return back()->withErrors(array_slice($errors, 0, 15))->with('warning', 'Tidak ada soal yang disimpan. Perbaiki baris yang error lalu unggah ulang.');
        }
        DB::transaction(function () use ($valid, $program) {
            foreach ($valid as $v) {
                $this->save(new Question(['bank_id' => $program->questionBank->id]), $v);
            }
        });
        AuditLogger::log('question.import', $program, null, ['count' => count($valid)]);

        return back()->with('success', count($valid).' soal berhasil diimpor.');
    }

    private function validated(Request $request, Program $program): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Question::TYPES))],
            'difficulty' => ['required', Rule::in(array_keys(Question::DIFFICULTIES))],
            'module_id' => ['nullable', Rule::exists('modules', 'id')->where('program_id', $program->id)],
            'text' => 'required|string|max:5000',
            'explanation' => 'nullable|string|max:5000',
            'options' => 'array',
            'options.*' => 'nullable|string|max:1000',
            'correct' => 'array',
        ], [], ['text' => 'pertanyaan']);

        if ($data['type'] === 'TRUE_FALSE') {
            $data['options'] = ['Benar', 'Salah'];
            $data['correct'] = [(int) ($request->input('tf_answer') === 'SALAH')];
        } else {
            $opts = [];
            $correct = [];
            foreach ($data['options'] ?? [] as $i => $text) {
                if (trim((string) $text) === '') continue;
                if (in_array((string) $i, array_map('strval', $data['correct'] ?? []), true)) $correct[] = count($opts);
                $opts[] = trim($text);
            }
            if (count($opts) < 2) abort(back()->withInput()->withErrors(['options' => 'Minimal 2 opsi jawaban.']));
            if ($data['type'] === 'SINGLE' && count($correct) !== 1) abort(back()->withInput()->withErrors(['correct' => 'Pilihan ganda harus memiliki tepat 1 jawaban benar.']));
            if ($data['type'] === 'MULTI' && count($correct) < 2) abort(back()->withInput()->withErrors(['correct' => 'Pilihan ganda kompleks harus memiliki ≥ 2 jawaban benar.']));
            $data['options'] = $opts;
            $data['correct'] = $correct;
        }

        return $data;
    }

    private function save(Question $q, array $data): Question
    {
        $q->fill(['type' => $data['type'], 'difficulty' => $data['difficulty'], 'text' => $data['text'],
            'explanation' => $data['explanation'] ?? null, 'module_id' => $data['module_id'] ?? null])->save();
        $q->options()->delete();
        foreach ($data['options'] as $i => $text) {
            $q->options()->create(['text' => $text, 'is_correct' => in_array($i, $data['correct'], true), 'sort_order' => $i]);
        }

        return $q;
    }
}
