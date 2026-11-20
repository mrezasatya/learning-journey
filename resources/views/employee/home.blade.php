@extends('layouts.app')
@section('title', 'Beranda')
@section('content')
<div class="page-head">
    <div><h1>Halo, {{ \Illuminate\Support\Str::of($employee->full_name)->before(' ') }} 👋</h1>
        <p class="muted">Berikut training wajib Anda. Selesaikan sebelum tenggat.</p></div>
</div>
<div class="grid grid-3">
    <div class="card stat">
        <div class="label">Training wajib selesai ({{ now()->year }})</div>
        <div class="value">{{ $doneCount }}<small> / {{ $totalCount }}</small></div>
        <div class="progress" style="margin-top:.5rem"><span style="width:{{ $totalCount ? round($doneCount / $totalCount * 100) : 0 }}%"></span></div>
    </div>
    <div class="card stat">
        <div class="label">Tenggat terdekat</div>
        @if ($nearest)
            <div class="value" style="font-size:1.3rem">{{ $nearest->due_at->translatedFormat('d M Y') }}</div>
            <small>{{ $nearest->program->name }}</small>
        @else
            <div class="value" style="font-size:1.3rem">—</div><small>Tidak ada training terbuka</small>
        @endif
    </div>
    <div class="card stat">
        <div class="label">Overdue</div>
        <div class="value" style="{{ $overdueCount ? 'color:var(--danger)' : '' }}">{{ $overdueCount }}</div>
        <small>{{ $overdueCount ? 'Segera selesaikan — atasan Anda menerima pemberitahuan.' : 'Tidak ada. Pertahankan!' }}</small>
    </div>
</div>

<h2 style="margin-top:.5rem">Training Wajib Saya</h2>
@if ($cards->isEmpty())
    <div class="card empty">🎉 Semua training wajib Anda sudah selesai.</div>
@else
<div class="grid grid-3">
    @foreach ($cards as $c)
        @php $a = $c['a']; $d = $a->daysLeft(); @endphp
        <article class="card program-card">
            <div class="thumb">@if($a->program->thumbnail)<img src="{{ $a->program->thumbnail->signedUrl() }}" alt="">@else{{ $a->program->code }}@endif</div>
            <div class="meta"><span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span><span>{{ $a->program->categoryLabel() }}</span>@if($a->period_year)<span>Periode {{ $a->period_year }}</span>@endif</div>
            <h3>{{ $a->program->name }}</h3>
            <div class="meta">
                <span>Tenggat <b>{{ $a->due_at->translatedFormat('d M Y') }}</b></span>
                <span class="{{ $d < 0 ? 'text-danger' : '' }}">({{ $d < 0 ? 'lewat '.abs($d).' hari' : ($d === 0 ? 'hari ini' : $d.' hari lagi') }})</span>
            </div>
            <div><div class="progress"><span style="width:{{ $c['percent'] }}%"></span></div><small>{{ $c['percent'] }}% · {{ [
                'PRE_TEST' => 'Langkah berikut: pre-test', 'LESSONS' => 'Langkah berikut: materi', 'POST_TEST' => 'Langkah berikut: post-test',
                'SURVEY' => 'Langkah berikut: survei', 'SESSION' => 'Menunggu sesi tatap muka', 'DONE' => 'Selesai'][$c['step']] ?? '' }}</small></div>
            @if ($a->cycle_no > 1 && $c['step'] === 'LESSONS')<div class="alert alert-warning" style="margin:0;padding:.5rem .75rem">Perlu mengulang materi sebelum post-test berikutnya.</div>@endif
            <div class="actions">
                <a class="btn btn-block" href="{{ $c['resume'] }}">{{ $a->started_at ? 'Lanjutkan' : 'Mulai' }}</a>
                <a class="btn btn-secondary" href="{{ route('learn.program', $a) }}" aria-label="Detail program">ⓘ</a>
            </div>
        </article>
    @endforeach
</div>
@endif

@if ($recentDone->isNotEmpty())
    <h2>Baru selesai</h2>
    <div class="card table-wrap"><table><tbody>
        @foreach ($recentDone as $a)
            <tr><td>{{ $a->program->name }}<br><small>Selesai {{ $a->completed_at->translatedFormat('d M Y') }} · Nilai {{ $a->final_score ?? '—' }}</small></td>
                <td><span class="badge badge-success">{{ $a->statusLabel() }}</span></td>
                <td class="nowrap">@if($a->certificate)<a class="btn btn-sm btn-secondary" href="{{ route('learn.certificate', $a) }}">⬇ Sertifikat</a>@endif</td></tr>
        @endforeach
    </tbody></table></div>
@endif
@endsection
