<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Daftar hadir — {{ $s->title }}</title>
<style>body{font-family:system-ui,Arial,sans-serif;margin:24px;color:#1F2937}h1{font-size:18px;color:#003854;margin:0}table{width:100%;border-collapse:collapse;margin-top:16px;font-size:13px}td,th{border:1px solid #9CA3AF;padding:8px;text-align:left}th{background:#F0F6F9}.sig{height:34px}</style></head>
<body onload="window.print()">
<h1>DAFTAR HADIR — {{ $s->program->name }}</h1>
<p>{{ $s->title }} · {{ $s->start_at->translatedFormat('l, d F Y H:i') }}–{{ $s->end_at->format('H:i') }} · {{ $s->location }}</p>
<table><thead><tr><th>No</th><th>NIK</th><th>Nama</th><th>Departemen</th><th>Nilai</th><th>Tanda tangan</th></tr></thead><tbody>
@foreach ($s->participants->sortBy('employee.full_name')->values() as $i => $p)
<tr><td>{{ $i + 1 }}</td><td>{{ $p->employee->nik }}</td><td>{{ $p->employee->full_name }}</td><td>{{ $p->employee->department?->name }}</td><td></td><td class="sig"></td></tr>
@endforeach
@for ($i = 0; $i < 5; $i++)<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td class="sig"></td></tr>@endfor
</tbody></table>
<p style="margin-top:32px">Instruktur: ____________________ &nbsp;&nbsp; Admin L&amp;D: ____________________</p>
</body></html>
