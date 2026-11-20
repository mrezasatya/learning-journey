@extends('layouts.guest')
@section('title', 'Lupa Password')
@section('content')
<h1 style="font-size:1.25rem">Lupa Password</h1>
<p class="muted">Masukkan NIK. Tautan reset dikirim ke email yang terdaftar. Bila Anda tidak memiliki email, hubungi Admin L&amp;D.</p>
<form method="post" action="{{ route('password.email') }}">
    @csrf
    <div class="field"><label for="username">NIK</label><input id="username" name="username" value="{{ old('username') }}" required></div>
    <button class="btn btn-block">Kirim tautan reset</button>
</form>
<p style="margin-top:1rem;text-align:center"><a href="{{ route('login') }}">Kembali ke halaman masuk</a></p>
@endsection
