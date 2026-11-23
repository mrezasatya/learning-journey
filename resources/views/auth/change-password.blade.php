@extends($forced ? 'layouts.guest' : 'layouts.app')
@section('title', 'Ganti Password')
@section('content')
<div @unless($forced) class="card" style="max-width:480px" @endunless>
    <h1 style="font-size:1.25rem">Ganti Password</h1>
    @if ($forced)
        <div class="alert alert-info">Demi keamanan, Anda wajib mengganti password sebelum melanjutkan.</div>
    @endif
    <form method="post" action="{{ route('password.change.update') }}">
        @csrf
        <div class="field"><label for="current_password">Password saat ini</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
        <div class="field"><label for="password">Password baru</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            <div class="hint">Minimal 8 karakter, kombinasi huruf dan angka.</div></div>
        <div class="field"><label for="password_confirmation">Ulangi password baru</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
        <button class="btn btn-block">Simpan password</button>
    </form>
    @if ($forced)
        <form method="post" action="{{ route('logout') }}" style="margin-top:.75rem;text-align:center">@csrf<button class="btn-link">Keluar</button></form>
    @endif
</div>
@endsection
