<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #1F2937; }
    .frame { position: absolute; top: 18px; left: 18px; right: 18px; bottom: 18px; border: 6px solid #003854; }
    .inner { position: absolute; top: 30px; left: 30px; right: 30px; bottom: 30px; border: 1.5px solid #80B2CC; }
    .band { position: absolute; top: 30px; left: 30px; right: 30px; height: 90px; background: #003854; color: #fff; }
    .band .t { position: absolute; left: 40px; top: 22px; font-size: 13px; letter-spacing: 3px; }
    .band .n { position: absolute; left: 40px; top: 44px; font-size: 22px; font-weight: bold; }
    .band .no { position: absolute; right: 40px; top: 36px; font-size: 12px; }
    .body { position: absolute; top: 150px; left: 70px; right: 70px; text-align: center; }
    .lbl { font-size: 13px; color: #4B5563; letter-spacing: 2px; text-transform: uppercase; }
    .name { font-size: 34px; font-weight: bold; color: #003854; margin: 10px 0 2px; }
    .nik { font-size: 13px; color: #4B5563; }
    .prog { font-size: 22px; font-weight: bold; color: #006699; margin: 10px 0; }
    .meta { margin-top: 18px; font-size: 13px; }
    .meta td { padding: 3px 14px; }
    .foot { position: absolute; left: 70px; right: 70px; bottom: 60px; }
    .qr { position: absolute; right: 0; bottom: 0; text-align: center; font-size: 9px; color: #4B5563; }
    .qr img { width: 105px; height: 105px; }
    .sign { position: absolute; left: 0; bottom: 0; font-size: 12px; }
    .sign .line { border-top: 1px solid #1F2937; width: 220px; margin-top: 50px; padding-top: 4px; }
</style>
</head>
<body>
<div class="frame"></div><div class="inner"></div>
<div class="band">
    <div class="t">BUKAKA LEARNING JOURNEY</div>
    <div class="n">SERTIFIKAT PENYELESAIAN TRAINING</div>
    <div class="no">No. {{ $cert->certificate_no }}</div>
</div>
<div class="body">
    <div class="lbl">Diberikan kepada</div>
    <div class="name">{{ $a->employee->full_name }}</div>
    <div class="nik">NIK {{ $a->employee->nik }}</div>
    <div class="lbl" style="margin-top:22px">telah menyelesaikan training wajib</div>
    <div class="prog">{{ $a->program->name }}</div>
    <table class="meta" align="center">
        <tr><td>Tanggal selesai</td><td><b>{{ $cert->issued_at->translatedFormat('d F Y') }}</b></td>
            <td>Nilai post-test</td><td><b>{{ $a->final_score ?? '-' }}</b></td></tr>
        @if ($cert->valid_until)<tr><td>Berlaku sampai</td><td colspan="3"><b>{{ $cert->valid_until->translatedFormat('d F Y') }}</b></td></tr>@endif
    </table>
</div>
<div class="foot">
    <div class="sign">Learning &amp; Development — Human Capital<div class="line">Diterbitkan otomatis oleh sistem BLJ</div></div>
    <div class="qr"><img src="{{ $qr }}" alt="QR"><br>Pindai untuk verifikasi</div>
</div>
</body>
</html>
