@extends('layouts.app')
@section('title', 'Template Survei')
@section('content')
<div class="page-head"><div><h1>{{ $template->exists ? 'Ubah' : 'Buat' }} template survei</h1></div></div>
<form method="post" action="{{ $template->exists ? route('admin.surveys.update', $template) : route('admin.surveys.store') }}" class="card">
    @csrf @if($template->exists) @method('put') @endif
    <div class="field"><label for="name">Nama template</label><input id="name" name="name" value="{{ old('name', $template->name) }}" required></div>
    <div class="field"><label for="likert">Pernyataan skala 1–5 (satu per baris)</label>
        <textarea id="likert" name="likert" rows="6" required>{{ old('likert', collect($template->items)->where('type','LIKERT')->pluck('text')->join("\n")) }}</textarea></div>
    <div class="field"><label for="open">Pertanyaan terbuka (satu per baris, opsional)</label>
        <textarea id="open" name="open" rows="2">{{ old('open', collect($template->items)->where('type','TEXT')->pluck('text')->join("\n")) }}</textarea></div>
    <div class="field"><label class="check"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $template->is_default))> Jadikan template default</label></div>
    <div class="row"><button class="btn">Simpan</button><a class="btn btn-secondary" href="{{ route('admin.surveys.index') }}">Batal</a></div>
</form>
@endsection
