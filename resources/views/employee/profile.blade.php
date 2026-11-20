@extends('layouts.app')
@section('title', 'Profil')
@section('content')
<div class="page-head"><div><h1>Profil</h1><p class="muted">Data berasal dari master karyawan. Bila ada yang salah, hubungi Admin L&amp;D/HR.</p></div></div>
<div class="card">
    <dl class="kv">
        <dt>NIK</dt><dd>{{ $employee->nik }}</dd>
        <dt>Nama</dt><dd>{{ $employee->full_name }}</dd>
        <dt>Email</dt><dd>{{ $employee->email ?? '—' }}</dd>
        <dt>Jabatan</dt><dd>{{ $employee->position?->name ?? '—' }}</dd>
        <dt>Golongan</dt><dd>{{ $employee->grade?->code ?? '—' }}</dd>
        <dt>Departemen</dt><dd>{{ $employee->department?->name ?? '—' }}</dd>
        <dt>Divisi</dt><dd>{{ $employee->department?->division?->name ?? '—' }}</dd>
        <dt>Lokasi kerja</dt><dd>{{ $employee->workLocation?->name ?? '—' }}</dd>
        <dt>Status</dt><dd>{{ \App\Models\Employee::STATUSES[$employee->employment_status] ?? $employee->employment_status }}</dd>
        <dt>Tanggal masuk</dt><dd>{{ $employee->join_date->translatedFormat('d F Y') }}</dd>
        <dt>Atasan langsung</dt><dd>{{ $employee->supervisor?->full_name ?? '—' }}</dd>
    </dl>
</div>
@endsection
