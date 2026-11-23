<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Program;
use App\Services\AuditLogger;
use App\Services\MediaService;
use App\Support\Html;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Modul & materi (US-06 AC2, US-07, US-08). */
class ContentController extends Controller
{
    // ---------- Modul ----------

    public function storeModule(Request $request, Program $program)
    {
        $this->guardEditable($program);
        $data = $request->validate(['title' => 'required|string|max:255']);
        $module = $program->modules()->create($data + ['sort_order' => ($program->modules()->max('sort_order') ?? 0) + 1]);
        AuditLogger::log('module.create', $module, null, $module->toArray());

        return back()->with('success', 'Modul ditambahkan.');
    }

    public function updateModule(Request $request, Module $module)
    {
        $this->guardEditable($module->program);
        $original = $module->getOriginal();
        $module->update($request->validate(['title' => 'required|string|max:255']));
        AuditLogger::changes('module.update', $module, $original);

        return back()->with('success', 'Modul diperbarui.');
    }

    public function destroyModule(Module $module)
    {
        $this->guardEditable($module->program);
        AuditLogger::log('module.delete', $module, $module->toArray());
        $module->delete();

        return back()->with('success', 'Modul dihapus.');
    }

    public function moveModule(Module $module, string $dir)
    {
        $this->guardEditable($module->program);
        $this->swap($module->program->modules()->get(), $module, $dir);

        return back();
    }

    // ---------- Materi ----------

    public function createLesson(Module $module)
    {
        $this->guardEditable($module->program);

        return view('admin.programs.lesson-form', ['module' => $module, 'lesson' => new Lesson(['type' => 'VIDEO'])]);
    }

    public function storeLesson(Request $request, Module $module, MediaService $media)
    {
        $this->guardEditable($module->program);
        $data = $this->validatedLesson($request, null);
        if ($request->hasFile('file')) {
            $file = $media->store($request->file('file'));
            $data['file_id'] = $file->id;
            $data['type'] = MediaService::lessonTypeFor($request->file('file')->getClientOriginalExtension()) ?? $data['type'];
        } elseif ($data['type'] !== 'TEXT') {
            return back()->withInput()->withErrors(['file' => 'Unggah berkas untuk materi bertipe '.Lesson::TYPES[$data['type']].'.']);
        }
        $lesson = $module->lessons()->create($data + ['sort_order' => ($module->lessons()->max('sort_order') ?? 0) + 1]);
        AuditLogger::log('lesson.create', $lesson, null, $lesson->only(['title', 'type', 'file_id']));

        $msg = $lesson->file && ! $lesson->file->isReady()
            ? 'Materi tersimpan. Berkas sedang diproses di latar belakang (konversi resolusi/halaman).'
            : 'Materi tersimpan.';

        return redirect()->route('admin.programs.show', $module->program_id)->with('success', $msg);
    }

    public function editLesson(Lesson $lesson)
    {
        $this->guardEditable($lesson->module->program);

        return view('admin.programs.lesson-form', ['module' => $lesson->module, 'lesson' => $lesson]);
    }

    public function updateLesson(Request $request, Lesson $lesson, MediaService $media)
    {
        $this->guardEditable($lesson->module->program);
        $original = $lesson->getOriginal();
        $data = $this->validatedLesson($request, $lesson);
        if ($request->hasFile('file')) {
            $data['file_id'] = $media->store($request->file('file'))->id;
            $data['type'] = MediaService::lessonTypeFor($request->file('file')->getClientOriginalExtension()) ?? $data['type'];
        }
        $lesson->update($data);
        AuditLogger::changes('lesson.update', $lesson, $original);

        return redirect()->route('admin.programs.show', $lesson->module->program_id)->with('success', 'Materi diperbarui.');
    }

    public function destroyLesson(Lesson $lesson)
    {
        $this->guardEditable($lesson->module->program);
        AuditLogger::log('lesson.delete', $lesson, $lesson->only(['title', 'type']));
        $lesson->delete();

        return back()->with('success', 'Materi dihapus.');
    }

    public function moveLesson(Lesson $lesson, string $dir)
    {
        $this->guardEditable($lesson->module->program);
        $this->swap($lesson->module->lessons()->get(), $lesson, $dir);

        return back();
    }

    public function retryProcessing(Lesson $lesson)
    {
        abort_unless($lesson->file, 404);
        $lesson->file->update(['processing_status' => 'PENDING']);
        \App\Jobs\ProcessMediaJob::dispatch($lesson->file_id);

        return back()->with('success', 'Pemrosesan berkas dijadwalkan ulang.');
    }

    /** Status pemrosesan (dipanggil berkala oleh halaman editor). */
    public function processingStatus(Program $program)
    {
        return response()->json($program->lessons()->with('file')->get()->filter->file
            ->mapWithKeys(fn ($l) => [$l->id => $l->file->processing_status]));
    }

    private function validatedLesson(Request $request, ?Lesson $lesson): array
    {
        $maxKb = (int) Settings::get('upload_max_mb') * 1024;
        $exts = implode(',', [...MediaService::VIDEO_MIMES, ...MediaService::DOC_MIMES, ...MediaService::SLIDE_MIMES, ...MediaService::IMAGE_MIMES]);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'type' => ['required', Rule::in(array_keys(Lesson::TYPES))],
            'body' => 'nullable|string|max:200000',
            'min_seconds' => 'nullable|integer|min:0|max:3600',
            'completion_threshold' => 'nullable|numeric|min:0.5|max:1',
            'file' => "nullable|file|max:$maxKb|mimes:$exts",
        ], ['file.max' => 'Ukuran berkas melebihi batas '.Settings::get('upload_max_mb').' MB.']);
        unset($data['file']);
        $data['body'] = Html::clean($data['body'] ?? null) ?: null;
        if ($data['type'] === 'TEXT' && ! $data['body']) {
            abort(back()->withInput()->withErrors(['body' => 'Isi artikel wajib diisi.']));
        }
        $data['completion_threshold'] = $data['completion_threshold'] ?? 0.9;

        return $data;
    }

    private function swap($items, $current, string $dir): void
    {
        $items = $items->values();
        $i = $items->search(fn ($x) => $x->id === $current->id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($i === false || ! isset($items[$j])) {
            return;
        }
        foreach ($items as $k => $item) {
            $item->update(['sort_order' => $k + 1]);
        }
        [$a, $b] = [$items[$i], $items[$j]];
        [$sa, $sb] = [$a->sort_order, $b->sort_order];
        $a->update(['sort_order' => $sb]);
        $b->update(['sort_order' => $sa]);
    }

    /** Program terbit yang sudah berpeserta tidak boleh diubah langsung → buat versi baru (BR-09). */
    private function guardEditable(Program $program): void
    {
        if ($program->status !== 'DRAFT' && $program->assignments()->exists()) {
            abort(back()->withErrors(['Program ini sudah berjalan. Untuk mengubah materi, buat versi baru (BR-09).']));
        }
    }
}
