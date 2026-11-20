@extends('layouts.app')
@section('title', 'Tim Saya')
@section('content')
<div class="page-head">
    <div><h1>Tim Saya</h1><p class="muted">Status training wajib anggota tim {{ $scope === 'semua' ? 'langsung & tidak langsung' : 'langsung' }}. Anda menerima salinan pengingat mulai H-30 dan eskalasi saat overdue.</p></div>
    <div class="row">
        @if ($canIndirect || $scope === 'semua')
            <a class="btn btn-secondary" href="{{ route('team.index', array_merge(request()->except('lingkup'), $scope === 'semua' ? [] : ['lingkup' => 'semua'])) }}">{{ $scope === 'semua' ? 'Hanya tim langsung' : 'Termasuk tim tidak langsung' }}</a>
        @endif
        <a class="btn btn-secondary" href="{{ route('team.export', request()->query()) }}">⬇ Ekspor Excel</a>
    </div>
</div>
<div class="grid grid-4">
    <div class="card stat"><div class="label">Anggota tim</div><div class="value">{{ $summary['members'] }}</div></div>
    <div class="card stat"><div class="label">Completion rate tahun ini</div><div class="value">{{ $summary['rate'] }}%</div></div>
    <div class="card stat"><div class="label">Overdue</div><a class="value" style="{{ $summary['overdue'] ? 'color:var(--danger)' : '' }}" href="{{ route('team.index', array_merge(request()->query(), ['status' => 'OVERDUE'])) }}">{{ $summary['overdue'] }}</a></div>
    <div class="card stat"><div class="label">Jatuh tempo ≤ 30 hari</div><div class="value">{{ $summary['due30'] }}</div></div>
</div>
<form class="filters" method="get">
    @if($scope==='semua')<input type="hidden" name="lingkup" value="semua">@endif
    <div class="field"><label>Cari anggota</label><input type="search" name="q" value="{{ request('q') }}"></div>
    <div class="field"><label>Program</label><select name="program_id"><option value="">Semua</option>@foreach($programs as $p)<option value="{{ $p->id }}" @selected(request('program_id')==$p->id)>{{ $p->code }} — {{ $p->name }}</option>@endforeach</select></div>
    <div class="field"><label>Status</label><select name="status"><option value="">Semua</option>
        <option value="open" @selected(request('status')==='open')>Belum selesai</option>
        @foreach(\App\Models\Assignment::STATUSES as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach</select></div>
    <label class="check" style="align-self:center"><input type="checkbox" name="periode_lalu" value="1" @checked(request('periode_lalu'))> Termasuk periode lalu</label>
    <button class="btn btn-secondary">Terapkan</button>
</form>
<div class="card table-wrap">
<table>
    <thead><tr><th>Anggota</th><th>Program</th><th>Status</th><th>Progres</th><th>Tenggat</th><th class="num">Post-test</th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
        @php $a = $r['a']; $d = $a->daysLeft(); @endphp
        <tr>
            <td>{{ $a->employee->full_name }}<br><small>{{ $a->employee->nik }} · {{ $a->employee->position?->name }}</small></td>
            <td>{{ $a->program->name }}<br><small>{{ $a->program->code }} · {{ $a->periodLabel() }}</small></td>
            <td><span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span></td>
            <td style="min-width:110px"><div class="progress"><span style="width:{{ $r['percent'] }}%"></span></div><small>{{ $r['percent'] }}%</small></td>
            <td class="nowrap">{{ $a->due_at->format('d/m/Y') }}@if($d !== null)<br><small class="{{ $d < 0 ? 'text-danger' : '' }}">{{ $d < 0 ? abs($d).' hari lewat' : $d.' hari lagi' }}</small>@endif</td>
            <td class="num">{{ $a->final_score ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">Tidak ada data.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
