@extends('layouts.app')
@section('title', $employee->full_name)
@section('content')
<div class="page-head">
    <div><h1>{{ $employee->full_name }}</h1><p class="muted">NIK {{ $employee->nik }} @unless($employee->is_active)· <span class="badge badge-neutral">Nonaktif</span>@endunless</p></div>
    <div class="row">
        <a class="btn btn-secondary" href="{{ route('admin.employees.index') }}">← Daftar</a>
        <a class="btn" href="{{ route('admin.employees.edit', $employee) }}">Ubah data</a>
    </div>
</div>
<div class="grid grid-2">
    <div class="card">
        <h2>Data karyawan</h2>
        <dl class="kv">
            <dt>Jabatan</dt><dd>{{ $employee->position?->name ?? '—' }} ({{ $employee->position?->level }})</dd>
            <dt>Golongan</dt><dd>{{ $employee->grade?->code ?? '—' }}</dd>
            <dt>Departemen</dt><dd>{{ $employee->department?->name ?? '—' }}</dd>
            <dt>Divisi</dt><dd>{{ $employee->department?->division?->name ?? '—' }}</dd>
            <dt>Lokasi</dt><dd>{{ $employee->workLocation?->name ?? '—' }}</dd>
            <dt>Status</dt><dd>{{ \App\Models\Employee::STATUSES[$employee->employment_status] ?? '' }}</dd>
            <dt>Tanggal masuk</dt><dd>{{ $employee->join_date?->format('d/m/Y') }}</dd>
            <dt>Tanggal berakhir</dt><dd>{{ $employee->end_date?->format('d/m/Y') ?? '—' }}</dd>
            <dt>Email</dt><dd>{{ $employee->email ?? '— (reset password via Admin)' }}</dd>
            <dt>Atasan</dt><dd>@if($employee->supervisor)<a href="{{ route('admin.employees.show', $employee->supervisor) }}">{{ $employee->supervisor->full_name }}</a>@else — @endif</dd>
            <dt>Login terakhir</dt><dd>{{ $employee->user?->last_login_at?->format('d/m/Y H:i') ?? 'Belum pernah' }}</dd>
        </dl>
    </div>
    <div class="card">
        <h2>Anggota tim langsung ({{ $subordinates->count() }})</h2>
        @forelse ($subordinates as $s)
            <div><a href="{{ route('admin.employees.show', $s) }}">{{ $s->full_name }}</a> <small>{{ $s->nik }}</small></div>
        @empty
            <p class="muted">Tidak memiliki anggota tim.</p>
        @endforelse
    </div>
</div>
<div class="card table-wrap">
    <h2>Penugasan training</h2>
    <table>
        <thead><tr><th>Program</th><th>Periode</th><th>Status</th><th>Tenggat</th><th>Nilai</th><th></th></tr></thead>
        <tbody>
        @forelse ($employee->assignments as $a)
            <tr>
                <td>{{ $a->program->code }} — {{ $a->program->name }}</td>
                <td>{{ $a->periodLabel() }}</td>
                <td><span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span></td>
                <td>{{ $a->due_at->format('d/m/Y') }}</td>
                <td>{{ $a->final_score ?? '—' }}</td>
                <td>@if(Route::has('admin.assignments.show'))<a href="{{ route('admin.assignments.show', $a) }}">Detail</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Belum ada penugasan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
