<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#003854">
    <title>@yield('title', 'Masuk') · BLJ</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
<div class="auth-wrap">
    <div class="card auth-card">
        <div class="brand"><span class="brand-mark">BLJ</span><span class="brand-name">Bukaka Learning Journey</span></div>
        @include('partials.flash')
        @yield('content')
    </div>
</div>
<script src="{{ asset('js/app.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>
