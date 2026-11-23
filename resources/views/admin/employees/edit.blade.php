@extends('layouts.app')
@section('title', 'Ubah Karyawan')
@section('content')
<div class="page-head"><div><h1>Ubah data: {{ $employee->full_name }}</h1><p class="muted">NIK {{ $employee->nik }} tidak dapat diubah (kunci data). Perubahan tercatat di audit log dan aturan penugasan dievaluasi ulang.</p></div></div>
<form method="post" action="{{ route('admin.employees.update', $employee) }}" class="card">
    @csrf @method('put')
    <div class="form-grid">
        <div class="field"><label>Nama lengkap</label><input name="full_name" value="{{ old('full_name', $employee->full_name) }}" required></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email', $employee->email) }}"></div>
        <div class="field"><label>No. HP</label><input name="phone" value="{{ old('phone', $employee->phone) }}"></div>
        <div class="field"><label>Jabatan</label><select name="position_code">@foreach($positions as $p)<option value="{{ $p->code }}" @selected(old('position_code', $employee->position?->code)===$p->code)>{{ $p->name }}</option>@endforeach</select></div>
        <div class="field"><label>Golongan</label><select name="grade_code">@foreach($grades as $g)<option value="{{ $g->code }}" @selected(old('grade_code', $employee->grade?->code)===$g->code)>{{ $g->code }}</option>@endforeach</select></div>
        <div class="field"><label>Departemen</label><select name="department_code">@foreach($departments as $d)<option value="{{ $d->code }}" @selected(old('department_code', $employee->department?->code)===$d->code)>{{ $d->name }}</option>@endforeach</select></div>
        <div class="field"><label>Lokasi kerja</label><select name="work_location_code">@foreach($locations as $l)<option value="{{ $l->code }}" @selected(old('work_location_code', $employee->workLocation?->code)===$l->code)>{{ $l->name }}</option>@endforeach</select></div>
        <div class="field"><label>Status karyawan</label><select name="employment_status">@foreach(\App\Models\Employee::STATUSES as $k=>$v)<option value="{{ $k }}" @selected(old('employment_status', $employee->employment_status)===$k)>{{ $v }}</option>@endforeach</select></div>
        <div class="field"><label>NIK atasan langsung</label><input name="supervisor_nik" value="{{ old('supervisor_nik', $employee->supervisor_nik) }}"></div>
        <div class="field"><label>Tanggal masuk</label><input type="date" name="join_date" value="{{ old('join_date', $employee->join_date?->format('Y-m-d')) }}" required></div>
        <div class="field"><label>Tanggal berakhir</label><input type="date" name="end_date" value="{{ old('end_date', $employee->end_date?->format('Y-m-d')) }}"></div>
        <div class="field"><label>ID ESS (external_ref)</label><input name="external_ref" value="{{ old('external_ref', $employee->external_ref) }}"></div>
    </div>
    <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $employee->is_active))> Karyawan aktif (nonaktif = tidak dapat login, riwayat tetap tersimpan)</label></div>
    <div class="row"><button class="btn">Simpan</button><a class="btn btn-secondary" href="{{ route('admin.employees.show', $employee) }}">Batal</a></div>
</form>
@endsection
