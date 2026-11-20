@extends('layouts.app')
@section('title', 'Detail Penugasan')
@section('content')
<div class="page-head">
    <div><h1>{{ $a->employee->full_name }} — {{ $a->program->code }}</h1>
        <p class="muted">{{ $a->program->name }} (v{{ $a->program_version }}) · Periode {{ $a->periodLabel() }} · <span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span></p></div>
    <a class="btn btn-secondary" href="{{ url()->previous() }}">← Kembali</a>
</div>
<div class="grid grid-2">
    <div class="card">
        <h2>Ringkasan</h2>
        <dl class="kv">
            <dt>NIK</dt><dd><a href="{{ route('admin.employees.show', $a->employee) }}">{{ $a->employee->nik }}</a></dd>
            <dt>Atasan</dt><dd>{{ $a->employee->supervisor?->full_name ?? '—' }}</dd>
            <dt>Sumber</dt><dd>{{ $a->source === 'RULE' ? 'Aturan: '.($a->rule?->name ?? '-') : 'Manual' }}</dd>
            <dt>Ditugaskan</dt><dd>{{ $a->assigned_at->format('d/m/Y') }}</dd>
            <dt>Tenggat</dt><dd>{{ $a->due_at->format('d/m/Y') }} @if(($d=$a->daysLeft())!==null)<small class="{{ $d<0?'text-danger':'' }}">({{ $d<0 ? abs($d).' hari lewat' : $d.' hari lagi' }})</small>@endif</dd>
            <dt>Mulai</dt><dd>{{ $a->started_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            <dt>Selesai</dt><dd>{{ $a->completed_at?->format('d/m/Y H:i') ?? '—' }} @if($a->completion_method) ({{ $a->completion_method === 'MANUAL' ? 'manual/offline' : 'online' }}) @endif</dd>
            <dt>Siklus belajar</dt><dd>{{ $a->cycle_no }} @if($a->cycle_no > 1)<small>(pernah reset materi — BR-24)</small>@endif</dd>
            <dt>Pre-test</dt><dd>{{ $a->pre_score ?? '—' }}</dd>
            <dt>Nilai akhir</dt><dd>{{ $a->final_score ?? '—' }}</dd>
            @if($a->certificate)<dt>Sertifikat</dt><dd>{{ $a->certificate->certificate_no }}</dd>@endif
        </dl>
    </div>
    <div class="card">
        <h2>Pengecualian / perpanjangan</h2>
        @if ($a->isOpen())
        <form method="post" action="{{ route('admin.assignments.exempt', $a) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                <div class="field"><label>Jenis</label><select name="type" id="ex-type"><option value="EXTEND">Perpanjang tenggat</option><option value="EXEMPT">Kecualikan</option></select></div>
                <div class="field" id="ex-due"><label>Tenggat baru</label><input type="date" name="new_due_at" value="{{ old('new_due_at') }}"></div>
                <div class="field"><label>Kategori alasan</label><select name="reason_category">@foreach(\App\Models\Exemption::REASONS as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            </div>
            <div class="field"><label>Alasan (wajib)</label><textarea name="reason" rows="2" required minlength="10">{{ old('reason') }}</textarea></div>
            <div class="field"><label>Bukti (opsional, PDF/JPG)</label><input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png"></div>
            <button class="btn btn-secondary" data-confirm="Simpan pengecualian/perpanjangan? Tercatat di audit log.">Simpan</button>
        </form>
        @push('scripts')<script>(function(){var t=document.getElementById('ex-type');function s(){document.getElementById('ex-due').classList.toggle('hidden',t.value!=='EXTEND');}t.addEventListener('change',s);s();})();</script>@endpush
        @endif
        @forelse ($a->exemptions as $ex)
            <div style="border-top:1px solid var(--neutral-200);padding-top:.5rem;margin-top:.5rem">
                <b>{{ $ex->type === 'EXTEND' ? 'Perpanjangan' : 'Pengecualian' }}</b> · {{ \App\Models\Exemption::REASONS[$ex->reason_category] }} · {{ $ex->created_at->format('d/m/Y') }} oleh {{ $ex->approver?->displayName() }}<br>
                @if($ex->type==='EXTEND')<small>{{ $ex->old_due_at?->format('d/m/Y') }} → {{ $ex->new_due_at?->format('d/m/Y') }}</small><br>@endif
                {{ $ex->reason }} @if($ex->evidence)· <a href="{{ $ex->evidence->signedUrl() }}" target="_blank">bukti</a>@endif
            </div>
        @empty
            @unless($a->isOpen())<p class="muted">Tidak ada.</p>@endunless
        @endforelse
    </div>
</div>
@includeIf('admin.assignments._learning')
@endsection
