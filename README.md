# Bukaka Learning Journey (BLJ)

Learning Management System untuk **training wajib** (Onboarding, ISO, K3, dan training wajib
lainnya) — dibangun dari *Brief Produk BLJ v0.2* (pemilik produk: L&D — Human Capital).

> Status: **Prototipe (Fase 0)** — hanya untuk data **dummy**. Jangan memuat data karyawan
> asli sebelum aplikasi dipindah ke server internal yang disetujui IT (Brief 9.6).

## Dokumentasi tahapan

Pembangunan dibagi menjadi 10 tahap agar prosesnya mudah dipahami. Mulai dari
[`docs/00-ROADMAP.md`](docs/00-ROADMAP.md), lalu baca dokumen setiap tahap berurutan.

## Menjalankan di laptop (tanpa Docker)

Prasyarat: PHP 8.3 (ekstensi `pdo_sqlite`, `gd`, `intl`, `zip`, `mbstring`), Composer.
Opsional untuk konversi media: FFmpeg, Poppler (`pdftoppm`), LibreOffice.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed       # membuat tabel + data dummy
php artisan serve                # buka http://localhost:8000
```

Di terminal lain (untuk konversi video & pengiriman notifikasi):

```bash
php artisan queue:work
```

## Menjalankan dengan Docker

```bash
cp .env.example .env && php artisan key:generate --show   # salin nilai ke APP_KEY di .env
docker compose up -d --build
docker compose exec app php artisan db:seed --force          # data dummy
```

Aplikasi tersedia di `http://localhost:8080`.

## Struktur folder penting

| Folder | Isi |
|---|---|
| `app/Services` | Aturan bisnis (BR-xx): penugasan, tenggat, alur belajar, penilaian, sertifikat |
| `app/Adapters` | Titik sambung integrasi: autentikasi (SSO), notifikasi (email/WA), sinkronisasi ESS |
| `app/Http/Controllers` | Penghubung halaman ↔ aturan bisnis, dikelompokkan per peran |
| `database/migrations` | Rancangan tabel (Brief Bagian 7) |
| `database/seeders` | Data dummy untuk demo |
| `resources/views` | Tampilan (Blade) |
| `config/blj.php` | Nilai default aturan bisnis (passing grade, remedial, tenggat, dst.) |
| `tests/Feature` | Pengujian otomatis aturan bisnis |
| `docs/` | Penjelasan setiap tahap pembangunan |
