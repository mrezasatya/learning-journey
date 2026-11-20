@extends('layouts.app')
@section('title', 'Master Data')
@section('content')
<div class="page-head"><div><h1>Master Data</h1><p class="muted">Data yang sudah dipakai tidak dapat dihapus — cukup dinonaktifkan.</p></div></div>
<nav class="tabs">
    @foreach ($types as $key => [$tlabel])
        <a href="{{ route('admin.master.index', $key) }}" class="{{ $key === $type ? 'active' : '' }}">{{ $tlabel }}</a>
    @endforeach
</nav>
<div class="grid" style="grid-template-columns: minmax(0,2fr) minmax(260px,1fr)">
<div class="card table-wrap">
    <table>
        <thead><tr>@foreach ($fields as $f => $fl)<th>{{ $fl }}</th>@endforeach<th class="num">Dipakai</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        @foreach ($items as $item)
            <tr>
                @foreach ($fields as $f => $fl)
                    <td>{{ $f === 'division_id' ? $item->division?->name : ($f === 'type' ? (\App\Models\WorkLocation::TYPES[$item->type] ?? $item->type) : $item->{$f}) }}</td>
                @endforeach
                <td class="num">{{ $usage[$item->id] }}</td>
                <td>{!! $item->is_active ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-neutral">Nonaktif</span>' !!}</td>
                <td class="nowrap">
                    <a class="btn btn-sm btn-secondary" href="{{ route('admin.master.index', [$type, 'edit' => $item->id]) }}">Ubah</a>
                    <form method="post" action="{{ route('admin.master.toggle', [$type, $item->id]) }}" style="display:inline">@csrf<button class="btn btn-sm btn-secondary">{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                    @if ($usage[$item->id] === 0)
                        <form method="post" action="{{ route('admin.master.destroy', [$type, $item->id]) }}" style="display:inline">@csrf @method('delete')<button class="btn btn-sm btn-danger" data-confirm="Hapus data ini?">Hapus</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="card">
    <h2>{{ $edit ? 'Ubah' : 'Tambah' }} {{ strtolower($label) }}</h2>
    <form method="post" action="{{ $edit ? route('admin.master.update', [$type, $edit->id]) : route('admin.master.store', $type) }}">
        @csrf @if($edit) @method('put') @endif
        @foreach ($fields as $f => $fl)
            <div class="field"><label for="f-{{ $f }}">{{ $fl }}</label>
            @if ($f === 'division_id')
                <select id="f-{{ $f }}" name="division_id"><option value="">—</option>@foreach($divisions as $d)<option value="{{ $d->id }}" @selected(old($f, $edit?->division_id)==$d->id)>{{ $d->name }}</option>@endforeach</select>
            @elseif ($f === 'level')
                <select id="f-{{ $f }}" name="level"><option value="">—</option>@foreach(\App\Models\Position::LEVELS as $lv)<option @selected(old($f, $edit?->level)===$lv)>{{ $lv }}</option>@endforeach</select>
            @elseif ($f === 'type')
                <select id="f-{{ $f }}" name="type">@foreach(\App\Models\WorkLocation::TYPES as $k=>$v)<option value="{{ $k }}" @selected(old($f, $edit?->type)===$k)>{{ $v }}</option>@endforeach</select>
            @else
                <input id="f-{{ $f }}" name="{{ $f }}" value="{{ old($f, $edit?->{$f}) }}" @if($f!=='region') required @endif>
            @endif
            </div>
        @endforeach
        <div class="row"><button class="btn">Simpan</button>@if($edit)<a class="btn btn-secondary" href="{{ route('admin.master.index', $type) }}">Batal</a>@endif</div>
    </form>
</div>
</div>
@endsection
