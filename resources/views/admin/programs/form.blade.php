@extends('layouts.app')
@section('title', $program->exists ? 'Ubah Program' : 'Program Baru')
@section('content')
@php $locked = $program->exists && $program->assignments()->exists(); @endphp
<div class="page-head"><div><h1>{{ $program->exists ? 'Ubah program' : 'Program baru' }}</h1></div></div>
<form method="post" enctype="multipart/form-data" action="{{ $program->exists ? route('admin.programs.update', $program) : route('admin.programs.store') }}" class="card">
    @csrf @if($program->exists) @method('put') @endif
    <div class="form-grid">
        <div class="field"><label for="code">Kode program</label><input id="code" name="code" value="{{ old('code', $program->code) }}" required @readonly($locked) placeholder="mis. K3-01">
            <div class="hint">Dipakai di nomor sertifikat: BLJ/[KODE]/[TAHUN]/00001</div></div>
        <div class="field"><label for="name">Nama program</label><input id="name" name="name" value="{{ old('name', $program->name) }}" required></div>
        <div class="field"><label for="type">Tipe</label><select id="type" name="type" @disabled($locked)>
            @foreach (\App\Models\Program::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('type', $program->type)===$k)>{{ $v }}</option>@endforeach</select>
            @if($locked)<input type="hidden" name="type" value="{{ $program->type }}">@endif
            <div class="hint">Onboarding: dipicu tanggal masuk · Wajib Sekali: sekali saja · Wajib Tahunan: diulang tiap 1 Januari</div></div>
        <div class="field"><label for="category">Kategori</label><select id="category" name="category">
            @foreach (\App\Models\Program::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('category', $program->category)===$k)>{{ $v }}</option>@endforeach</select></div>
        <div class="field"><label for="deadline_days">Tenggat (hari sejak ditugaskan)</label><input id="deadline_days" type="number" min="1" max="365" name="deadline_days" value="{{ old('deadline_days', $program->deadline_days) }}">
            <div class="hint">Kosongkan = 365 hari. Maks. 365 (BR-14/15). Untuk Wajib Tahunan tenggat mengikuti 31 Desember.</div></div>
        <div class="field"><label for="thumbnail">Thumbnail (opsional)</label><input id="thumbnail" type="file" name="thumbnail" accept="image/*"></div>
    </div>
    <div class="field"><label for="description">Deskripsi</label><textarea id="description" name="description">{{ old('description', $program->description) }}</textarea></div>
    <div class="field"><label class="check"><input type="checkbox" name="sequential" value="1" @checked(old('sequential', $program->sequential))> Materi harus dibuka berurutan (BR-18)</label></div>
    <div class="field"><label class="check"><input type="checkbox" name="is_blended" value="1" @checked(old('is_blended', $program->is_blended))> Blended: kehadiran sesi tatap muka menjadi syarat selesai (BR-45)</label></div>
    <div class="row"><button class="btn">Simpan</button><a class="btn btn-secondary" href="{{ $program->exists ? route('admin.programs.show', $program) : route('admin.programs.index') }}">Batal</a></div>
</form>
@endsection
