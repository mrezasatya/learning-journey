@extends('layouts.app')
@section('title', $lesson->exists ? 'Ubah Materi' : 'Materi Baru')
@section('content')
<div class="page-head"><div><h1>{{ $lesson->exists ? 'Ubah materi' : 'Materi baru' }}</h1><p class="muted">{{ $module->program->name }} › {{ $module->title }}</p></div></div>
<form method="post" enctype="multipart/form-data" action="{{ $lesson->exists ? route('admin.lessons.update', $lesson) : route('admin.lessons.store', $module) }}" class="card" id="lesson-form">
    @csrf @if($lesson->exists) @method('put') @endif
    <div class="form-grid">
        <div class="field"><label for="title">Judul materi</label><input id="title" name="title" value="{{ old('title', $lesson->title) }}" required></div>
        <div class="field"><label for="type">Tipe</label><select id="type" name="type">
            @foreach (\App\Models\Lesson::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('type', $lesson->type)===$k)>{{ $v }}</option>@endforeach</select>
            <div class="hint">Tipe otomatis disesuaikan dengan berkas yang diunggah.</div></div>
    </div>
    <div class="field" data-for="VIDEO PDF SLIDE IMAGE">
        <label for="file">Berkas {{ $lesson->file ? '(kosongkan bila tidak diganti)' : '' }}</label>
        <input id="file" type="file" name="file" accept=".mp4,.m4v,.mov,.webm,.pdf,.ppt,.pptx,.jpg,.jpeg,.png,.webp">
        <div class="hint">Video MP4 → otomatis dikonversi ke 360p/480p/720p. PDF/PPT → diubah menjadi gambar per halaman agar ringan di HP. Maks. {{ \App\Support\Settings::get('upload_max_mb') }} MB. Saran: video ≤ 15 menit per materi.</div>
        @if ($lesson->file)<p class="muted">Berkas saat ini: {{ $lesson->file->original_name }} ({{ $lesson->file->humanSize() }})</p>@endif
    </div>
    <div class="field" data-for="TEXT IMAGE">
        <label>Isi artikel / keterangan</label>
        <div class="row no-print" style="margin-bottom:.35rem" id="rt-toolbar">
            <button type="button" class="btn btn-sm btn-secondary" data-cmd="bold"><b>B</b></button>
            <button type="button" class="btn btn-sm btn-secondary" data-cmd="italic"><i>I</i></button>
            <button type="button" class="btn btn-sm btn-secondary" data-cmd="formatBlock" data-val="h2">Judul</button>
            <button type="button" class="btn btn-sm btn-secondary" data-cmd="formatBlock" data-val="p">Paragraf</button>
            <button type="button" class="btn btn-sm btn-secondary" data-cmd="insertUnorderedList">• Daftar</button>
            <button type="button" class="btn btn-sm btn-secondary" data-cmd="insertOrderedList">1. Daftar</button>
            <button type="button" class="btn btn-sm btn-secondary" data-cmd="createLink">Tautan</button>
        </div>
        <div id="rt-editor" class="rich-text" contenteditable="true" style="min-height:220px;border:1px solid var(--neutral-400);border-radius:8px;padding:.75rem;background:#fff">{!! old('body', $lesson->body) !!}</div>
        <input type="hidden" name="body" id="body">
    </div>
    <div class="form-grid">
        <div class="field" data-for="PDF SLIDE TEXT IMAGE"><label for="min_seconds">Waktu minimum (detik)</label>
            <input id="min_seconds" type="number" name="min_seconds" min="0" value="{{ old('min_seconds', $lesson->min_seconds) }}" placeholder="default">
            <div class="hint">Dokumen: per halaman (default {{ \App\Support\Settings::get('seconds_per_page') }} dtk). Teks/gambar: total (default {{ \App\Support\Settings::get('text_min_seconds') }} dtk). BR-19.</div></div>
        <div class="field" data-for="VIDEO"><label for="completion_threshold">Batas tonton dianggap selesai</label>
            <select id="completion_threshold" name="completion_threshold">
                @foreach ([0.8, 0.85, 0.9, 0.95, 1.0] as $t)<option value="{{ $t }}" @selected((float) old('completion_threshold', $lesson->completion_threshold ?? 0.9) == $t)>{{ $t * 100 }}%</option>@endforeach</select></div>
    </div>
    <div class="row"><button class="btn">Simpan materi</button><a class="btn btn-secondary" href="{{ route('admin.programs.show', $module->program_id) }}">Batal</a></div>
</form>
@push('scripts')
<script>
(function () {
  var type = document.getElementById('type');
  function sync() {
    document.querySelectorAll('[data-for]').forEach(function (el) {
      el.classList.toggle('hidden', el.dataset.for.split(' ').indexOf(type.value) < 0);
    });
  }
  type.addEventListener('change', sync); sync();
  var ed = document.getElementById('rt-editor');
  document.getElementById('rt-toolbar').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    var val = b.dataset.val || null;
    if (b.dataset.cmd === 'createLink') { val = prompt('Alamat tautan (https://...)'); if (!val) return; }
    document.execCommand(b.dataset.cmd, false, val); ed.focus();
  });
  document.getElementById('lesson-form').addEventListener('submit', function () {
    document.getElementById('body').value = ed.innerHTML;
  });
})();
</script>
@endpush
@endsection
