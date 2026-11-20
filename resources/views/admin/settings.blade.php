@extends('layouts.app')
@section('title', 'Pengaturan')
@section('content')
<div class="page-head"><div><h1>Pengaturan sistem</h1><p class="muted">Nilai default aturan bisnis. Perubahan tercatat di audit log. Pengaturan tes yang sudah dibuat per program tidak berubah otomatis.</p></div></div>
<form method="post" action="{{ route('admin.settings.update') }}" class="card" style="max-width:760px">
    @csrf @method('put')
    <div class="form-grid">
    @foreach ($fields as $key => [$label, $type])
        <div class="field"><label for="{{ $key }}">{{ $label }}</label>
            <input id="{{ $key }}" name="{{ $key }}" value="{{ old($key, is_array($values[$key] ?? null) ? implode(', ', $values[$key]) : ($values[$key] ?? '')) }}" @if($type==='int') type="number" min="0" @endif required></div>
    @endforeach
    </div>
    <button class="btn">Simpan pengaturan</button>
</form>
<div class="card" style="max-width:760px">
    <h2>Integrasi ESS (API v1)</h2>
    <p>Status: {!! config('blj.api.enabled') ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-neutral">Nonaktif (default)</span>' !!}.
        Diaktifkan oleh IT melalui variabel lingkungan <code>BLJ_API_ENABLED=true</code> dan <code>BLJ_API_TOKEN</code>. Dokumentasi: <code>docs/api/openapi.yaml</code>.</p>
</div>
@endsection
