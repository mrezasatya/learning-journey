@extends('layouts.app')
@section('title', 'Aturan Penugasan')
@section('content')
@php
    $describe = function ($rule) use ($options) {
        $items = $rule->conditions['items'] ?? [];
        if (! $items) return 'Semua karyawan aktif';
        $parts = array_map(function ($c) use ($options) {
            $vals = array_map(fn ($v) => $options[$c['field']][$v] ?? $v, $c['values']);
            return \App\Models\AssignmentRule::FIELDS[$c['field']].($c['op'] === 'not_in' ? ' bukan ' : ' = ').implode(' / ', $vals);
        }, $items);
        return implode(($rule->conditions['logic'] ?? 'AND') === 'OR' ? ' ATAU ' : ' DAN ', $parts);
    };
@endphp
<div class="page-head">
    <div><h1>Aturan Penugasan</h1><p class="muted">Aturan bersifat dinamis: karyawan baru atau yang pindah jabatan/lokasi otomatis mendapat program yang sesuai (BR-11).</p></div>
    <a class="btn" href="{{ route('admin.rules.create', ['program_id' => request('program_id')]) }}">+ Aturan baru</a>
</div>
<form class="filters" method="get">
    <div class="field"><label>Program</label><select name="program_id" onchange="this.form.submit()"><option value="">Semua program</option>
        @foreach($programs as $p)<option value="{{ $p->id }}" @selected(request('program_id')==$p->id)>{{ $p->code }} — {{ $p->name }}</option>@endforeach</select></div>
</form>
<div class="card table-wrap">
<table>
    <thead><tr><th>Nama aturan</th><th>Program</th><th>Kondisi</th><th>Berlaku sejak</th><th class="num">Penugasan</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse ($rules as $r)
        <tr>
            <td>{{ $r->name }}</td>
            <td>{{ $r->program->code }} <small class="muted">v{{ $r->program->version }} · {{ $r->program->typeLabel() }}</small></td>
            <td style="max-width:380px">{{ $describe($r) }}</td>
            <td>{{ $r->effective_from->format('d/m/Y') }}</td>
            <td class="num"><a href="{{ route('admin.assignments.index', ['program_id' => $r->program_id]) }}">{{ $r->assignments_count }}</a></td>
            <td>{!! $r->is_active ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-neutral">Nonaktif</span>' !!}</td>
            <td class="nowrap">
                <a class="btn btn-sm btn-secondary" href="{{ route('admin.rules.edit', $r) }}">Ubah</a>
                <form method="post" action="{{ route('admin.rules.toggle', $r) }}" style="display:inline">@csrf<button class="btn btn-sm btn-secondary">{{ $r->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="empty">Belum ada aturan.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
