@extends('layouts.app')
@section('title', 'Laporan')
@section('content')
<div class="page-head"><div><h1>Laporan evaluasi</h1><p class="muted">Kirkpatrick level 1 (reaksi) & level 2 (pembelajaran) per program. Level 3–4 di luar lingkup fase 1.</p></div>
    <a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">Laporan kepatuhan →</a></div>
<form class="filters" method="get"><div class="field"><label>Periode</label><select name="period" onchange="this.form.submit()">
    @foreach (range(now()->year, now()->year - 3) as $y)<option @selected($year===$y)>{{ $y }}</option>@endforeach
    <option value="all" @selected($year===null)>Semua periode</option></select></div></form>
<div class="card table-wrap"><table>
    <thead><tr><th>Program</th><th class="num">Peserta</th><th class="num">Selesai</th><th class="num">Reaksi (1–5)</th><th class="num">Pre</th><th class="num">Post</th><th class="num">Gain</th><th class="num">Lulus perc. 1</th><th></th></tr></thead>
    <tbody>@foreach ($programs as $x)
        @php $l2 = $x['r']['l2']; $l1 = $x['r']['l1']; @endphp
        <tr><td>{{ $x['program']->code }}<br><small>{{ $x['program']->name }}</small></td>
            <td class="num">{{ $l2['participants'] }}</td><td class="num">{{ $l2['completed'] }}</td>
            <td class="num">{{ $l1->count() ? number_format($l1->avg('avg'), 2, ',', '') : '—' }}</td>
            <td class="num">{{ $l2['avg_pre'] ?? '—' }}</td><td class="num">{{ $l2['avg_post'] ?? '—' }}</td>
            <td class="num">{{ $l2['avg_gain'] !== null ? ($l2['avg_gain'] > 0 ? '+' : '').$l2['avg_gain'] : '—' }}</td>
            <td class="num">{{ $l2['first_pass_rate'] !== null ? $l2['first_pass_rate'].'%' : '—' }}</td>
            <td><a class="btn btn-sm btn-secondary" href="{{ route('admin.reports.evaluation', [$x['program'], 'period' => $year ?? 'all']) }}">Detail</a></td></tr>
    @endforeach</tbody>
</table></div>
@endsection
