@extends('layouts.app')
@section('title', $program->name)
@section('content')
@include('admin.programs._tabs')
@php
    $pre = $program->assessments->firstWhere('kind', 'PRE');
    $post = $program->assessments->firstWhere('kind', 'POST');
    $editable = $program->status === 'DRAFT' || $program->assignments()->doesntExist();
    $statusBadge = ['PENDING' => ['Menunggu proses', 'badge-warning'], 'PROCESSING' => ['Sedang diproses', 'badge-info'], 'READY' => ['Siap', 'badge-success'], 'FAILED' => ['Gagal diproses', 'badge-danger']];
@endphp

<div class="card">
    <div class="card-header">
        <h2>Ringkasan</h2>
        <div class="row">
            <a class="btn btn-sm btn-secondary" href="{{ route('admin.programs.edit', $program) }}">Ubah info program</a>
            @if ($program->status === 'DRAFT')
                <form method="post" action="{{ route('admin.programs.publish', $program) }}">@csrf<button class="btn btn-sm btn-success" @disabled(count($problems))>Terbitkan</button></form>
            @endif
            @if ($program->status === 'PUBLISHED')
                <form method="post" action="{{ route('admin.programs.version', $program) }}">@csrf<button class="btn btn-sm btn-secondary" data-confirm="Buat versi baru (Draft) dari program ini?">Buat versi baru</button></form>
                <form method="post" action="{{ route('admin.programs.archive', $program) }}">@csrf<button class="btn btn-sm btn-secondary" data-confirm="Arsipkan program? Aturan penugasannya akan dinonaktifkan.">Arsipkan</button></form>
            @endif
            @if ($canDelete)
                <form method="post" action="{{ route('admin.programs.destroy', $program) }}">@csrf @method('delete')<button class="btn btn-sm btn-danger" data-confirm="Hapus program ini beserta isinya?">Hapus</button></form>
            @endif
        </div>
    </div>
    <p>{{ $program->description }}</p>
    <dl class="kv">
        <dt>Tenggat</dt><dd>{{ $program->type === 'ANNUAL' ? '31 Desember periode berjalan' : ($program->deadline_days ?? 365).' hari sejak ditugaskan' }}</dd>
        <dt>Urutan materi</dt><dd>{{ $program->sequential ? 'Wajib berurutan' : 'Bebas' }}</dd>
        <dt>Versi</dt><dd>@foreach($versions as $v)<a href="{{ route('admin.programs.show', $v) }}" @if($v->id===$program->id) style="font-weight:700" @endif>v{{ $v->version }}</a> ({{ $v->statusLabel() }})@if(!$loop->last) · @endif @endforeach</dd>
    </dl>
    @if ($program->status === 'DRAFT' && count($problems))
        <div class="alert alert-warning" style="margin-top:.75rem">Belum dapat diterbitkan:<ul>@foreach($problems as $p)<li>{{ $p }}</li>@endforeach</ul></div>
    @endif
    @unless ($editable)
        <div class="alert alert-info" style="margin-top:.75rem">Program sudah memiliki peserta. Untuk mengubah materi, gunakan <b>Buat versi baru</b> — peserta versi ini tidak terdampak (BR-09).</div>
    @endunless
</div>

<div class="card">
    <div class="card-header"><h2>Alur belajar</h2><small class="muted">Pre-test → Materi → Post-test → Survei → Sertifikat (BR-17)</small></div>
    <ol class="steps">
        <li><span class="dot">1</span><div class="grow"><b>Pre-test</b> — {{ $pre?->question_count }} soal, {{ $pre?->duration_minutes }} menit (tidak menentukan kelulusan)</div></li>
        @foreach ($program->modules as $mi => $module)
            <li style="display:block">
                <div class="row">
                    <span class="dot">M{{ $mi + 1 }}</span>
                    <b class="grow">{{ $module->title }}</b>
                    @if ($editable)
                        <form method="post" action="{{ route('admin.modules.move', [$module, 'up']) }}">@csrf<button class="btn btn-sm btn-secondary" aria-label="Naikkan">↑</button></form>
                        <form method="post" action="{{ route('admin.modules.move', [$module, 'down']) }}">@csrf<button class="btn btn-sm btn-secondary" aria-label="Turunkan">↓</button></form>
                        <details><summary class="btn btn-sm btn-secondary">Ubah</summary>
                            <form method="post" action="{{ route('admin.modules.update', $module) }}" class="row" style="margin-top:.5rem">@csrf @method('put')
                                <input name="title" value="{{ $module->title }}" required style="max-width:260px"><button class="btn btn-sm">Simpan</button></form>
                        </details>
                        <form method="post" action="{{ route('admin.modules.destroy', $module) }}">@csrf @method('delete')<button class="btn btn-sm btn-danger" data-confirm="Hapus modul beserta materinya?">Hapus</button></form>
                    @endif
                </div>
                <div class="table-wrap" style="margin:.5rem 0 0 2.4rem">
                <table>
                    @foreach ($module->lessons as $lesson)
                        <tr>
                            <td style="width:2rem">{{ $lesson->icon() }}</td>
                            <td>{{ $lesson->title }}<br><small class="muted">{{ $lesson->typeLabel() }}
                                @if($lesson->file) · {{ $lesson->file->humanSize() }}
                                    @if($lesson->file->duration_seconds) · {{ gmdate('i:s', $lesson->file->duration_seconds) }} @endif
                                    @if($lesson->file->page_count) · {{ $lesson->file->page_count }} halaman @endif
                                    @if($lesson->type === 'VIDEO' && $lesson->file->renditions) · {{ implode('/', $lesson->file->videoVariants()) }} @endif
                                @endif</small></td>
                            <td>@if($lesson->file)<span class="badge {{ $statusBadge[$lesson->file->processing_status][1] }}" data-file-status="{{ $lesson->id }}">{{ $statusBadge[$lesson->file->processing_status][0] }}</span>
                                @if($lesson->file->processing_status === 'FAILED')<form method="post" action="{{ route('admin.lessons.retry', $lesson) }}" style="display:inline">@csrf<button class="btn-link">coba lagi</button></form>@endif
                                @endif</td>
                            <td class="nowrap">
                                @if ($editable)
                                <form method="post" action="{{ route('admin.lessons.move', [$lesson, 'up']) }}" style="display:inline">@csrf<button class="btn btn-sm btn-secondary" aria-label="Naikkan">↑</button></form>
                                <form method="post" action="{{ route('admin.lessons.move', [$lesson, 'down']) }}" style="display:inline">@csrf<button class="btn btn-sm btn-secondary" aria-label="Turunkan">↓</button></form>
                                <a class="btn btn-sm btn-secondary" href="{{ route('admin.lessons.edit', $lesson) }}">Ubah</a>
                                <form method="post" action="{{ route('admin.lessons.destroy', $lesson) }}" style="display:inline">@csrf @method('delete')<button class="btn btn-sm btn-danger" data-confirm="Hapus materi ini?">Hapus</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
                @if ($editable)<a class="btn btn-sm btn-secondary" style="margin-top:.4rem" href="{{ route('admin.lessons.create', $module) }}">+ Materi</a>@endif
                </div>
            </li>
        @endforeach
        @if ($editable)
        <li>
            <span class="dot">+</span>
            <form method="post" action="{{ route('admin.modules.store', $program) }}" class="row grow">@csrf
                <input name="title" placeholder="Judul modul baru" required style="max-width:320px"><button class="btn btn-sm">Tambah modul</button></form>
        </li>
        @endif
        <li><span class="dot">✓</span><div class="grow"><b>Post-test</b> — {{ $post?->question_count }} soal, {{ $post?->duration_minutes }} menit, lulus ≥ {{ $post?->passing_score }}, {{ $post?->max_attempts }} percobaan/siklus, jeda {{ $post?->retry_cooldown_minutes }} menit</div></li>
        @if ($program->is_blended)<li><span class="dot">🏫</span><div class="grow"><b>Sesi tatap muka</b> — kehadiran menjadi syarat selesai</div></li>@endif
        <li><span class="dot">★</span><div class="grow"><b>Survei reaksi</b> — {{ $program->surveyTemplate?->name ?? 'Template default' }} → <b>Sertifikat</b></div></li>
    </ol>
</div>

<div class="card">
    <h2>Pengaturan tes &amp; survei</h2>
    <p class="muted">Bank soal: @php $act = $questionStats->where('is_active', true); @endphp
        {{ $act->sum('n') }} soal aktif (@foreach(\App\Models\Question::DIFFICULTIES as $k=>$v){{ $v }} {{ $act->firstWhere('difficulty',$k)?->n ?? 0 }}@if(!$loop->last), @endif @endforeach).
        Pre-test dan post-test memakai bank soal yang sama agar gain score sebanding (BR-28). <a href="{{ route('admin.questions.index', $program) }}">Kelola bank soal →</a></p>
    <form method="post" action="{{ route('admin.assessments.update', $program) }}">
        @csrf @method('put')
        <div class="grid grid-2">
        @foreach (['PRE' => $pre, 'POST' => $post] as $k => $a)
            <fieldset style="border:1px solid var(--neutral-200);border-radius:8px;padding:.75rem">
                <legend><b>{{ $k === 'PRE' ? 'Pre-test' : 'Post-test' }}</b></legend>
                <div class="form-grid">
                    <div class="field"><label>Jumlah soal</label><input type="number" name="{{ $k }}[question_count]" value="{{ $a->question_count }}" min="1"></div>
                    <div class="field"><label>Durasi (menit)</label><input type="number" name="{{ $k }}[duration_minutes]" value="{{ $a->duration_minutes }}" min="1"></div>
                    <div class="field"><label>% Mudah</label><input type="number" name="{{ $k }}[mix_easy]" value="{{ $a->difficulty_mix['EASY'] ?? 30 }}" min="0" max="100"></div>
                    <div class="field"><label>% Sedang</label><input type="number" name="{{ $k }}[mix_medium]" value="{{ $a->difficulty_mix['MEDIUM'] ?? 50 }}" min="0" max="100"></div>
                    <div class="field"><label>% Sulit</label><input type="number" name="{{ $k }}[mix_hard]" value="{{ $a->difficulty_mix['HARD'] ?? 20 }}" min="0" max="100"></div>
                    @if ($k === 'POST')
                        <div class="field"><label>Passing grade</label><input type="number" name="POST[passing_score]" value="{{ $a->passing_score }}" min="1" max="100"></div>
                        <div class="field"><label>Percobaan per siklus</label><input type="number" name="POST[max_attempts]" value="{{ $a->max_attempts }}" min="1" max="10"><div class="hint">3 = 1 + 2 remedial (BR-23)</div></div>
                        <div class="field"><label>Jeda remedial (menit)</label><input type="number" name="POST[retry_cooldown_minutes]" value="{{ $a->retry_cooldown_minutes }}" min="0"></div>
                    @endif
                </div>
                @if ($k === 'POST')<label class="check"><input type="checkbox" name="POST[show_explanation]" value="1" @checked($a->show_explanation)> Tampilkan pembahasan setelah lulus (kunci jawaban tetap tidak ditampilkan)</label>@endif
            </fieldset>
        @endforeach
        </div>
        <div class="field" style="margin-top:.75rem;max-width:420px"><label for="survey_template_id">Template survei reaksi</label>
            <select id="survey_template_id" name="survey_template_id"><option value="">Default ({{ \App\Models\SurveyTemplate::default()?->name }})</option>
                @foreach (\App\Models\SurveyTemplate::where('is_active', true)->get() as $t)<option value="{{ $t->id }}" @selected($program->survey_template_id===$t->id)>{{ $t->name }}</option>@endforeach</select></div>
        <button class="btn">Simpan pengaturan</button>
    </form>
</div>

@if ($hasProcessing)
@push('scripts')
<script>
// Muat ulang halaman otomatis setelah pemrosesan berkas selesai
(function poll() {
  setTimeout(function () {
    fetch('{{ route('admin.programs.processing', $program) }}', {headers: {'Accept': 'application/json'}}).then(r => r.json()).then(function (s) {
      var pending = Object.values(s).some(v => v === 'PENDING' || v === 'PROCESSING');
      if (!pending) location.reload(); else poll();
    });
  }, 5000);
})();
</script>
@endpush
@endif
@endsection
