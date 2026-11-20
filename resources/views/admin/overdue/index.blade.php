@extends('layouts.app')
@section('title', 'Overdue & Teguran')
@section('content')
<div class="page-head">
    <div><h1>Overdue &amp; Rekomendasi Teguran</h1>
        <p class="muted">Daftar karyawan overdue tanpa pengecualian. <b>Sistem tidak menerbitkan teguran.</b> Admin L&amp;D memverifikasi, lalu proses teguran berjalan melalui Industrial Relations sesuai Peraturan Perusahaan/PKB (BR-40).</p></div>
    <a class="btn btn-secondary" href="{{ route('admin.overdue.export', request()->query()) }}">⬇ Ekspor Excel</a>
</div>
<nav class="tabs">
    @foreach (\App\Models\DisciplinaryFollowup::STATUSES + ['all' => 'Semua'] as $k => $v)
        <a href="{{ route('admin.overdue.index', ['status' => $k]) }}" class="{{ $status === $k ? 'active' : '' }}">{{ $v }} @if($k!=='all')({{ $counts[$k] ?? 0 }})@endif</a>
    @endforeach
</nav>
<form class="filters" method="get"><input type="hidden" name="status" value="{{ $status }}">
    <div class="field"><label>Cari NIK/nama</label><input type="search" name="q" value="{{ request('q') }}"></div><button class="btn btn-secondary">Cari</button></form>
<div class="card table-wrap">
<table>
    <thead><tr><th>Karyawan</th><th>Program</th><th>Tenggat</th><th>Status training</th><th>Teguran</th><th style="min-width:280px">Tindak lanjut</th></tr></thead>
    <tbody>
    @forelse ($items as $f)
        @php $a = $f->assignment; @endphp
        <tr>
            <td><a href="{{ route('admin.assignments.show', $a) }}">{{ $a->employee->full_name }}</a><br><small>{{ $a->employee->nik }} · {{ $a->employee->department?->name }}<br>Atasan: {{ $a->employee->supervisor?->full_name ?? '—' }}</small></td>
            <td>{{ $a->program->code }}<br><small>{{ $a->program->name }} · {{ $a->periodLabel() }}</small></td>
            <td class="nowrap">{{ $a->due_at->format('d/m/Y') }}<br><small class="text-danger">{{ (int) $a->due_at->diffInDays(now()) }} hari lewat</small></td>
            <td><span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span></td>
            <td><span class="badge {{ ['RECOMMENDED'=>'badge-warning','VERIFIED'=>'badge-info','ISSUED'=>'badge-danger','CANCELLED'=>'badge-neutral'][$f->status] }}">{{ \App\Models\DisciplinaryFollowup::STATUSES[$f->status] }}</span>
                @if($f->letter_no)<br><small>No. {{ $f->letter_no }} · {{ $f->letter_date?->format('d/m/Y') }}</small>@endif
                @if($f->document)<br><a href="{{ $f->document->signedUrl() }}" target="_blank"><small>dokumen</small></a>@endif
                @if($f->notes)<br><small class="muted">{{ $f->notes }}</small>@endif</td>
            <td>
                @if ($f->status === 'RECOMMENDED')
                    <form method="post" action="{{ route('admin.overdue.update', $f) }}" style="display:inline">@csrf<input type="hidden" name="action" value="verify"><button class="btn btn-sm">Verifikasi</button></form>
                @endif
                @if (in_array($f->status, ['RECOMMENDED', 'VERIFIED']))
                    <details style="margin-top:.4rem"><summary>Catat surat teguran</summary>
                        <form method="post" action="{{ route('admin.overdue.update', $f) }}" enctype="multipart/form-data" style="margin-top:.4rem">@csrf<input type="hidden" name="action" value="issue">
                            <input name="letter_no" placeholder="Nomor surat" required style="margin-bottom:.3rem"><input type="date" name="letter_date" required style="margin-bottom:.3rem">
                            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" style="margin-bottom:.3rem"><button class="btn btn-sm">Simpan</button></form></details>
                    <details style="margin-top:.4rem"><summary>Batalkan</summary>
                        <form method="post" action="{{ route('admin.overdue.update', $f) }}" style="margin-top:.4rem">@csrf<input type="hidden" name="action" value="cancel">
                            <textarea name="notes" rows="2" placeholder="Alasan (mis. kendala akses site, data atasan salah)" required></textarea><button class="btn btn-sm btn-secondary">Batalkan rekomendasi</button></form></details>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">Tidak ada data.</td></tr>
    @endforelse
    </tbody>
</table>
{{ $items->links() }}
</div>
@endsection
