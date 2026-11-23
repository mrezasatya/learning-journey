@extends('layouts.app')
@section('title', 'Impor Karyawan')
@section('content')
<div class="page-head"><div><h1>Impor data karyawan</h1><p class="muted">Excel (.xlsx) atau CSV. NIK menjadi kunci: NIK yang sudah ada akan diperbarui, bukan diduplikasi.</p></div>
    <a class="btn btn-secondary" href="{{ route('admin.employees.index') }}">← Daftar karyawan</a></div>
@if (session('credentials_token'))
    <div class="alert alert-warning">
        Akun baru dibuat dengan password awal acak. <a href="{{ route('admin.employees.credentials', session('credentials_token')) }}"><b>Unduh daftar password awal</b></a>
        — hanya dapat diunduh <b>sekali</b>. Simpan dengan aman dan bagikan secara pribadi.
    </div>
@endif
<div class="grid grid-2">
    <div class="card">
        <h2>1. Unduh template</h2>
        <p>Template berisi kolom yang wajib, petunjuk, dan daftar kode referensi (jabatan, golongan, departemen, lokasi).</p>
        <a class="btn btn-secondary" href="{{ route('admin.employees.template') }}">⬇ Unduh template .xlsx</a>
    </div>
    <div class="card">
        <h2>2. Unggah berkas</h2>
        <form method="post" action="{{ route('admin.employees.preview') }}" enctype="multipart/form-data">
            @csrf
            <div class="field"><label for="file">Berkas .xlsx / .csv (maks. 10 MB)</label><input id="file" type="file" name="file" accept=".xlsx,.csv" required></div>
            <button class="btn">Lihat pratinjau</button>
        </form>
        <p class="muted" style="margin-top:.5rem">Belum ada yang disimpan sebelum Anda menekan <b>Simpan impor</b> di halaman pratinjau.</p>
    </div>
</div>
@endsection
