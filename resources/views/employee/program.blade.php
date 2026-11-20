@extends('layouts.app')
@section('title', $a->program->name)
@section('content')
@php $pre = $a->program->preAssessment; $post = $a->program->postAssessment; @endphp
<div class="page-head">
    <div><h1>{{ $a->program->name }}</h1>
        <p class="muted">{{ $a->program->typeLabel() }} · {{ $a->program->categoryLabel() }} @if($a->period_year)· Periode {{ $a->period_year }}@endif
            · Tenggat {{ $a->due_at->translatedFormat('d M Y') }} · <span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span></p></div>
    <a class="btn btn-secondary" href="{{ route('employee.home') }}">← Beranda</a>
</div>
@if ($a->program->description)<p>{{ $a->program->description }}</p>@endif
<div class="card">
    <div class="row"><div class="progress lg" style="flex:1"><span style="width:{{ $percent }}%"></span></div><b>{{ $percent }}%</b></div>
    @if ($a->isOpen())<a class="btn" style="margin-top:.75rem" href="{{ $resume }}">{{ $a->started_at ? 'Lanjutkan' : 'Mulai' }}</a>@endif
    @if ($a->certificate)<a class="btn btn-success" style="margin-top:.75rem" href="{{ route('learn.certificate', $a) }}">⬇ Unduh sertifikat</a>@endif
</div>
@if ($a->cycle_no > 1 && $step === 'LESSONS')
    <div class="alert alert-warning"><b>Perlu Mengulang Materi.</b> Anda belum mencapai nilai {{ $post->passing_score }} setelah {{ $post->max_attempts }} percobaan. Pelajari ulang seluruh materi, lalu Anda mendapat {{ $post->max_attempts }} kesempatan post-test baru (BR-24).</div>
@endif
<div class="card">
    <h2>Langkah belajar</h2>
    <ol class="steps">
        <li class="{{ $preDone ? 'done' : ($step === 'PRE_TEST' ? 'current' : '') }}">
            <span class="dot">{{ $preDone ? '✓' : '1' }}</span>
            <div class="grow"><b>Pre-test</b><br><small>{{ $pre?->question_count }} soal · {{ $pre?->duration_minutes }} menit · mengukur pengetahuan awal, tidak memengaruhi kelulusan @if($a->pre_score !== null)· Nilai: <b>{{ $a->pre_score }}</b>@endif</small></div>
            @if (! $preDone && $a->isOpen())<a class="btn btn-sm" href="{{ route('learn.test.intro', [$a, 'PRE']) }}">Kerjakan</a>@endif
        </li>
        @foreach ($lessons as $i => $x)
            <li class="{{ $x['done'] ? 'done' : ($x['locked'] ? 'locked' : 'current') }}">
                <span class="dot">{{ $x['done'] ? '✓' : ($x['locked'] ? '🔒' : $x['lesson']->icon()) }}</span>
                <div class="grow"><b>{{ $x['lesson']->title }}</b><br><small>{{ $x['lesson']->module->title }} · {{ $x['lesson']->typeLabel() }}
                    @if($x['lesson']->file?->duration_seconds) · {{ gmdate('i:s', $x['lesson']->file->duration_seconds) }} @endif
                    @if($x['lesson']->file?->page_count) · {{ $x['lesson']->file->page_count }} halaman @endif</small></div>
                @if (! $x['locked'] || ! $a->isOpen())<a class="btn btn-sm {{ $x['done'] ? 'btn-secondary' : '' }}" href="{{ route('learn.lesson', [$a, $x['lesson']]) }}">{{ $x['done'] ? 'Buka' : 'Pelajari' }}</a>@endif
            </li>
        @endforeach
        <li class="{{ $postPassed ? 'done' : ($step === 'POST_TEST' ? 'current' : 'locked') }}">
            <span class="dot">{{ $postPassed ? '✓' : '✎' }}</span>
            <div class="grow"><b>Post-test</b><br><small>Lulus ≥ {{ $post?->passing_score }} · {{ $post?->question_count }} soal · {{ $post?->duration_minutes }} menit
                · Percobaan siklus ini: {{ $postStatus['used'] }}/{{ $postStatus['max'] }} @if($postStatus['best'] !== null)· Nilai tertinggi: <b>{{ $postStatus['best'] }}</b>@endif</small></div>
            @if ($step === 'POST_TEST')<a class="btn btn-sm" href="{{ route('learn.test.intro', [$a, 'POST']) }}">Kerjakan</a>@endif
        </li>
        @if ($a->program->is_blended)
            <li class="{{ $sessionAttended ? 'done' : 'locked' }}">
                <span class="dot">{{ $sessionAttended ? '✓' : '🏫' }}</span>
                <div class="grow"><b>Sesi tatap muka</b><br><small>
                    @forelse ($sessions as $s){{ $s->title }} — {{ $s->start_at->translatedFormat('d M Y H:i') }}, {{ $s->location }}<br>@empty Jadwal akan diinformasikan Admin L&amp;D. @endforelse
                    Kehadiran dicatat via QR check-in.</small></div>
            </li>
        @endif
        <li class="{{ $a->surveyResponse ? 'done' : ($step === 'SURVEY' ? 'current' : 'locked') }}">
            <span class="dot">{{ $a->surveyResponse ? '✓' : '★' }}</span>
            <div class="grow"><b>Survei reaksi</b><br><small>Wajib diisi sebelum sertifikat terbit</small></div>
            @if ($step === 'SURVEY')<a class="btn btn-sm" href="{{ route('learn.survey', $a) }}">Isi survei</a>@endif
        </li>
        <li class="{{ $a->certificate ? 'done' : 'locked' }}">
            <span class="dot">🎓</span>
            <div class="grow"><b>Sertifikat</b>@if($a->certificate)<br><small>{{ $a->certificate->certificate_no }}</small>@endif</div>
            @if ($a->certificate)<a class="btn btn-sm btn-success" href="{{ route('learn.certificate', $a) }}">Unduh</a>@endif
        </li>
    </ol>
</div>
@endsection
