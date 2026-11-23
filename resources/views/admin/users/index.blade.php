@extends('layouts.app')
@section('title', 'Akun & Password')
@section('content')
<div class="page-head">
    <div><h1>Akun &amp; Password</h1><p class="muted">Reset password, buka kunci akun, dan kelola peran admin.</p></div>
</div>
<form class="filters" method="get">
    <div class="field"><label for="q">Cari NIK / nama</label><input id="q" type="search" name="q" value="{{ $q }}"></div>
    <div class="field"><label for="filter">Filter</label>
        <select id="filter" name="filter">
            <option value="">Semua akun</option>
            <option value="locked" @selected(request('filter')==='locked')>Terkunci</option>
            <option value="admin" @selected(request('filter')==='admin')>Admin</option>
        </select></div>
    <button class="btn btn-secondary">Terapkan</button>
</form>
<div class="card table-wrap">
<table>
    <thead><tr><th>NIK/Username</th><th>Nama</th><th>Status akun</th><th>Login terakhir</th><th>Peran</th><th>Aksi</th></tr></thead>
    <tbody>
    @forelse ($users as $user)
        <tr>
            <td>{{ $user->username }}</td>
            <td>{{ $user->displayName() }}<br><small>{{ $user->employee?->position?->name }}</small></td>
            <td>
                @if ($user->employee && ! $user->employee->is_active)<span class="badge badge-neutral">Nonaktif</span>
                @elseif ($user->isLocked())<span class="badge badge-danger">Terkunci s.d. {{ $user->locked_until->format('H:i') }}</span>
                @elseif ($user->must_change_password)<span class="badge badge-warning">Wajib ganti password</span>
                @else<span class="badge badge-success">Aktif</span>@endif
            </td>
            <td class="nowrap">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</td>
            <td>
                @if (auth()->user()->isSuperAdmin())
                    <form method="post" action="{{ route('admin.users.roles', $user) }}" class="row">@csrf
                        <label class="check"><input type="checkbox" name="roles[]" value="ADMIN_LD" @checked($user->roles->contains('code','ADMIN_LD'))> Admin L&amp;D</label>
                        <label class="check"><input type="checkbox" name="roles[]" value="SUPER_ADMIN" @checked($user->roles->contains('code','SUPER_ADMIN'))> Super</label>
                        <button class="btn btn-sm btn-secondary">Simpan</button>
                    </form>
                @else
                    {{ $user->roles->pluck('name')->join(', ') ?: '—' }}
                @endif
            </td>
            <td class="nowrap">
                <form method="post" action="{{ route('admin.users.reset', $user) }}" style="display:inline">@csrf
                    <button class="btn btn-sm btn-secondary" data-confirm="Reset password {{ $user->username }}? Password lama tidak berlaku lagi.">Reset password</button></form>
                @if ($user->isLocked())
                    <form method="post" action="{{ route('admin.users.unlock', $user) }}" style="display:inline">@csrf
                        <button class="btn btn-sm">Buka kunci</button></form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">Tidak ada akun.</td></tr>
    @endforelse
    </tbody>
</table>
{{ $users->links() }}
</div>
@endsection
