@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
<h1 style="font-size:1.25rem">Masuk</h1>
<p class="muted">Gunakan NIK dan password Anda.</p>
<form method="post" action="{{ route('login') }}">
    @csrf
    <div class="field">
        <label for="username">NIK</label>
        <input id="username" name="username" type="text" inputmode="text" autocomplete="username" value="{{ old('username') }}" required autofocus>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <div class="field"><label class="check"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label></div>
    <button class="btn btn-block">Masuk</button>
</form>
<p style="margin-top:1rem;text-align:center"><a href="{{ route('password.request') }}">Lupa password?</a></p>
@if (app()->environment('local'))
    <details style="margin-top:1rem"><summary>Akun demo (data dummy)</summary>
        <p class="muted" style="margin-top:.5rem">Password semua akun: <code>Bukaka123</code></p>
        <table><tbody>
            <tr><td><code>100001</code></td><td>Admin L&amp;D</td></tr>
            <tr><td><code>100010</code></td><td>Atasan</td></tr>
            <tr><td><code>100101</code></td><td>Karyawan</td></tr>
            <tr><td><code>100102</code></td><td>Karyawan baru (wajib ganti password)</td></tr>
            <tr><td><code>superadmin</code></td><td>Super Admin</td></tr>
        </tbody></table>
    </details>
@endif
@endsection
