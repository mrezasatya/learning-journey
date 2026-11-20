@extends('layouts.app')
@section('title', 'Audit Log')
@section('content')
<div class="page-head"><div><h1>Audit Log</h1><p class="muted">Catatan seluruh aksi admin. Tidak dapat diubah atau dihapus (BR-47).</p></div></div>
<form class="filters" method="get">
    <div class="field"><label for="action">Aksi (awalan)</label><input id="action" name="action" value="{{ request('action') }}" placeholder="mis. employee, assignment"></div>
    <div class="field"><label for="entity">Entitas</label><select id="entity" name="entity"><option value="">Semua</option>
        @foreach ($entities as $e)<option @selected(request('entity')===$e)>{{ $e }}</option>@endforeach</select></div>
    <div class="field"><label for="from">Dari</label><input id="from" type="date" name="from" value="{{ request('from') }}"></div>
    <div class="field"><label for="to">Sampai</label><input id="to" type="date" name="to" value="{{ request('to') }}"></div>
    <button class="btn btn-secondary">Terapkan</button>
</form>
<div class="card table-wrap">
<table>
    <thead><tr><th>Waktu</th><th>Pelaku</th><th>Aksi</th><th>Entitas</th><th>Perubahan</th><th>IP</th></tr></thead>
    <tbody>
    @forelse ($logs as $log)
        <tr>
            <td class="nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
            <td>{{ $log->actor?->displayName() ?? 'Sistem' }}</td>
            <td><code>{{ $log->action }}</code></td>
            <td>{{ $log->entity }} @if($log->entity_id)#{{ $log->entity_id }}@endif</td>
            <td style="max-width:420px">
                @if ($log->before || $log->after)
                <details><summary>Lihat</summary>
                    @if ($log->before)<div><small>Sebelum</small><pre>{{ json_encode($log->before, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></div>@endif
                    @if ($log->after)<div><small>Sesudah</small><pre>{{ json_encode($log->after, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></div>@endif
                </details>
                @endif
            </td>
            <td>{{ $log->ip_address }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">Belum ada aktivitas.</td></tr>
    @endforelse
    </tbody>
</table>
{{ $logs->links() }}
</div>
@endsection
