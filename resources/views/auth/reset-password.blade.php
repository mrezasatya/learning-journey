@extends('layouts.guest')
@section('title', 'Buat Password Baru')
@section('content')
<h1 style="font-size:1.25rem">Buat Password Baru</h1>
<form method="post" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="field"><label for="username">NIK</label><input id="username" name="username" value="{{ old('username', $nik) }}" required></div>
    <div class="field"><label for="password">Password baru</label><input id="password" name="password" type="password" required>
        <div class="hint">Minimal 8 karakter, kombinasi huruf dan angka.</div></div>
    <div class="field"><label for="password_confirmation">Ulangi password baru</label><input id="password_confirmation" name="password_confirmation" type="password" required></div>
    <button class="btn btn-block">Simpan</button>
</form>
@endsection
