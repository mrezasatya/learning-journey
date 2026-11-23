@extends('layouts.app')
@section('title', 'Dashboard Kepatuhan')
@section('content')
@php $base = ['period' => $year, 'program_id' => $programId]; @endphp
<div class="page-head">
    <div><h1>Dashboard Kepatuhan</h1><p class="muted">Training wajib periode {{ $year }} · karyawan aktif · tidak termasuk yang dikecualikan</p></div>
    <div class="row">
        <a class="btn btn-secondary" href="{{ route('admin.dashboard', $base + ['export' => 1]) }}">⬇ Ekspor Excel</a>
        @if (auth()->user()->employee)<a class="btn btn-secondary" href="{{ route('employee.home') }}">Beranda karyawan saya</a>@endif
    </div>
</div>
<form class="filters" method="get">
    <div class="field"><label>Periode</label><select name="period">@foreach($years as $y)<option @selected($y==$year)>{{ $y }}</option>@endforeach</select></div>
    <div class="field"><label>Program</label><select name="program_id"><option value="">Semua program</option>@foreach($programs as $p)<option value="{{ $p->id }}" @selected($programId==$p->id)>{{ $p->code }} v{{ $p->version }} — {{ $p->name }}</option>@endforeach</select></div>
    <button class="btn btn-secondary">Terapkan</button>
</form>

<div class="grid grid-4">
    <div class="card stat"><div class="label">% selesai keseluruhan</div>
        <a class="value" href="{{ route('admin.assignments.index', $base + ['status' => 'done']) }}">{{ rtrim(rtrim(number_format($summary['rate'], 1, ',', ''), '0'), ',') }}%</a>
        <small>{{ $summary['done'] }} dari {{ $summary['total'] }} penugasan</small>
        <div class="progress" style="margin-top:.4rem"><span style="width:{{ $summary['rate'] }}%"></span></div></div>
    <div class="card stat"><div class="label">Overdue</div>
        <a class="value" style="{{ $summary['overdue'] ? 'color:var(--danger)' : '' }}" href="{{ route('admin.assignments.index', $base + ['status' => 'OVERDUE']) }}">{{ $summary['overdue'] }}</a>
        <small>{{ $recommendations }} rekomendasi teguran menunggu verifikasi · <a href="{{ route('admin.overdue.index') }}">lihat</a></small></div>
    <div class="card stat"><div class="label">Jatuh tempo ≤ 30 hari</div>
        <a class="value" href="{{ route('admin.assignments.index', $base + ['status' => 'due30']) }}">{{ $summary['due30'] }}</a><small>belum selesai</small></div>
    <div class="card stat"><div class="label">Belum mulai / berjalan</div>
        <div class="value"><a href="{{ route('admin.assignments.index', $base + ['status' => 'NOT_STARTED']) }}" style="text-decoration:none">{{ $summary['not_started'] }}</a><small> / </small><a href="{{ route('admin.assignments.index', $base + ['status' => 'IN_PROGRESS']) }}" style="text-decoration:none">{{ $summary['in_progress'] }}</a></div></div>
</div>

<div class="card">
    <h2>Indikator keberhasilan (Brief 1.3)</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Indikator</th><th class="num">Capaian</th><th class="num">Target</th><th>Status</th></tr></thead>
        <tbody>@foreach ($indicators as [$name, $value, $target, $ok])
            <tr><td>{{ $name }}</td><td class="num"><b>{{ $value }}</b></td><td class="num">{{ $target }}</td>
                <td>@if($ok === null)<span class="badge badge-neutral">Dipantau</span>@elseif($ok)<span class="badge badge-success">✓ Tercapai</span>@else<span class="badge badge-warning">! Belum tercapai</span>@endif</td></tr>
        @endforeach</tbody>
    </table></div>
    @if ($year >= now()->year)<small class="muted">Periode {{ $year }} masih berjalan — capaian "sebelum tenggat" final dinilai setelah 31 Desember. </small>@endif
    <small class="muted">Indikator "waktu admin untuk rekap bulanan" diukur di luar sistem (bandingkan dengan proses manual sebelum BLJ).</small>
</div>

<div class="grid grid-2">
    @foreach ($breakdowns as $key => $b)
        <div class="card">
            <h2>Completion rate per {{ lcfirst($b['label']) }}</h2>
            @if ($b['rows']->isEmpty())<p class="muted">Belum ada data.</p>
            @else @include('partials.bars', ['rows' => $b['rows'], 'target' => 95, 'linkBase' => $base, 'title' => 'Completion rate per '.$b['label']]) @endif
        </div>
    @endforeach
</div>
<p class="muted">Klik angka atau batang untuk melihat daftar karyawan. Diurutkan dari completion rate terendah agar area yang perlu perhatian muncul di atas.</p>
@endsection
