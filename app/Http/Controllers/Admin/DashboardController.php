<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Program;
use App\Services\ComplianceReport;
use App\Support\Spreadsheet;
use Illuminate\Http\Request;

/** Dashboard kepatuhan training wajib (US-25). */
class DashboardController extends Controller
{
    public function __invoke(Request $request, ComplianceReport $report)
    {
        [$year, $programId] = $this->filters($request);
        $rows = $report->rows($year, $programId);
        $dims = ['program' => 'Program', 'division' => 'Divisi', 'department' => 'Departemen', 'location' => 'Lokasi kerja', 'location_type' => 'Tipe lokasi (HO/Plant/Site)'];

        if ($request->query('export')) {
            $sheets = ['Ringkasan' => [['Indikator', 'Nilai', 'Target'], collect($report->indicators($rows, $year))->map(fn ($i) => [$i[0], $i[1], $i[2]])]];
            foreach ($dims as $k => $label) {
                $sheets[$label] = [[$label, 'Penugasan', 'Selesai', 'Overdue', 'Completion %'],
                    $report->breakdown($rows, $k)->map(fn ($r) => [$r['label'], $r['total'], $r['done'], $r['overdue'], $r['rate']])];
            }

            return Spreadsheet::download("kepatuhan-$year.xlsx", $sheets);
        }

        return view('admin.dashboard', [
            'year' => $year, 'programId' => $programId,
            'summary' => $report->summary($rows),
            'indicators' => $report->indicators($rows, $year),
            'breakdowns' => collect($dims)->map(fn ($label, $k) => ['label' => $label, 'rows' => $report->breakdown($rows, $k)]),
            'programs' => Program::whereIn('status', ['PUBLISHED', 'ARCHIVED'])->orderBy('code')->get(),
            'years' => Assignment::where('period_year', '>', 0)->distinct()->orderByDesc('period_year')->pluck('period_year')->push(now()->year)->unique()->sortDesc()->values(),
            'recommendations' => \App\Models\DisciplinaryFollowup::where('status', 'RECOMMENDED')->count(),
        ]);
    }

    private function filters(Request $request): array
    {
        return [(int) ($request->query('period') ?: now()->year), $request->query('program_id') ? (int) $request->query('program_id') : null];
    }
}
