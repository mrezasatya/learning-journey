# Panduan Deploy BLJ untuk Tim IT

> Prototipe dibangun dengan **data dummy**. Langkah di bawah memasang BLJ di server internal
> **tanpa** data dummy. Keputusan stack, server, dan akses luar kantor tetap pada IT (Brief Fase 1).

## 1. Komponen

| Layanan | Fungsi | Perintah |
|---|---|---|
| `app` | Web (Apache + PHP 8.3) | `apache2-foreground` |
| `worker` | Konversi video/PPT, kirim email | `php artisan queue:work --tries=3 --timeout=3600` |
| `scheduler` | Pengingat harian 06.00, penugasan tahunan 1 Jan | `php artisan schedule:work` |
| `db` | MariaDB 11 / MySQL 8 | — |
| (opsional) MinIO | Penyimpanan konten S3-compatible | — |

Semua tersedia di `docker-compose.yml`. Tanpa Docker: PHP 8.3 (ekstensi pdo_mysql, gd, intl, zip,
mbstring, bcmath), Composer, FFmpeg, Poppler (`pdftoppm`), LibreOffice Impress (opsional, untuk PPT),
cron untuk `php artisan schedule:run` tiap menit, dan supervisor untuk `queue:work`.

## 2. Instalasi (Docker)
```bash
cp .env.example .env
# isi: APP_KEY (php artisan key:generate --show), APP_URL, DB_PASSWORD, MAIL_*, APP_ENV=production, APP_DEBUG=false
docker compose up -d --build
docker compose exec app php artisan blj:install          # buat Super Admin (password tampil sekali)
```
Lalu login sebagai Super Admin → **Akun & Password** → beri peran Admin L&D ke staf L&D
(setelah data karyawan diimpor) → isi **Master Data** → **Impor karyawan**.

**Jangan** menjalankan `db:seed` tanpa kelas di server produksi — itu memuat data dummy.

## 3. Variabel lingkungan penting

| Variabel | Nilai produksi |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | URL HTTPS resmi (dipakai di QR sertifikat — jangan diubah setelah sertifikat terbit) |
| `SESSION_LIFETIME` | `60` (Brief 9.2) |
| `MAIL_MAILER`, `MAIL_HOST`, … | SMTP perusahaan |
| `BLJ_CONTENT_DISK` | `local` atau `s3` (MinIO: isi `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT=true`, bucket, key) |
| `BLJ_API_ENABLED` / `BLJ_API_TOKEN` | `false` sampai integrasi ESS siap; token acak ≥ 40 karakter |

## 4. Checklist keamanan (Brief 9.2)
- [ ] HTTPS wajib (sertifikat TLS di reverse proxy/WAF).
- [ ] Akses dari luar kantor: publikasi terbatas di belakang WAF (rekomendasi brief) atau VPN.
- [ ] Batas unggah di reverse proxy ≥ 1 GB untuk video (`client_max_body_size` / setara).
- [ ] `APP_DEBUG=false`; folder `storage/` tidak dapat diakses publik (hanya `public/` yang di-*serve*).
- [ ] Berkas konten hanya lewat URL bertanda tangan (sudah bawaan aplikasi).
- [ ] Password di-hash bcrypt; penguncian 5× gagal; rate limit login (bawaan).
- [ ] Jalankan `composer audit` berkala.

## 5. Backup & pemulihan
- Database: harian (`mysqldump --single-transaction blj`), simpan 30 hari.
- Konten (`storage/app/private` atau bucket MinIO): mingguan.
- **Uji restore minimal tiap 3 bulan** ke server uji; verifikasi login, buka satu video, unduh satu sertifikat.

## 6. Kapasitas (indikatif, Brief 9.5)
- Disk konten ≥ 100 GB. Video 20 menit ≈ 420 MB untuk 3 resolusi.
- Puncak 200 pengguna bersamaan menjelang akhir tahun: 2 vCPU / 4 GB RAM untuk `app` memadai
  untuk halaman; video dilayani sebagai file statis (pertimbangkan cache/CDN internal bila bandwidth site terbatas).
- Worker konversi video sebaiknya di mesin dengan CPU lebih besar atau dibatasi 1 job paralel.

## 7. Perintah operasional

| Perintah | Kegunaan |
|---|---|
| `php artisan blj:daily` | Proses harian (otomatis 06.00): overdue, pengingat, eskalasi |
| `php artisan blj:assign-annual 2027` | Penugasan ulang Wajib Tahunan (otomatis 1 Jan 00.10) |
| `php artisan queue:failed` / `queue:retry all` | Pantau & ulangi job gagal (konversi/email) |
| `php artisan test` | Menjalankan 40 skenario pengujian otomatis |

## 8. Migrasi ke MySQL dari prototipe SQLite
Skema dibuat lewat migration sehingga identik. Data prototipe adalah dummy — tidak perlu dipindah.
