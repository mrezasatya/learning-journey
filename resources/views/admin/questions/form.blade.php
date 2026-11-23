@extends('layouts.app')
@section('title', $question->exists ? 'Ubah Soal' : 'Soal Baru')
@section('content')
@php
    $opts = old('options', $question->exists ? $question->options->pluck('text')->all() : ['', '', '', '']);
    $opts = array_pad($opts, 6, '');
    $correct = array_map('strval', old('correct', $question->exists ? $question->options->values()->filter->is_correct->keys()->all() : []));
    $tf = old('tf_answer', $question->exists && $question->type === 'TRUE_FALSE' ? ($question->options->firstWhere('is_correct', true)?->text === 'Salah' ? 'SALAH' : 'BENAR') : 'BENAR');
@endphp
<div class="page-head"><div><h1>{{ $question->exists ? 'Ubah soal' : 'Soal baru' }}</h1><p class="muted">{{ $program->name }}</p></div></div>
<form method="post" action="{{ $question->exists ? route('admin.questions.update', $question) : route('admin.questions.store', $program) }}" class="card">
    @csrf @if($question->exists) @method('put') @endif
    <div class="form-grid">
        <div class="field"><label for="type">Tipe soal</label><select id="type" name="type">@foreach(\App\Models\Question::TYPES as $k=>$v)<option value="{{ $k }}" @selected(old('type',$question->type)===$k)>{{ $v }}</option>@endforeach</select></div>
        <div class="field"><label for="difficulty">Tingkat kesulitan</label><select id="difficulty" name="difficulty">@foreach(\App\Models\Question::DIFFICULTIES as $k=>$v)<option value="{{ $k }}" @selected(old('difficulty',$question->difficulty)===$k)>{{ $v }}</option>@endforeach</select></div>
        <div class="field"><label for="module_id">Modul (opsional)</label><select id="module_id" name="module_id"><option value="">—</option>@foreach($program->modules as $m)<option value="{{ $m->id }}" @selected(old('module_id',$question->module_id)==$m->id)>{{ $m->title }}</option>@endforeach</select></div>
    </div>
    <div class="field"><label for="text">Pertanyaan</label><textarea id="text" name="text" required>{{ old('text', $question->text) }}</textarea></div>
    <div id="opts-choice">
        <label>Opsi jawaban — centang yang benar</label>
        @foreach ($opts as $i => $o)
            <div class="row" style="margin-bottom:.4rem">
                <input type="checkbox" name="correct[]" value="{{ $i }}" @checked(in_array((string)$i, $correct, true)) aria-label="Opsi {{ chr(65+$i) }} benar" style="width:22px;height:22px">
                <span style="width:1.2rem;font-weight:700">{{ chr(65+$i) }}</span>
                <input name="options[]" value="{{ $o }}" style="flex:1" placeholder="Opsi {{ chr(65+$i) }}{{ $i >= 2 ? ' (opsional)' : '' }}">
            </div>
        @endforeach
    </div>
    <div id="opts-tf" class="field">
        <label>Jawaban benar</label>
        <label class="check"><input type="radio" name="tf_answer" value="BENAR" @checked($tf==='BENAR')> Benar</label>
        <label class="check"><input type="radio" name="tf_answer" value="SALAH" @checked($tf==='SALAH')> Salah</label>
    </div>
    <div class="field"><label for="explanation">Pembahasan (opsional)</label><textarea id="explanation" name="explanation">{{ old('explanation', $question->explanation) }}</textarea></div>
    <div class="row"><button class="btn">Simpan soal</button><a class="btn btn-secondary" href="{{ route('admin.questions.index', $program) }}">Batal</a></div>
</form>
@push('scripts')
<script>
(function(){var t=document.getElementById('type');function s(){var tf=t.value==='TRUE_FALSE';document.getElementById('opts-choice').classList.toggle('hidden',tf);document.getElementById('opts-tf').classList.toggle('hidden',!tf);}t.addEventListener('change',s);s();})();
</script>
@endpush
@endsection
