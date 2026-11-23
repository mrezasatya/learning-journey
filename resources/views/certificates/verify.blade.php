<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>Verifikasi Sertifikat · BLJ</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@include('partials.demo-banner')
<div class="auth-wrap"><div class="card auth-card">
    <div class="brand"><span class="brand-mark">BLJ</span><span class="brand-name">Verifikasi Sertifikat</span></div>
    @if (! $cert)
        <div class="alert alert-danger">Sertifikat tidak ditemukan. Pastikan QR yang dipindai berasal dari sertifikat BLJ.</div>
    @else
        <div class="alert {{ $cert->isValid() ? 'alert-success' : 'alert-warning' }}"><b>{{ $cert->isValid() ? '✓ Sertifikat VALID' : 'Sertifikat sudah tidak berlaku (masa berlaku habis)' }}</b></div>
        {{-- BR-35: hanya nama, program, tanggal, status --}}
        <dl class="kv">
            <dt>Nama</dt><dd>{{ $cert->assignment->employee->full_name }}</dd>
            <dt>Program</dt><dd>{{ $cert->assignment->program->name }}</dd>
            <dt>Tanggal selesai</dt><dd>{{ $cert->issued_at->translatedFormat('d F Y') }}</dd>
            @if($cert->valid_until)<dt>Berlaku s.d.</dt><dd>{{ $cert->valid_until->translatedFormat('d F Y') }}</dd>@endif
            <dt>Nomor</dt><dd>{{ $cert->certificate_no }}</dd>
        </dl>
    @endif
</div></div>
</body>
</html>
