@extends('layouts.app')
@section('title', $test->label())
@section('content')
<div class="page-head">
    <div><small class="muted"><a href="{{ route('learn.program', $a) }}">{{ $a->program->name }}</a></small><h1>{{ $test->label() }}</h1></div>
</div>
<div class="card" style="max-width:640px">
    <dl class="kv">
        <dt>Jumlah soal</dt><dd>{{ $test->question_count }}</dd>
        <dt>Durasi</dt><dd>{{ $test->duration_minutes }} menit (waktu berjalan di server — tetap berjalan bila koneksi terputus)</dd>
        @if ($kind === 'POST')
            <dt>Nilai lulus</dt><dd>{{ $test->passing_score }}</dd>
            <dt>Percobaan siklus ini</dt><dd>{{ $postStatus['used'] }} dari {{ $postStatus['max'] }} (1 + {{ $postStatus['max'] - 1 }} remedial)</dd>
        @else
            <dt>Catatan</dt><dd>Pre-test dikerjakan sekali dan <b>tidak menentukan kelulusan</b>. Kerjakan dengan jujur untuk mengukur pengetahuan awal Anda.</dd>
        @endif
    </dl>
    <ul class="muted" style="margin-top:1rem">
        <li>Soal dan pilihan jawaban diacak untuk setiap peserta.</li>
        <li>Jawaban tersimpan otomatis setiap kali Anda memilih.</li>
        <li>Bila koneksi terputus, buka kembali halaman ini untuk melanjutkan selama waktu masih ada.</li>
        <li>Kunci jawaban tidak ditampilkan setelah tes untuk menjaga kerahasiaan bank soal.</li>
    </ul>
    @if ($open)
        <form method="post" action="{{ route('learn.test.start', [$a, $kind]) }}">@csrf<button class="btn btn-block">Lanjutkan tes (sisa {{ gmdate('i:s', $open->remainingSeconds()) }})</button></form>
    @elseif ($block)
        <div class="alert alert-warning">{{ $block }}</div>
        <a class="btn btn-secondary" href="{{ route('learn.program', $a) }}">Kembali ke program</a>
    @else
        <form method="post" action="{{ route('learn.test.start', [$a, $kind]) }}">@csrf<button class="btn btn-block" data-confirm="Mulai {{ $test->label() }} sekarang? Timer {{ $test->duration_minutes }} menit akan berjalan.">Mulai {{ $test->label() }}</button></form>
    @endif
</div>
@if ($history->isNotEmpty())
<div class="card" style="max-width:640px">
    <h2>Riwayat percobaan</h2>
    <table><thead><tr><th>Siklus</th><th>Percobaan</th><th>Tanggal</th><th class="num">Nilai</th><th></th></tr></thead><tbody>
    @foreach ($history as $h)
        <tr><td>{{ $h->cycle_no }}</td><td>{{ $h->attempt_no }}</td><td>{{ $h->submitted_at->format('d/m/Y H:i') }}</td><td class="num"><b>{{ $h->score }}</b></td>
            <td>@if($kind==='POST')<span class="badge {{ $h->passed ? 'badge-success' : 'badge-danger' }}">{{ $h->passed ? 'Lulus' : 'Belum lulus' }}</span>@endif</td></tr>
    @endforeach
    </tbody></table>
</div>
@endif
@endsection
