@extends('layouts.app')
@section('title', 'Hasil '.$test->label())
@section('content')
<div class="page-head"><div><small class="muted"><a href="{{ route('learn.program', $a) }}">{{ $a->program->name }}</a></small><h1>Hasil {{ $test->label() }}</h1></div></div>
<div class="card" style="max-width:640px;text-align:center">
    <div class="stat"><div class="label">Nilai Anda</div><div class="value" style="font-size:3rem">{{ $attempt->score }}</div></div>
    @if ($test->kind === 'PRE')
        <p>Terima kasih. Nilai pre-test dipakai untuk mengukur peningkatan pengetahuan Anda (tidak menentukan kelulusan).</p>
    @elseif ($attempt->passed)
        <p><span class="badge badge-success" style="font-size:1rem">LULUS</span></p>
        <p>Selamat! Langkah berikutnya: isi survei reaksi untuk menerbitkan sertifikat.</p>
    @else
        <p><span class="badge badge-danger" style="font-size:1rem">BELUM LULUS</span> (nilai lulus {{ $test->passing_score }})</p>
        @if ($relearn)
            <div class="alert alert-warning" style="text-align:left"><b>Perlu Mengulang Materi.</b> Kesempatan post-test pada siklus ini sudah habis. Silakan pelajari ulang seluruh materi; setelah itu Anda mendapat {{ $test->max_attempts }} kesempatan baru. Atasan dan Admin L&amp;D telah diberi tahu.</div>
        @else
            <p>Sisa kesempatan: <b>{{ $postStatus['remaining'] }}</b>.
                @if ($postStatus['next_at']) Remedial dapat dikerjakan mulai <b>{{ $postStatus['next_at']->translatedFormat('d M Y H:i') }}</b>. Manfaatkan jeda ini untuk meninjau materi. @endif</p>
        @endif
    @endif
    <a class="btn" href="{{ $next }}">Lanjutkan</a>
</div>
@if ($test->kind === 'POST' && $attempt->passed && $test->show_explanation)
<div class="card" style="max-width:820px">
    <h2>Pembahasan</h2>
    @foreach ($attempt->answers as $i => $ans)
        @if ($ans->question->explanation)<p><b>{{ $i + 1 }}. {{ $ans->question->text }}</b><br>{{ $ans->question->explanation }}</p>@endif
    @endforeach
</div>
@endif
@endsection
