# Tahap 8 — Dashboard, Laporan & Teguran

## Tujuan
Menghilangkan rekap kepatuhan manual (indikator: waktu admin turun ≥ 70%) dan memberi L&D
bukti evaluasi Kirkpatrick L1–L2 per program, serta jalur teguran yang terkendali.

## Yang dibangun

| Fitur | Rujukan | Catatan |
|---|---|---|
| Kartu ringkas: % selesai, overdue, jatuh tempo ≤ 30 hari, belum mulai/berjalan | US-25 AC1 | Admin otomatis diarahkan ke dashboard setelah login |
| Completion rate per **program, divisi, departemen, lokasi, tipe lokasi (HO/Plant/Site)** | US-25 AC2 | Diurutkan dari yang terendah; garis target 95% |
| **Semua angka & batang dapat diklik** → daftar karyawan terfilter; ekspor Excel | US-25 AC3 | |
| Tabel **indikator keberhasilan Brief 1.3** vs target | 1.3 | Completion sebelum tenggat, % overdue, lulus percobaan pertama, skor reaksi, penyelesaian online karyawan site |
| Laporan **Level 1**: rata-rata per butir survei + sebaran 1–5 + jawaban terbuka | US-26 AC1 | |
| Laporan **Level 2**: rata-rata pre, post, *gain score*, % lulus percobaan pertama, rata-rata jumlah percobaan, jumlah yang mengulang materi | US-26 AC2 | |
| **Analisis butir soal**: tingkat kesulitan aktual (p) & daya beda (D, metode 27% atas/bawah) + catatan otomatis | US-28 (Could) | Membantu memperbaiki bank soal & mendeteksi kebocoran kunci |
| **Daftar Rekomendasi Teguran**: Direkomendasikan → Terverifikasi → Teguran terbit / Dibatalkan; catat nomor & tanggal surat + unggah dokumen | BR-40, BR-41, US-27 | Sistem **tidak** menerbitkan teguran; semua aksi di audit log |
| Pengecualian/perpanjangan dan penyelesaian otomatis membatalkan rekomendasi | BR-16, BR-41 | |

## Definisi angka (penting agar laporan tidak diperdebatkan)
- **Populasi** = penugasan karyawan **aktif**, periode terpilih (Wajib Tahunan periode itu +
  Onboarding/Wajib Sekali yang ditugaskan di tahun itu), **tanpa** yang dikecualikan.
- **% selesai** = (Selesai + Selesai Terlambat) / populasi.
- **Completion sebelum tenggat** = Selesai tepat waktu / populasi (indikator 1.3).
- **Lulus percobaan pertama** = percobaan post-test #1 siklus #1 yang lulus / seluruh percobaan #1.
- **Gain score** = rata-rata (nilai akhir − nilai pre-test) pada peserta yang memiliki keduanya.

## Catatan interpretasi (perspektif L&D)
1. Gain score menunjukkan **peningkatan pengetahuan**, bukan perubahan perilaku (level 3).
2. Karena soal pre/post diambil acak dari bank yang sama, perbandingan valid di **tingkat kelompok**,
   bukan untuk menilai individu.
3. Angka site yang lebih rendah di data demo adalah skenario simulasi — saat pilot, bedakan
   penyebab **akses** (bandwidth, perangkat) dari penyebab **motivasi** sebelum menetapkan intervensi.

## Pengujian otomatis
`tests/Feature/ReportingTest.php`: ringkasan sesuai definisi (yang dikecualikan tidak dihitung),
seluruh halaman & ekspor dapat dibuka, akses non-admin ditolak, alur verifikasi → catat surat
(wajib nomor & tanggal) → status training tetap overdue → tercatat di audit log.
