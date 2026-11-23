# Tahap 0 — Fondasi Proyek

## Tujuan
Menyiapkan "kerangka gedung": proyek Laravel kosong yang bisa berjalan, konfigurasi aturan
bisnis di satu tempat, identitas visual BLJ, dan resep Docker untuk dipindah ke server IT.

## Apa yang dibangun

| Berkas | Fungsi | Rujukan brief |
|---|---|---|
| Proyek Laravel 13 (`app/`, `routes/`, `config/` …) | Kerangka aplikasi web | 9.4 Opsi A |
| `config/blj.php` | Angka default aturan bisnis: passing grade 80, 3 percobaan, jeda 60 menit, 10 soal/20 menit, tenggat maks. 365 hari, pengingat H-90…H-1 | BR-14, 19, 22, 23, 26, 38 |
| `.env.example` | Zona waktu `Asia/Jakarta`, bahasa `id`, sesi 60 menit, API ESS nonaktif | 9.2, US-30 |
| `public/css/app.css` | Palet brand #006699 & turunannya, warna status terpisah, komponen *mobile-first* (target sentuh ≥ 44 px) | 8, 8.4 |
| `public/js/app.js` | JavaScript kecil tanpa framework (menu HP, konfirmasi, kirim data aman CSRF) | 9.1 (halaman < 1 MB) |
| `docker/`, `docker-compose.yml` | 4 layanan: `app` (web), `worker` (konversi video/PPT, email), `scheduler` (pengingat harian), `db` (MariaDB) | 9.3 |
| `.github/workflows/tests.yml` | GitHub menjalankan pengujian otomatis setiap ada *push* | — |

## Mengapa begini

- **Tanpa build tool JavaScript (Vite/NPM).** Server internal cukup PHP; halaman ringan untuk
  koneksi lemah di site. Konsekuensinya: tampilan sedikit kurang "SPA", sesuai trade-off Opsi A.
- **Font sistem** (tanpa unduh font) dan **kontras AA**: teks putih hanya di atas brand-500/700/900.
- **Pemisahan web / worker / scheduler.** Konversi video bisa memakan menit; bila dikerjakan di
  proses web, admin akan menunggu dan server bisa *timeout*. Worker mengerjakannya di latar.

## Cara memverifikasi
1. `php artisan serve` → buka `http://localhost:8000` → tampil kartu palet BLJ.
2. Periksa `config/blj.php` — semua angka default ada di satu berkas.

## Catatan untuk IT
- Image Docker memasang FFmpeg, Poppler, dan (opsional, `INSTALL_LIBREOFFICE=true`) LibreOffice
  Impress untuk konversi PPT → PDF (US-08). LibreOffice menambah ukuran image ±500 MB.
- Unggah file sampai 1 GB sudah diatur di `docker/php.ini`; reverse proxy/WAF juga perlu
  mengizinkan ukuran ini.
