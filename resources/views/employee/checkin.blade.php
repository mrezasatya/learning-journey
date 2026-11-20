@extends('layouts.app')
@section('title', 'Check-in Sesi')
@section('content')
<div class="card" style="max-width:480px;margin:0 auto;text-align:center">
    <h1>Check-in sesi</h1>
    <p><b>{{ $s->title }}</b><br>{{ $s->program->name }}<br>{{ $s->start_at->translatedFormat('d M Y H:i') }}–{{ $s->end_at->format('H:i') }} · {{ $s->location }}</p>
    @if ($p && $p->attendance_status === 'PRESENT')
        <div class="alert alert-success">✓ Kehadiran Anda sudah tercatat ({{ $p->checkin_at?->format('H:i') }}).</div>
    @elseif (! $s->isCheckinOpen())
        <div class="alert alert-warning">QR hanya dapat dipakai pada waktu sesi (±30 menit).</div>
    @else
        <form method="post" action="{{ route('checkin.store', $s->qr_token) }}">@csrf<button class="btn btn-block">Saya hadir</button></form>
    @endif
    <p style="margin-top:1rem"><a href="{{ route('employee.home') }}">Ke beranda</a></p>
</div>
@endsection
