<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SurveyTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Template survei reaksi (BR-31). Butir Likert 1–5 + pertanyaan terbuka. */
class SurveyTemplateController extends Controller
{
    public function index()
    {
        return view('admin.surveys.index', ['templates' => SurveyTemplate::withCount('responses')->orderBy('name')->get()]);
    }

    public function edit(?SurveyTemplate $template = null)
    {
        return view('admin.surveys.form', ['template' => $template ?? new SurveyTemplate(['items' => []])]);
    }

    public function save(Request $request, ?SurveyTemplate $template = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'likert' => 'required|string|max:5000',
            'open' => 'nullable|string|max:5000',
        ]);
        $items = [];
        foreach (array_filter(array_map('trim', preg_split('/\r?\n/', $data['likert']))) as $i => $line) {
            $items[] = ['code' => 'q'.($i + 1).'_'.Str::slug(Str::words($line, 2, ''), '_'), 'type' => 'LIKERT', 'text' => $line];
        }
        foreach (array_filter(array_map('trim', preg_split('/\r?\n/', (string) $data['open']))) as $i => $line) {
            $items[] = ['code' => 'open'.($i + 1), 'type' => 'TEXT', 'text' => $line];
        }
        $template ??= new SurveyTemplate;
        if ($template->exists && $template->responses()->exists()) {
            return back()->withErrors(['Template sudah memiliki jawaban. Buat template baru agar laporan lama tetap konsisten.']);
        }
        $template->fill(['name' => $data['name'], 'items' => $items, 'is_default' => $request->boolean('is_default')])->save();
        if ($template->is_default) {
            SurveyTemplate::where('id', '!=', $template->id)->update(['is_default' => false]);
        }
        AuditLogger::log('survey_template.save', $template, null, $template->toArray());

        return redirect()->route('admin.surveys.index')->with('success', 'Template survei disimpan.');
    }
}
