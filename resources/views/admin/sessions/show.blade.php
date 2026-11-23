@extends('layouts.app')
@section('title', $s->title)
@section('content')
<div class="page-head">
    <div><h1>{{ $s->title }}</h1><p class="muted">{{ $s->program->code }} — {{ $s->program->name }} · {{ $s->start_at->translatedFormat('l, d M Y H:i') }}–{{ $s->end_at->format('H:i') }} · {{ $s->location }} · Kapasitas {{ $s->capacity }}</p></div>
    <div class="row"><a class="btn btn-secondary" href="{{ route('admin.sessions.print', $s) }}" target="_blank">🖨 Daftar hadir</a><a class="btn btn-secondary" href="{{ route('admin.sessions.index') }}">← Semua sesi</a></div>
</div>
<div class="grid" style="grid-template-columns:minmax(0,2fr) minmax(260px,1fr)">
<div class="card table-wrap">
    <h2>Peserta ({{ $s->participants->count() }}) — hadir {{ $s->participants->where('attendance_status','PRESENT')->count() }}</h2>
    <table><thead><tr><th>Karyawan</th><th>Status</th><th style="min-width:300px">Absensi manual / nilai offline</th></tr></thead><tbody>
    @forelse ($s->participants->sortBy('employee.full_name') as $p)
        <tr><td>{{ $p->employee->full_name }}<br><small>{{ $p->employee->nik }} · {{ $p->employee->workLocation?->name }}</small></td>
            <td><span class="badge {{ ['INVITED'=>'badge-neutral','PRESENT'=>'badge-success','ABSENT'=>'badge-danger'][$p->attendance_status] }}">{{ \App\Models\SessionParticipant::STATUSES[$p->attendance_status] }}</span>
                @if($p->checkin_at)<br><small>{{ $p->checkin_method }} {{ $p->checkin_at->format('H:i') }}</small>@endif
                @if($p->correction_reason)<br><small class="muted">{{ $p->correction_reason }}</small>@endif</td>
            <td><form method="post" action="{{ route('admin.sessions.attendance', $p) }}" class="row">@csrf
                <select name="attendance_status" style="width:auto">@foreach(\App\Models\SessionParticipant::STATUSES as $k=>$v)<option value="{{ $k }}" @selected($p->attendance_status===$k)>{{ $v }}</option>@endforeach</select>
                <input type="number" name="offline_score" min="0" max="100" value="{{ $p->offline_score }}" placeholder="Nilai" style="width:80px">
                <input name="reason" placeholder="Alasan koreksi" style="flex:1;min-width:120px">
                <button class="btn btn-sm">Simpan</button></form></td></tr>
    @empty <tr><td colspan="3" class="empty">Belum ada peserta.</td></tr> @endforelse
    </tbody></table>
</div>
<div>
    <div class="card" style="text-align:center">
        <h2>QR check-in</h2>
        <img src="{{ $qr }}" alt="QR check-in sesi" style="width:220px;height:220px">
        <p><small>Tampilkan di layar/print. Peserta memindai dengan HP lalu login. Berlaku {{ $s->start_at->copy()->subMinutes(30)->format('d/m H:i') }}–{{ $s->end_at->copy()->addMinutes(30)->format('H:i') }}.</small></p>
        <p>{!! $s->isCheckinOpen() ? '<span class="badge badge-success">QR aktif sekarang</span>' : '<span class="badge badge-neutral">QR belum/tidak aktif</span>' !!}</p>
        <details><summary>Tautan</summary><code style="word-break:break-all">{{ $checkinUrl }}</code></details>
    </div>
    <form method="post" action="{{ route('admin.sessions.invite', $s) }}" class="card">@csrf
        <h2>Tambah peserta</h2>
        <p class="muted">Karyawan yang ditugaskan {{ $s->program->code }} dan belum selesai.</p>
        <div class="row" style="margin-bottom:.5rem">
            <select onchange="location.href='?lokasi='+this.value" style="flex:1"><option value="">Semua lokasi</option>@foreach($locations as $l)<option value="{{ $l->id }}" @selected(request('lokasi')==$l->id)>{{ $l->name }}</option>@endforeach</select>
            <button type="button" class="btn btn-sm btn-secondary" onclick="this.form.querySelectorAll('input[type=checkbox]').forEach(c=>c.checked=true)">Pilih semua</button>
        </div>
        <div style="max-height:320px;overflow:auto">
        @forelse ($candidates as $c)
            <label class="check"><input type="checkbox" name="employee_ids[]" value="{{ $c->employee_id }}"> {{ $c->employee->full_name }} <small class="muted">{{ $c->employee->workLocation?->name }}</small></label>
        @empty <p class="muted">Tidak ada kandidat.</p> @endforelse
        </div>
        <button class="btn btn-block" style="margin-top:.5rem">Undang</button>
    </form>
</div>
</div>
@endsection
