# Demo Terbatas di Vercel

> **Bukan** lingkungan produksi. Untuk pilot/produksi gunakan server dengan disk permanen sesuai
> [DEPLOY.md](DEPLOY.md). Mode ini hanya agar tampilan & alur BLJ dapat dilihat lewat tautan.

## Mengapa "terbatas"

| Kebutuhan BLJ | Di Vercel | Dampak pada demo |
|---|---|---|
| Database permanen | Hanya `/tmp` yang dapat ditulis, hilang saat instance berhenti | Setiap instance memulai dari **database demo bawaan**; perubahan (progres, nilai, aturan baru) bisa hilang sewaktu-waktu dan bisa berbeda antar-instance |
| Penyimpanan unggahan | Sementara, ukuran permintaan maks. 4,5 MB | Unggah berkas > 4 MB gagal; unggahan bisa hilang |
| FFmpeg / LibreOffice | Tidak tersedia | Konversi video & PPT ditandai "tidak tersedia di mode demo" |
| Worker & scheduler | Tidak ada | Email (hanya log), pengingat H-90…H-1, overdue otomatis tidak berjalan |

## Cara kerja
- `vercel.json` → semua permintaan ke `api/index.php` (runtime komunitas `vercel-php@0.7.4`, PHP 8.3), kecuali `/css` & `/js` (statis).
- `api/index.php` menyalin `database/demo.sqlite` + `database/demo-storage/` ke `/tmp/blj` saat instance baru dimulai.
- `BLJ_DEMO_MODE=true` → banner "MODE DEMO" di semua halaman, `noindex` untuk mesin pencari, konversi media dinonaktifkan.
- Daftar akun demo **tidak** ditampilkan di halaman login. Bagikan akun secara pribadi kepada penonton demo.
- `APP_KEY` disimpan sebagai *environment variable* terenkripsi di proyek Vercel (bukan di repo).

## Menyegarkan data demo
Tanggal data dummy relatif terhadap waktu pembuatan. Untuk menyegarkan (mis. sebelum presentasi):
```bash
bash scripts/build-vercel-demo.sh
git add database/demo.sqlite database/demo-storage && git commit -m "Segarkan data demo" && git push
```

## Keamanan
- Hanya data **fiktif**. Jangan memasukkan data karyawan asli ke demo ini (Brief 9.6).
- Pertimbangkan mengaktifkan *Vercel Authentication / Password Protection* di pengaturan proyek
  agar tautan hanya dapat dibuka oleh pihak yang diundang.
