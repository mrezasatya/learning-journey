@extends('layouts.app')
@section('title', 'Penugasan')
@section('content')
<div class="page-head">
    <div><h1>Penugasan</h1><p class="muted">Seluruh penugasan training wajib karyawan aktif.</p></div>
    <div class="row">
        <a class="btn btn-secondary" href="{{ route('admin.assignments.export', request()->query()) }}">⬇ Ekspor Excel</a>
        <a class="btn" href="{{ route('admin.assignments.create') }}">+ Penugasan manual</a>
    </div>
</div>
<form class="filters" method="get">
    <div class="field"><label>Cari NIK/nama</label><input type="search" name="q" value="{{ request('q') }}"></div>
    <div class="field"><label>Program</label><select name="program_id"><option value="">Semua</option>@foreach($programs as $p)<option value="{{ $p->id }}" @selected(request('program_id')==$p->id)>{{ $p->code }} v{{ $p->version }}</option>@endforeach</select></div>
    <div class="field"><label>Status</label><select name="status"><option value="">Semua</option>
        <option value="open" @selected(request('status')==='open')>Belum selesai</option>
        <option value="due30" @selected(request('status')==='due30')>Jatuh tempo ≤ 30 hari</option>
        <option value="done" @selected(request('status')==='done')>Selesai (tepat/terlambat)</option>
        @foreach(\App\Models\Assignment::STATUSES as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach</select></div>
    <div class="field"><label>Periode</label><select name="period"><option value="">Semua</option>@foreach($years as $y)<option @selected(request('period')==$y)>{{ $y }}</option>@endforeach</select></div>
    <div class="field"><label>Divisi</label><select name="division_id"><option value="">Semua</option>@foreach($divisions as $d)<option value="{{ $d->id }}" @selected(request('division_id')==$d->id)>{{ $d->name }}</option>@endforeach</select></div>
    <div class="field"><label>Departemen</label><select name="department_id"><option value="">Semua</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id')==$d->id)>{{ $d->name }}</option>@endforeach</select></div>
    <div class="field"><label>Lokasi</label><select name="work_location_id"><option value="">Semua</option>@foreach($locations as $l)<option value="{{ $l->id }}" @selected(request('work_location_id')==$l->id)>{{ $l->name }}</option>@endforeach</select></div>
    <div class="field"><label>Tipe lokasi</label><select name="location_type"><option value="">Semua</option>@foreach(\App\Models\WorkLocation::TYPES as $k=>$v)<option value="{{ $k }}" @selected(request('location_type')===$k)>{{ $v }}</option>@endforeach</select></div>
    <button class="btn btn-secondary">Terapkan</button>
</form>
<div class="card table-wrap">
<table>
    <thead><tr><th>Karyawan</th><th>Program</th><th>Periode</th><th>Status</th><th>Tenggat</th><th class="num">Nilai</th><th>Sumber</th></tr></thead>
    <tbody>
    @forelse ($assignments as $a)
        <tr>
            <td><a href="{{ route('admin.assignments.show', $a) }}">{{ $a->employee->full_name }}</a><br><small>{{ $a->employee->nik }} · {{ $a->employee->department?->name }} · {{ $a->employee->workLocation?->name }}</small></td>
            <td>{{ $a->program->code }}<br><small>{{ $a->program->name }}</small></td>
            <td>{{ $a->periodLabel() }}</td>
            <td><span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span></td>
            <td class="nowrap">{{ $a->due_at->format('d/m/Y') }}
                @if(($d = $a->daysLeft()) !== null)<br><small class="{{ $d < 0 ? 'text-danger' : '' }}">{{ $d < 0 ? abs($d).' hari lewat' : $d.' hari lagi' }}</small>@endif</td>
            <td class="num">{{ $a->final_score ?? '—' }}</td>
            <td>{{ $a->source === 'RULE' ? 'Aturan' : 'Manual' }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="empty">Tidak ada penugasan yang cocok.</td></tr>
    @endforelse
    </tbody>
</table>
{{ $assignments->links() }}
</div>
@endsection
