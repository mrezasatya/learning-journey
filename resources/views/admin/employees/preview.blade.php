@extends('layouts.app')
@section('title', 'Pratinjau Impor')
@section('content')
@php $s = $preview['summary']; $labels = ['NEW'=>['Baru','badge-success'],'CHANGED'=>['Berubah','badge-warning'],'UNCHANGED'=>['Tetap','badge-neutral'],'ERROR'=>['Error','badge-danger']]; @endphp
<div class="page-head"><div><h1>Pratinjau impor</h1><p class="muted">{{ $filename }}</p></div></div>
<div class="grid grid-4">
    <div class="card stat"><div class="label">Baris baru</div><div class="value">{{ $s['NEW'] }}</div></div>
    <div class="card stat"><div class="label">Baris berubah</div><div class="value">{{ $s['CHANGED'] }}</div></div>
    <div class="card stat"><div class="label">Tidak berubah</div><div class="value">{{ $s['UNCHANGED'] }}</div></div>
    <div class="card stat"><div class="label">Baris error (dilewati)</div><div class="value" style="color:var(--danger)">{{ $s['ERROR'] }}</div></div>
</div>
<form method="post" action="{{ route('admin.employees.commit') }}" class="card">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    @if (count($preview['missing']))
        <div class="alert alert-info">
            {{ count($preview['missing']) }} karyawan aktif di sistem <b>tidak ada</b> di berkas ini. Secara default mereka <b>tidak</b> dinonaktifkan.
            <label class="check" style="margin-top:.5rem"><input type="checkbox" name="deactivate_missing" value="1"> Nonaktifkan karyawan yang tidak ada di berkas</label>
            <details><summary>Lihat daftar</summary><p>@foreach($preview['missing'] as $m){{ $m['nik'] }} {{ $m['full_name'] }}@if(!$loop->last), @endif @endforeach</p></details>
        </div>
    @endif
    <div class="row">
        <button class="btn" @disabled($s['NEW'] + $s['CHANGED'] === 0 && !count($preview['missing']))>Simpan impor ({{ $s['NEW'] + $s['CHANGED'] }} baris)</button>
        <a class="btn btn-secondary" href="{{ route('admin.employees.import') }}">Batal</a>
    </div>
</form>
<div class="card table-wrap">
<table>
    <thead><tr><th>Baris</th><th>Status</th><th>NIK</th><th>Nama</th><th>Keterangan</th></tr></thead>
    <tbody>
    @foreach ($preview['rows'] as $r)
        <tr class="{{ ['NEW'=>'row-new','CHANGED'=>'row-changed','ERROR'=>'row-error'][$r['status']] ?? '' }}">
            <td>{{ $r['line'] }}</td>
            <td><span class="badge {{ $labels[$r['status']][1] }}">{{ $labels[$r['status']][0] }}</span></td>
            <td>{{ $r['data']['nik'] ?? '' }}</td>
            <td>{{ $r['data']['full_name'] ?? '' }}</td>
            <td>
                @if ($r['errors']) {{ implode('; ', $r['errors']) }}
                @elseif ($r['diff'])
                    @foreach ($r['diff'] as $f => [$old, $new])<div><small>{{ $f }}:</small> {{ var_export($old, true) }} → <b>{{ var_export($new, true) }}</b></div>@endforeach
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection
