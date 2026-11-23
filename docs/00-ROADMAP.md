# Roadmap Pengembangan Prototipe BLJ

Dokumen ini memecah **Fase 0 (Prototipe)** pada Brief v0.2 menjadi **10 tahap kerja**.
Setiap tahap = satu (atau beberapa) *commit* di GitHub, dan punya dokumen penjelasan
sendiri di folder `docs/`. Tujuannya agar Anda bisa mengikuti **apa yang dibangun, mengapa,
dan bagaimana memverifikasinya** — bukan hanya menerima aplikasi jadi.

> Analogi: membangun aplikasi mirip membangun gedung. Tahap 0–1 = pondasi & denah,
> tahap 2–3 = pintu & data penghuni, tahap 4–6 = ruang kelas dan proses belajarnya,
> tahap 7–9 = sistem pemantauan, laporan, dan sambungan ke gedung lain (ESS).

## Ringkasan tahap

| Tahap | Nama | User story utama | Hasil yang bisa dilihat |
|---|---|---|---|
| 0 | Fondasi proyek | — | Proyek Laravel, konfigurasi BLJ, Docker, identitas visual |
| 1 | Database & data dummy | Bagian 7 brief | Seluruh tabel + 1 perusahaan fiktif berisi karyawan, program, soal |
| 2 | Login, peran & audit log | US-01, US-03, US-29 | Login NIK, ganti password wajib, kunci akun, menu sesuai peran |
| 3 | Data karyawan & master data | US-04, US-05 | Impor Excel/CSV dengan pratinjau, CRUD master data |
| 4 | Program, konten & bank soal | US-06–US-09 | Editor program/modul/materi, konversi video & PPT, bank soal + impor |
| 5 | Penugasan & tenggat | US-11–US-14 | Aturan penugasan dinamis + pratinjau, penugasan manual, Wajib Tahunan, pengecualian |
| 6 | Alur belajar karyawan | US-15–US-19 | Beranda, pemutar materi anti-skip, pre/post-test ber-timer, remedial, survei, sertifikat PDF + QR |
| 7 | Atasan, notifikasi & pengingat | US-20–US-22 | Menu "Tim Saya", notifikasi in-app & email, pengingat H-90…H-1, overdue |
| 8 | Dashboard, laporan & teguran | US-25–US-28 | Dashboard kepatuhan, laporan Kirkpatrick L1/L2, daftar rekomendasi teguran |
| 9 | Sesi klasikal, API & kesiapan deploy | US-23, US-24, US-30 | Sesi tatap muka + QR check-in, penyelesaian manual, API v1 (nonaktif), panduan deploy |

Status setiap tahap dicatat di bagian **Log kemajuan** di bawah.

## Prinsip yang dipakai di semua tahap

1. **Aturan bisnis dulu, tampilan kemudian.** Setiap aturan BR-xx ditulis di satu tempat
   (kelas *Service*), sehingga mudah diuji dan diubah bila jawaban Q-01…Q-10 berbeda.
2. **Satu sumber kebenaran untuk angka default** — `config/blj.php` (passing grade 80,
   3 percobaan, jeda 60 menit, dst.). Admin bisa menimpa lewat menu Pengaturan.
3. **Adapter** untuk hal yang akan diganti di fase integrasi: autentikasi (SSO), penyimpanan
   file (MinIO/S3), notifikasi (WA), sinkronisasi karyawan (ESS).
4. **Data dummy saja** selama prototipe (Brief 9.6). Tidak ada data karyawan asli di repo.
5. **Diuji otomatis.** Aturan penting (tenggat, remedial, penugasan) punya *automated test*
   yang dijalankan dengan `php artisan test`.

## Pilihan teknologi (Opsi A brief)

| Komponen | Pilihan | Alasan singkat |
|---|---|---|
| Bahasa & framework | PHP 8.3 + Laravel 13 | Rekomendasi brief (Opsi A), umum di tim IT korporat, mudah on-premise |
| Database | SQLite (prototipe) → MySQL/MariaDB (server) | SQLite = nol instalasi untuk demo; skema identik karena memakai *migration* |
| Tampilan | Blade + CSS & JavaScript murni (tanpa build tool) | Halaman ringan untuk koneksi site; tidak perlu Node.js di server |
| Antrian latar belakang | Laravel Queue (driver database) | Untuk konversi video FFmpeg & PPT tanpa membuat admin menunggu |
| PDF sertifikat | dompdf | Murni PHP, tidak butuh layanan eksternal |
| QR code | chillerlan/php-qrcode | Murni PHP |
| Excel | OpenSpout | Impor/ekspor .xlsx dan .csv, hemat memori |
| Media | FFmpeg, Poppler (pdftoppm), LibreOffice | Konversi video multi-resolusi, PDF→gambar per halaman, PPT→PDF |

Keputusan final stack tetap pada IT (Q-10). Karena logika bisnis terpisah di *Service*,
memindahkan ke stack lain berarti menerjemahkan, bukan merancang ulang.

## Log kemajuan

| Tahap | Status | Dokumen |
|---|---|---|
| 0 | Selesai | [01-tahap-0-fondasi.md](01-tahap-0-fondasi.md) |
| 1 | Selesai | [02-tahap-1-database.md](02-tahap-1-database.md) |
| 2 | Selesai | [03-tahap-2-login-peran.md](03-tahap-2-login-peran.md) |
| 3 | Selesai | [04-tahap-3-karyawan-master.md](04-tahap-3-karyawan-master.md) |
| 4 | Selesai | [05-tahap-4-program-konten.md](05-tahap-4-program-konten.md) |
| 5 | Selesai | [06-tahap-5-penugasan.md](06-tahap-5-penugasan.md) |
| 6 | Selesai | [07-tahap-6-alur-belajar.md](07-tahap-6-alur-belajar.md) |
| 7 | Selesai | [08-tahap-7-atasan-notifikasi.md](08-tahap-7-atasan-notifikasi.md) |
| 8 | Selesai | [09-tahap-8-dashboard-laporan.md](09-tahap-8-dashboard-laporan.md) |
| 9 | Selesai | [10-tahap-9-klasikal-api-deploy.md](10-tahap-9-klasikal-api-deploy.md) · [DEPLOY.md](DEPLOY.md) |
| Demo | Vercel (terbatas) | [11-demo-vercel.md](11-demo-vercel.md) |

## Cakupan user story prototipe

| Prioritas | Story | Status |
|---|---|---|
| **Must** | US-01, 03, 04, 05, 06, 07, 09, 11–19, 21, 25, 26, 27, 29 | Semua dibangun & diuji |
| **Should** | US-02 (reset via email), 08 (PPT→PDF), 10 (versi program), 20 (notifikasi), 22 (eskalasi), 23 (sesi & QR), 24 (selesai manual), 30 (API) | Semua dibangun |
| **Could** | US-28 (analisis butir soal) | Dibangun |
| **Won't (fase 1)** | US-31 (SSO, sinkron otomatis ESS, WhatsApp, modul kompetensi) | Struktur & adapter disiapkan |

## Keputusan yang masih menunggu Anda (dari Brief Bagian 12)

| Kode | Dipakai di prototipe | Di mana mengubahnya bila jawabannya berbeda |
|---|---|---|
| Q-01 | Passing grade 80, 3 percobaan (1 + 2 remedial), jeda 60 menit | Pengaturan (default) & pengaturan tes per program |
| Q-02 | Gagal 3× → ulang materi (siklus baru) | `AssessmentService::submit()` |
| Q-03 | Tahunan: 31 Des atau +90 hari (lihat catatan risiko di Tahap 5) | Pengaturan → "+90 hari" |
| Q-04 | Onboarding contoh 90 hari; maks. 365 | Field "Tenggat" per program |
| Q-05 | Tim langsung default; Dept./Division Head bisa melihat tim tidak langsung | `TeamController` |
| Q-06 | Email + in-app; tanpa email → in-app & eskalasi atasan | Adapter notifikasi (WA di fase berikut) |
| Q-08 | Teguran diterbitkan di luar sistem (IR); BLJ hanya rekomendasi + pencatatan | Menu Overdue & Teguran |
| Q-10 | Laravel + MySQL/MariaDB (Opsi A) | — |
