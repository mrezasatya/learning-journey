@extends('layouts.app')
@section('title', 'Riwayat & Sertifikat')
@section('content')
<div class="page-head"><div><h1>Riwayat &amp; Sertifikat</h1><p class="muted">Seluruh training wajib Anda, termasuk periode sebelumnya.</p></div></div>
<div class="card table-wrap">
<table>
    <thead><tr><th>Program</th><th>Periode</th><th>Status</th><th>Selesai</th><th class="num">Nilai</th><th>Sertifikat</th></tr></thead>
    <tbody>
    @forelse ($items as $a)
        <tr>
            <td><a href="{{ route('learn.program', $a) }}">{{ $a->program->name }}</a><br><small>{{ $a->program->code }} · {{ $a->program->typeLabel() }}</small></td>
            <td>{{ $a->periodLabel() }}</td>
            <td><span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span></td>
            <td>{{ $a->completed_at?->format('d/m/Y') ?? '—' }}</td>
            <td class="num">{{ $a->final_score ?? '—' }}</td>
            <td>@if ($a->certificate)
                    <a class="btn btn-sm btn-secondary" href="{{ route('learn.certificate', $a) }}">⬇ PDF</a><br>
                    <small>{{ $a->certificate->certificate_no }}@if($a->certificate->valid_until) · berlaku s.d. {{ $a->certificate->valid_until->format('d/m/Y') }}@endif</small>
                @else — @endif</td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">Belum ada riwayat training.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
