@extends('layouts.app')
@section('title', 'Karyawan')
@section('content')
<div class="page-head">
    <div><h1>Karyawan</h1><p class="muted">Data dari impor Excel/CSV (sebelum integrasi ESS).</p></div>
    <div class="row">
        <a class="btn btn-secondary" href="{{ route('admin.employees.export', request()->query()) }}">⬇ Ekspor Excel</a>
        <a class="btn" href="{{ route('admin.employees.import') }}">⬆ Impor data</a>
    </div>
</div>
<form class="filters" method="get">
    <div class="field"><label for="q">Cari NIK / nama</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
    <div class="field"><label for="division_id">Divisi</label><select id="division_id" name="division_id"><option value="">Semua</option>
        @foreach ($divisions as $d)<option value="{{ $d->id }}" @selected(request('division_id')==$d->id)>{{ $d->name }}</option>@endforeach</select></div>
    <div class="field"><label for="department_id">Departemen</label><select id="department_id" name="department_id"><option value="">Semua</option>
        @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id')==$d->id)>{{ $d->name }}</option>@endforeach</select></div>
    <div class="field"><label for="work_location_id">Lokasi</label><select id="work_location_id" name="work_location_id"><option value="">Semua</option>
        @foreach ($locations as $l)<option value="{{ $l->id }}" @selected(request('work_location_id')==$l->id)>{{ $l->name }}</option>@endforeach</select></div>
    <div class="field"><label for="active">Status akun</label><select id="active" name="active">
        <option value="1" @selected(request('active','1')==='1')>Aktif</option>
        <option value="0" @selected(request('active')==='0')>Nonaktif</option>
        <option value="all" @selected(request('active')==='all')>Semua</option></select></div>
    <button class="btn btn-secondary">Terapkan</button>
</form>
<div class="card table-wrap">
<table>
    <thead><tr><th>NIK</th><th>Nama</th><th>Jabatan</th><th>Gol.</th><th>Departemen</th><th>Lokasi</th><th>Status</th><th>Atasan</th></tr></thead>
    <tbody>
    @forelse ($employees as $e)
        <tr>
            <td><a href="{{ route('admin.employees.show', $e) }}">{{ $e->nik }}</a></td>
            <td>{{ $e->full_name }} @unless($e->is_active)<span class="badge badge-neutral">Nonaktif</span>@endunless</td>
            <td>{{ $e->position?->name }}</td>
            <td>{{ $e->grade?->code }}</td>
            <td>{{ $e->department?->name }}</td>
            <td>{{ $e->workLocation?->name }}</td>
            <td>{{ \App\Models\Employee::STATUSES[$e->employment_status] ?? '' }}</td>
            <td>{{ $e->supervisor?->full_name ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="empty">Tidak ada karyawan yang cocok.</td></tr>
    @endforelse
    </tbody>
</table>
{{ $employees->links() }}
</div>
@endsection
