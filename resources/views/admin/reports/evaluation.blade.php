@extends('layouts.app')
@section('title', 'Evaluasi '.$program->code)
@section('content')
@include('admin.programs._tabs')
@php $l2 = $r['l2']; $q = ['period' => $year ?? 'all']; @endphp
<div class="row" style="margin-bottom:1rem">
    <form method="get" class="row"><label style="margin:0">Periode</label><select name="period" onchange="this.form.submit()" style="width:auto">
        @foreach (range(now()->year, now()->year - 3) as $y)<option @selected($year===$y)>{{ $y }}</option>@endforeach<option value="all" @selected($year===null)>Semua</option></select></form>
    <a class="btn btn-secondary right" href="{{ route('admin.reports.evaluation', ['program' => $program] + $q + ['export' => 1]) }}">⬇ Ekspor Excel</a>
</div>

<h2>Level 2 — Pembelajaran</h2>
<div class="grid grid-4">
    <div class="card stat"><div class="label">Rata-rata pre-test</div><div class="value">{{ $l2['avg_pre'] ?? '—' }}</div></div>
    <div class="card stat"><div class="label">Rata-rata post-test (nilai akhir)</div><div class="value">{{ $l2['avg_post'] ?? '—' }}</div></div>
    <div class="card stat"><div class="label">Gain score (post − pre)</div><div class="value">{{ $l2['avg_gain'] !== null ? ($l2['avg_gain'] > 0 ? '+' : '').$l2['avg_gain'] : '—' }}</div><small>{{ $l2['pairs'] }} peserta berpasangan</small></div>
    <div class="card stat"><div class="label">Lulus percobaan pertama</div><div class="value">{{ $l2['first_pass_rate'] !== null ? $l2['first_pass_rate'].'%' : '—' }}</div><small>Target ≥ 80%</small></div>
    <div class="card stat"><div class="label">Rata-rata percobaan sampai lulus</div><div class="value">{{ $l2['avg_attempts'] ?? '—' }}</div></div>
    <div class="card stat"><div class="label">Peserta / selesai</div><div class="value">{{ $l2['participants'] }}<small> / {{ $l2['completed'] }}</small></div></div>
    <div class="card stat"><div class="label">Mengulang materi (BR-24)</div><div class="value">{{ $l2['relearn'] }}</div></div>
</div>
<p class="muted">Catatan interpretasi: gain score menunjukkan peningkatan pengetahuan selama program, <b>bukan</b> bukti perubahan perilaku kerja (level 3). Karena pre & post memakai bank soal yang sama tetapi soal acak, perbandingan bersifat indikatif pada level kelompok, bukan individu.</p>

<h2>Level 1 — Reaksi ({{ $r['responses'] }} responden)</h2>
<div class="card table-wrap">
    @if ($r['l1']->isEmpty())<p class="muted">Belum ada survei.</p>@else
    <table><thead><tr><th>Butir</th><th class="num">n</th><th class="num">Rata-rata</th><th>Sebaran 1 → 5</th></tr></thead><tbody>
        @foreach ($r['l1'] as $i)
            <tr><td>{{ $i['text'] }}</td><td class="num">{{ $i['n'] }}</td><td class="num"><b>{{ number_format($i['avg'], 2, ',', '') }}</b></td>
                <td style="min-width:220px">
                    <div style="display:flex;gap:2px;height:14px" title="@foreach($i['dist'] as $k=>$v){{ $k }}: {{ $v }} @endforeach">
                    @foreach ($i['dist'] as $k => $v)
                        @if($v)<span style="flex:{{ $v }};border-radius:3px;background:{{ ['','#D9E8F0','#B3D1E0','#80B2CC','#33809F','#003854'][$k] }}"></span>@endif
                    @endforeach</div>
                    <small class="muted">@foreach($i['dist'] as $k=>$v){{ $k }}={{ $v }} @endforeach</small></td></tr>
        @endforeach
    </tbody></table>@endif
</div>
<div class="card"><h3>Jawaban terbuka</h3>
    @forelse ($r['open']->take(30) as $o)<p>“{{ $o['text'] }}” <small class="muted">— {{ $o['date']->format('d/m/Y') }}</small></p>@empty<p class="muted">Belum ada.</p>@endforelse</div>

<h2>Analisis butir soal</h2>
<div class="card table-wrap">
    <p class="muted">p = proporsi peserta menjawab benar (tingkat kesulitan aktual). D = daya beda (kelompok atas 27% − bawah 27%); D &lt; 0,20 menandakan soal kurang membedakan peserta yang paham dan yang tidak.</p>
    @if (! $r['items'])<p class="muted">Minimal 10 percobaan tes diperlukan.</p>@else
    <table><thead><tr><th>ID</th><th>Soal</th><th>Tingkat (rencana)</th><th class="num">n</th><th class="num">p</th><th class="num">D</th><th>Catatan</th></tr></thead><tbody>
        @foreach ($r['items'] as $i)
            <tr><td>{{ $i['id'] }}</td><td>{{ \Illuminate\Support\Str::limit($i['text'], 90) }}</td><td>{{ \App\Models\Question::DIFFICULTIES[$i['difficulty']] }}</td>
                <td class="num">{{ $i['n'] }}</td><td class="num">{{ number_format($i['p'], 2, ',', '') }}</td><td class="num">{{ number_format($i['d'], 2, ',', '') }}</td>
                <td>@if($i['flag'])<span class="badge badge-warning">{{ $i['flag'] }}</span>@endif</td></tr>
        @endforeach
    </tbody></table>@endif
</div>
@endsection
