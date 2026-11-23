<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Services\EvaluationReport;
use App\Support\Spreadsheet;
use Illuminate\Http\Request;

/** Laporan evaluasi Kirkpatrick L1 & L2 per program (US-26) + analisis butir (US-28). */
class ReportController extends Controller
{
    public function index(Request $request, EvaluationReport $report)
    {
        $year = $request->query('period') === 'all' ? null : (int) ($request->query('period') ?: now()->year);
        $programs = Program::where('version', 1)->orderBy('code')->get()
            ->map(fn ($p) => ['program' => $p, 'r' => $report->build($p, $year)]);

        return view('admin.reports.index', compact('programs', 'year'));
    }

    public function evaluation(Request $request, Program $program, EvaluationReport $report)
    {
        $year = $request->query('period') === 'all' ? null : (int) ($request->query('period') ?: now()->year);
        $r = $report->build($program, $year);

        if ($request->query('export')) {
            return Spreadsheet::download("evaluasi-{$program->code}-".($year ?? 'semua').'.xlsx', [
                'Level 2' => [['Ukuran', 'Nilai'], collect([
                    ['Peserta', $r['l2']['participants']], ['Selesai', $r['l2']['completed']], ['Rata-rata pre-test', $r['l2']['avg_pre']],
                    ['Rata-rata post-test (nilai akhir)', $r['l2']['avg_post']], ['Gain score rata-rata (post − pre)', $r['l2']['avg_gain']],
                    ['% lulus percobaan pertama', $r['l2']['first_pass_rate']], ['Rata-rata jumlah percobaan sampai lulus', $r['l2']['avg_attempts']],
                    ['Peserta yang mengulang materi', $r['l2']['relearn']]])],
                'Level 1' => [['Butir', 'n', 'Rata-rata', '1', '2', '3', '4', '5'], $r['l1']->map(fn ($i) => [$i['text'], $i['n'], $i['avg'], ...array_values($i['dist'])])],
                'Jawaban terbuka' => [['Tanggal', 'Jawaban'], $r['open']->map(fn ($o) => [$o['date']->format('Y-m-d'), $o['text']])],
                'Analisis butir' => [['ID', 'Soal', 'Tingkat', 'n', 'p (proporsi benar)', 'D (daya beda)', 'Catatan'], collect($r['items'])->map(fn ($i) => [$i['id'], $i['text'], $i['difficulty'], $i['n'], $i['p'], $i['d'], $i['flag']])],
            ]);
        }

        return view('admin.reports.evaluation', ['program' => $program, 'r' => $r, 'year' => $year]);
    }
}
