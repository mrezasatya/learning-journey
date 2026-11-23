@extends('layouts.app')
@section('title', 'Program & Konten')
@section('content')
<div class="page-head">
    <div><h1>Program &amp; Konten</h1><p class="muted">Draft → Terbit → Diarsipkan. Hanya program Terbit yang dapat ditugaskan.</p></div>
    <div class="row"><a class="btn btn-secondary" href="{{ route('admin.surveys.index') }}">Template survei</a><a class="btn" href="{{ route('admin.programs.create') }}">+ Program baru</a></div>
</div>
<form class="filters" method="get">
    <div class="field"><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status">
        <option value="active" @selected($status==='active')>Draft &amp; Terbit</option>
        @foreach (\App\Models\Program::STATUSES as $k => $v)<option value="{{ $k }}" @selected($status===$k)>{{ $v }}</option>@endforeach
        <option value="all" @selected($status==='all')>Semua</option></select></div>
    <button class="btn btn-secondary">Terapkan</button>
</form>
<div class="card table-wrap">
<table>
    <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th>Kategori</th><th>Versi</th><th class="num">Modul</th><th class="num">Materi</th><th class="num">Peserta</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($programs as $p)
        <tr>
            <td><a href="{{ route('admin.programs.show', $p) }}">{{ $p->code }}</a></td>
            <td>{{ $p->name }} @if($p->is_blended)<span class="badge badge-info">Blended</span>@endif</td>
            <td>{{ $p->typeLabel() }}</td>
            <td>{{ $p->categoryLabel() }}</td>
            <td>v{{ $p->version }}</td>
            <td class="num">{{ $p->modules_count }}</td>
            <td class="num">{{ $p->lessons_count }}</td>
            <td class="num">{{ $p->assignments_count }}</td>
            <td><span class="badge {{ ['DRAFT'=>'badge-warning','PUBLISHED'=>'badge-success','ARCHIVED'=>'badge-neutral'][$p->status] }}">{{ $p->statusLabel() }}</span></td>
        </tr>
    @empty
        <tr><td colspan="9" class="empty">Belum ada program.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
