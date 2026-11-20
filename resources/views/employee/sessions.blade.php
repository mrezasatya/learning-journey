@extends('layouts.app')
@section('title', 'Jadwal Klasikal')
@section('content')
<div class="page-head"><div><h1>Jadwal sesi tatap muka</h1><p class="muted">Pindai QR yang ditampilkan panitia saat sesi untuk mencatat kehadiran.</p></div></div>
<div class="card table-wrap"><table>
    <thead><tr><th>Sesi</th><th>Waktu</th><th>Lokasi</th><th>Kehadiran</th></tr></thead>
    <tbody>@forelse ($items as $p)
        <tr><td>{{ $p->session->title }}<br><small>{{ $p->session->program->name }}</small></td>
            <td class="nowrap">{{ $p->session->start_at->translatedFormat('d M Y H:i') }}</td><td>{{ $p->session->location }}</td>
            <td><span class="badge {{ ['INVITED'=>'badge-neutral','PRESENT'=>'badge-success','ABSENT'=>'badge-danger'][$p->attendance_status] }}">{{ \App\Models\SessionParticipant::STATUSES[$p->attendance_status] }}</span></td></tr>
    @empty <tr><td colspan="4" class="empty">Tidak ada jadwal sesi tatap muka.</td></tr> @endforelse</tbody>
</table></div>
@endsection
