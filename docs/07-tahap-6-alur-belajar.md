# Tahap 6 — Alur Belajar Karyawan

## Tujuan
Inti produk: karyawan (termasuk di site dengan koneksi lemah) menyelesaikan training wajib
secara mandiri, dan hasilnya **valid** (tidak bisa dilewati) sehingga dapat dipakai untuk
kepatuhan dan evaluasi Kirkpatrick L1–L2.

## Alur (BR-17)
```
Pre-test → Materi 1..n (berurutan) → Post-test (≥80?) → [Sesi tatap muka bila blended] → Survei → Sertifikat
                                      └ gagal → remedial (jeda 60 mnt, maks 3 percobaan)
                                                └ gagal 3× → "Perlu Mengulang Materi" → siklus baru
```

## Yang dibangun

| Fitur | Rujukan | Cara kerja / pengaman |
|---|---|---|
| Beranda: kartu ringkas (selesai x/y, tenggat terdekat, overdue), daftar diurutkan tenggat terdekat, warna + label status, % progres, tombol **Lanjutkan** ke posisi terakhir | US-15 | `Employee/HomeController`, `LearningFlow::resumeUrl()` |
| Detail program: daftar langkah dengan status & kunci | Layar 3 | `employee/program.blade.php` |
| Pre-test wajib sekali sebelum materi, tidak menentukan kelulusan | BR-21 | Materi terkunci sampai pre-test terkirim |
| Materi berurutan (dapat dimatikan per program) | BR-18 | `LearningFlow::lessons()` menghitung `locked` |
| **Video** ≥ 90%, tidak bisa *seek* melewati posisi terjauh, kecepatan dikunci 1× | BR-19 | Dicegah di browser **dan** di server: posisi terjauh hanya boleh naik sebanding waktu nyata sejak *heartbeat* terakhir |
| Pilihan kualitas otomatis (berdasar kecepatan koneksi) atau manual 360p/480p/720p | US-07 AC2 | Pilihan diingat di perangkat |
| **Dokumen** PDF/PPT: semua halaman + waktu baca minimum per halaman | BR-19 | Server memvalidasi jeda antara "buka halaman" dan "halaman selesai" |
| **Teks/gambar**: tombol "Tandai selesai" aktif setelah waktu minimum | BR-19 | Server memeriksa waktu sejak materi pertama dibuka |
| Progres tersimpan otomatis (video ≤ 10 detik, tiap halaman, saat pause/pindah tab) & lanjut di perangkat lain | BR-20, US-16 AC2 | Tersimpan di server, bukan di browser |
| Tes: soal acak sesuai komposisi kesulitan, urutan soal & opsi diacak, satu soal per layar, navigasi nomor soal | BR-26, US-17 | `AssessmentService::pickQuestions()` |
| **Timer berbasis server**; jawaban tersimpan setiap klik; bila koneksi putus dapat dilanjutkan; waktu habis → dinilai otomatis | BR-29 | `deadline_at` disimpan saat tes dimulai |
| Penilaian otomatis PG / B-S / PG kompleks (harus tepat seluruhnya) | BR-27 | |
| Nilai & status tampil segera, **kunci jawaban tidak ditampilkan**, pembahasan opsional | US-17 AC2 | |
| Remedial maks. 2× dengan jeda 60 menit; gagal 3× → reset materi (siklus baru) + notifikasi Atasan & Admin | BR-23, BR-24 | Riwayat progres siklus lama tetap tersimpan |
| Nilai akhir = nilai tertinggi | BR-25 | |
| Survei reaksi wajib setelah lulus, sebelum sertifikat | BR-30, BR-31 | |
| Selesai bila materi + post-test ≥ 80 + survei (+ hadir sesi bila blended) | BR-32, BR-45 | `LearningFlow::checkCompletion()` |
| Selesai setelah tenggat → `Selesai (Terlambat)` | BR-41 | |
| **Sertifikat PDF otomatis**: nama, NIK, program, tanggal, nilai, nomor `BLJ/[KODE]/[TAHUN]/00001`, QR | BR-33, BR-34 | `CertificateService` (dompdf + QR) |
| Sertifikat Wajib Tahunan berlaku s.d. 31 Des periode | BR-36 | |
| Halaman verifikasi publik dari QR: hanya nama, program, tanggal, status | BR-35 | Tanpa NIK/data lain, `noindex` |
| Riwayat & unduh ulang sertifikat | US-19 | PDF dibuat ulang bila berkas hilang |
| Karyawan hanya dapat mengakses penugasannya sendiri | BR-48 | |

## Mengapa validasi dilakukan di server (bukan hanya di browser)
Pengecekan di browser mudah diakali (mis. mengubah kode halaman). Karena data BLJ dipakai
untuk **kepatuhan** dan dapat berujung teguran, aturan BR-19 dan timer tes ditegakkan di server.
Browser hanya membantu kenyamanan (tombol non-aktif, hitung mundur).

## Data simulasi untuk demo
`LearningSimulationSeeder` mengisi riwayat periode tahun lalu (±92% selesai) dan periode berjalan
(campuran selesai/berjalan/belum mulai; karyawan site dibuat lebih rendah penyelesaiannya agar
pola "akses site" terlihat di dashboard). Akun `100101` memiliki 1 program selesai, 1 berjalan,
1 belum mulai.

## Pengujian otomatis
`tests/Feature/LearningFlowTest.php` (6 skenario): urutan pre-test → materi, materi berurutan,
waktu minimum teks, remedial + jeda + reset materi + nilai tertinggi, survei → sertifikat →
verifikasi publik tanpa NIK, video tidak bisa dilompati, selesai terlambat, akses milik orang lain ditolak.

## Cara mencoba
Login `100101` → **Lanjutkan** Induksi K3 → coba geser video ke depan (ditolak) → selesaikan →
post-test → survei → unduh sertifikat → pindai QR-nya.
