<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#003854">
    <title>@yield('title', 'Beranda') · BLJ</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
    @stack('head')
</head>
<body>
@php
    $u = auth()->user();
    $nav = function (string $route, string $label, string $icon, ?string $pattern = null) {
        if (! Route::has($route)) return '';
        $active = request()->routeIs($pattern ?? $route) ? 'active' : '';
        return '<a href="'.route($route).'" class="'.$active.'"><span class="ico" aria-hidden="true">'.$icon.'</span>'.e($label).'</a>';
    };
    $unread = $u->unreadNotificationCount();
@endphp
<header class="topbar">
    <button class="menu-toggle" data-toggle-menu aria-label="Buka menu">☰</button>
    <a href="{{ route('home') }}" class="brand">
        <span class="brand-mark">BLJ</span>
        <span class="brand-name">Bukaka Learning Journey<small>Training Wajib</small></span>
    </a>
    <span class="spacer"></span>
    @if (Route::has('notifications.index'))
        <a href="{{ route('notifications.index') }}" class="icon-btn" aria-label="Notifikasi ({{ $unread }} belum dibaca)">🔔@if($unread)<span class="badge-dot">{{ $unread > 99 ? '99+' : $unread }}</span>@endif</a>
    @endif
    <form method="post" action="{{ route('logout') }}" style="margin:0">@csrf
        <button class="btn btn-sm btn-secondary" style="min-height:34px">Keluar</button>
    </form>
</header>
<div class="layout">
    <nav class="sidebar" aria-label="Menu utama">
        <div style="padding:0 .75rem .5rem">
            <strong>{{ $u->displayName() }}</strong><br>
            <small>{{ $u->employee?->nik ?? $u->username }} · {{ $u->employee?->position?->name ?? 'Akun sistem' }}</small>
        </div>
        @if ($u->employee)
            <div class="nav-group">Belajar</div>
            {!! $nav('employee.home', 'Beranda', '🏠') !!}
            {!! $nav('employee.history', 'Riwayat & Sertifikat', '🎓') !!}
            {!! $nav('employee.sessions', 'Jadwal Klasikal', '📅') !!}
            {!! $nav('notifications.index', 'Notifikasi', '🔔') !!}
            {!! $nav('profile', 'Profil', '👤') !!}
        @endif
        @if ($u->isSupervisor())
            <div class="nav-group">Atasan</div>
            {!! $nav('team.index', 'Tim Saya', '👥', 'team.*') !!}
        @endif
        @if ($u->isAdmin())
            <div class="nav-group">Admin L&amp;D</div>
            {!! $nav('admin.dashboard', 'Dashboard Kepatuhan', '📈') !!}
            {!! $nav('admin.employees.index', 'Karyawan', '🧑‍🏭', 'admin.employees.*') !!}
            {!! $nav('admin.master.index', 'Master Data', '🗂', 'admin.master.*') !!}
            {!! $nav('admin.programs.index', 'Program & Konten', '📚', 'admin.programs.*|admin.modules.*|admin.lessons.*|admin.questions.*|admin.assessments.*') !!}
            {!! $nav('admin.rules.index', 'Aturan Penugasan', '⚙', 'admin.rules.*') !!}
            {!! $nav('admin.assignments.index', 'Penugasan', '📋', 'admin.assignments.*') !!}
            {!! $nav('admin.sessions.index', 'Sesi Klasikal', '🏫', 'admin.sessions.*') !!}
            {!! $nav('admin.reports.index', 'Laporan', '📊', 'admin.reports.*') !!}
            {!! $nav('admin.overdue.index', 'Overdue & Teguran', '⚠', 'admin.overdue.*') !!}
            {!! $nav('admin.users.index', 'Akun & Password', '🔑', 'admin.users.*') !!}
            {!! $nav('admin.audit.index', 'Audit Log', '🧾') !!}
            {!! $nav('admin.settings.edit', 'Pengaturan', '🛠') !!}
        @endif
        <div class="nav-group">Akun</div>
        {!! $nav('password.change', 'Ganti Password', '🔒') !!}
    </nav>
    <main class="content" id="main">
        @include('partials.flash')
        @yield('content')
    </main>
</div>
<script src="{{ asset('js/app.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>
