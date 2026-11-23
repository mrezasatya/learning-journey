@extends('layouts.app')
@section('title', 'Survei Reaksi')
@section('content')
<div class="page-head"><div><small class="muted"><a href="{{ route('learn.program', $a) }}">{{ $a->program->name }}</a></small><h1>Survei reaksi</h1>
    <p class="muted">Penilaian Anda membantu L&amp;D memperbaiki training. 1 = sangat tidak setuju, 5 = sangat setuju.</p></div></div>
<form method="post" action="{{ route('learn.survey.store', $a) }}" class="card" style="max-width:720px">
    @csrf
    @foreach ($template->items as $i => $item)
        <div class="field">
            <label>{{ $i + 1 }}. {{ $item['text'] }}</label>
            @if ($item['type'] === 'LIKERT')
                <div class="likert" role="radiogroup">
                    @foreach ([1 => 'Sangat tidak setuju', 2 => 'Tidak setuju', 3 => 'Netral', 4 => 'Setuju', 5 => 'Sangat setuju'] as $v => $lbl)
                        <label title="{{ $lbl }}"><input type="radio" name="answers[{{ $item['code'] }}]" value="{{ $v }}" @checked(old('answers.'.$item['code']) == $v) required><span>{{ $v }}</span></label>
                    @endforeach
                </div>
            @else
                <textarea name="answers[{{ $item['code'] }}]" rows="3">{{ old('answers.'.$item['code']) }}</textarea>
            @endif
        </div>
    @endforeach
    <button class="btn btn-block">Kirim survei &amp; terbitkan sertifikat</button>
</form>
@endsection
