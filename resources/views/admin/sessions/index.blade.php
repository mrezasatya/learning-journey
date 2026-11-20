@extends('layouts.app')
@section('title', 'Sesi Klasikal')
@section('content')
<div class="page-head"><div><h1>Sesi klasikal / tatap muka</h1><p class="muted">Untuk training wajib yang tidak bisa online (mis. praktik K3) dan program blended (BR-42, BR-45).</p></div></div>
<div class="grid" style="grid-template-columns:minmax(0,2fr) minmax(280px,1fr)">
<div>
    <nav class="tabs">
        <a href="{{ route('admin.sessions.index') }}" class="{{ request('waktu','mendatang')==='mendatang' ? 'active' : '' }}">Mendatang</a>
        <a href="{{ route('admin.sessions.index', ['waktu' => 'lalu']) }}" class="{{ request('waktu')==='lalu' ? 'active' : '' }}">Sudah lewat</a>
    </nav>
    <div class="card table-wrap"><table>
        <thead><tr><th>Sesi</th><th>Waktu</th><th>Lokasi</th><th class="num">Hadir / Peserta / Kapasitas</th></tr></thead>
        <tbody>@forelse ($sessions as $s)
            <tr><td><a href="{{ route('admin.sessions.show', $s) }}">{{ $s->title }}</a><br><small>{{ $s->program->code }} — {{ $s->program->name }}</small></td>
                <td class="nowrap">{{ $s->start_at->translatedFormat('d M Y') }}<br><small>{{ $s->start_at->format('H:i') }}–{{ $s->end_at->format('H:i') }}</small></td>
                <td>{{ $s->location }}</td><td class="num">{{ $s->present_count }} / {{ $s->participants_count }} / {{ $s->capacity }}</td></tr>
        @empty <tr><td colspan="4" class="empty">Belum ada sesi.</td></tr> @endforelse</tbody>
    </table>{{ $sessions->links() }}</div>
</div>
<form method="post" action="{{ route('admin.sessions.store') }}" class="card">
    @csrf
    <h2>Buat sesi</h2>
    <div class="field"><label>Program</label><select name="program_id" required>@foreach($programs as $p)<option value="{{ $p->id }}" @selected(old('program_id')==$p->id)>{{ $p->code }} — {{ $p->name }}@if($p->is_blended) (blended)@endif</option>@endforeach</select></div>
    <div class="field"><label>Judul sesi</label><input name="title" value="{{ old('title') }}" required placeholder="mis. Praktik APAR – Batch 1"></div>
    <div class="field"><label>Mulai</label><input type="datetime-local" name="start_at" value="{{ old('start_at') }}" required></div>
    <div class="field"><label>Selesai</label><input type="datetime-local" name="end_at" value="{{ old('end_at') }}" required></div>
    <div class="field"><label>Lokasi</label><input name="location" value="{{ old('location') }}" required></div>
    <div class="field"><label>Kapasitas</label><input type="number" name="capacity" value="{{ old('capacity', 30) }}" min="1" required></div>
    <div class="field"><label>Catatan</label><textarea name="notes" rows="2">{{ old('notes') }}</textarea></div>
    <button class="btn btn-block">Simpan sesi</button>
</form>
</div>
@endsection
