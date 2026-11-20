<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bukaka Learning Journey</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth-wrap">
    <div class="card auth-card">
        <div class="brand"><span class="brand-mark">BLJ</span><span class="brand-name">Bukaka Learning Journey</span></div>
        <p class="muted">Tahap 0 — fondasi proyek berjalan. Palet warna brand:</p>
        <div class="row">
            @foreach (['--brand-900','--brand-700','--brand-500','--brand-300','--brand-100','--brand-50'] as $c)
                <span title="{{ $c }}" style="width:40px;height:40px;border-radius:8px;border:1px solid #ddd;background:var({{ $c }})"></span>
            @endforeach
        </div>
        <p style="margin-top:1rem"><span class="badge badge-success">Selesai</span> <span class="badge badge-info">Berjalan</span> <span class="badge badge-warning">Jatuh tempo</span> <span class="badge badge-danger">Overdue</span></p>
    </div>
</div>
</body>
</html>
