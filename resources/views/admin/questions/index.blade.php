@extends('layouts.app')
@section('title', 'Bank Soal')
@section('content')
@include('admin.programs._tabs')
<div class="grid grid-2">
    <div class="card">
        <h2>Tambah soal</h2>
        <p class="muted">Tipe: pilihan ganda, benar/salah, pilihan ganda kompleks. Setiap soal punya kunci jawaban & tingkat kesulitan.</p>
        <a class="btn" href="{{ route('admin.questions.create', $program) }}">+ Soal baru</a>
    </div>
    <div class="card">
        <h2>Impor dari Excel</h2>
        <form method="post" action="{{ route('admin.questions.import', $program) }}" enctype="multipart/form-data" class="row">@csrf
            <input type="file" name="file" accept=".xlsx,.csv" required style="max-width:260px"><button class="btn btn-secondary">Impor</button></form>
        <p style="margin-top:.5rem"><a href="{{ route('admin.questions.template') }}">⬇ Unduh template soal</a></p>
    </div>
</div>
<form class="filters" method="get">
    <div class="field"><label>Tingkat</label><select name="difficulty"><option value="">Semua</option>@foreach(\App\Models\Question::DIFFICULTIES as $k=>$v)<option value="{{ $k }}" @selected(request('difficulty')===$k)>{{ $v }}</option>@endforeach</select></div>
    <div class="field"><label>Modul</label><select name="module_id"><option value="">Semua</option>@foreach($program->modules as $m)<option value="{{ $m->id }}" @selected(request('module_id')==$m->id)>{{ $m->title }}</option>@endforeach</select></div>
    <button class="btn btn-secondary">Terapkan</button>
</form>
<div class="card table-wrap">
<table>
    <thead><tr><th>#</th><th>Soal</th><th>Tipe</th><th>Tingkat</th><th>Modul</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    @forelse ($questions as $q)
        <tr @unless($q->is_active) style="opacity:.6" @endunless>
            <td>{{ $q->id }}</td>
            <td>{{ $q->text }}
                <ul style="margin:.3rem 0 0;padding-left:1.1rem;font-size:.85rem">@foreach($q->options as $o)<li @if($o->is_correct) style="color:var(--success-fg);font-weight:600" @endif>{{ $o->text }} @if($o->is_correct)✓@endif</li>@endforeach</ul></td>
            <td>{{ ['SINGLE'=>'PG','MULTI'=>'PG Kompleks','TRUE_FALSE'=>'B/S'][$q->type] }}</td>
            <td>{{ \App\Models\Question::DIFFICULTIES[$q->difficulty] }}</td>
            <td>{{ $q->module?->title ?? '—' }}</td>
            <td>{!! $q->is_active ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-neutral">Nonaktif</span>' !!}</td>
            <td class="nowrap">
                <a class="btn btn-sm btn-secondary" href="{{ route('admin.questions.edit', $q) }}">Ubah</a>
                <form method="post" action="{{ route('admin.questions.toggle', $q) }}" style="display:inline">@csrf<button class="btn btn-sm btn-secondary">{{ $q->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                <form method="post" action="{{ route('admin.questions.destroy', $q) }}" style="display:inline">@csrf @method('delete')<button class="btn btn-sm btn-danger" data-confirm="Hapus soal?">Hapus</button></form>
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="empty">Bank soal masih kosong.</td></tr>
    @endforelse
    </tbody>
</table>
{{ $questions->links() }}
</div>
@endsection
