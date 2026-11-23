# Tahap 4 — Program, Konten & Bank Soal

## Tujuan
Admin L&D dapat menyusun training wajib secara mandiri: program → modul → materi, bank soal,
pengaturan pre/post-test & survei, lalu menerbitkannya.

## Yang dibangun

| Fitur | Rujukan | Catatan |
|---|---|---|
| Program: kode, nama, tipe, kategori, tenggat (≤ 365), urutan wajib, blended, thumbnail | US-06 AC1, BR-15 | |
| Status Draft → Terbit → Diarsipkan; **pemeriksaan kelayakan terbit** | BR-07, BR-08 | Minimal 1 modul, tiap modul ≥ 1 materi, berkas selesai diproses, bank soal ≥ jumlah soal tes |
| Program berpeserta tidak dapat dihapus, hanya diarsipkan | BR-08 | |
| Modul & materi (video, PDF, PPT/PPTX, gambar, artikel teks kaya) + atur urutan | US-06 AC2, BR-07 | Editor teks sederhana; HTML dibersihkan dari skrip (anti-XSS) |
| Video → 360p/480p/720p di latar belakang, status proses terlihat | US-07 AC1 | FFmpeg; tidak meng-*upscale* video kecil; `faststart` agar cepat diputar |
| PPT/PPTX → PDF → gambar per halaman | US-08 | LibreOffice + Poppler |
| PDF → gambar per halaman | BR-19 | Dibaca di HP tanpa aplikasi tambahan; memudahkan pelacakan "halaman dibuka" |
| Batas unggah per file (default 1 GB) | US-07 AC3 | Dapat diubah di Pengaturan (Tahap 9) |
| **Versi baru** program (salin modul, materi, bank soal, pengaturan tes) | BR-09, US-10 | Saat versi baru terbit: versi lama diarsipkan, aturan penugasan dipindah ke versi baru, peserta lama tidak terdampak |
| Bank soal: PG, PG kompleks, Benar/Salah; kunci & tingkat kesulitan; pembahasan | US-09 AC1, BR-27 | Soal yang sudah pernah dikerjakan tidak bisa diubah/dihapus (hanya dinonaktifkan) agar nilai historis tetap valid |
| Impor soal dari Excel (template + validasi per baris) | US-09 AC2 | Semua-atau-tidak: bila ada error, tidak ada yang disimpan |
| Pengaturan pre/post-test: jumlah soal, durasi, komposisi kesulitan (%), passing grade, percobaan, jeda, pembahasan | US-09 AC3, BR-22–26 | Pre & post memakai bank soal yang sama (BR-28) |
| Template survei reaksi (Likert 1–5 + terbuka), pilih per program | BR-31 | Template yang sudah dijawab dikunci agar laporan konsisten |
| Berkas disajikan lewat **URL bertanda tangan & berbatas waktu** + wajib login | 9.2, BR-46 | Video mendukung *HTTP Range* (bisa di-*seek* dan dilanjutkan) |

## Konsep: antrian (queue)
Konversi video 20 menit bisa memakan beberapa menit. Saat admin mengunggah, aplikasi hanya
**menyimpan berkas dan mencatat tugas** di tabel `jobs`. Proses terpisah (`php artisan queue:work`,
atau container `worker` di Docker) mengerjakannya. Halaman editor memeriksa status tiap 5 detik
dan memuat ulang otomatis saat selesai.

## Data contoh
`DemoMediaSeeder` membuat **video uji 45 detik** (pola warna + nada, bebas hak cipta) di K3-01 dan
**PDF 3 halaman** di ONB-01, lalu memprosesnya dengan pipeline yang sama.

## Pengujian otomatis
`tests/Feature/ProgramContentTest.php`: alur Draft→Terbit dengan validasi, tenggat > 365 ditolak,
pembersihan HTML, validasi kunci jawaban, versi baru menyalin isi & mengarsipkan versi lama,
impor soal, dan seluruh halaman admin konten dapat dibuka.

## Cara mencoba
1. Login `100001` → **Program & Konten** → *K3-01* → lihat video (360p/480p/720p) dan status proses.
2. **+ Program baru** → tambah modul → **+ Materi** → unggah MP4/PDF/PPTX → perhatikan status
   "Sedang diproses" (jalankan `php artisan queue:work`).
3. **Bank Soal** → unduh template → impor.
4. Pada program Terbit → **Buat versi baru** → ubah materi → **Terbitkan**.

> Catatan unggah besar dengan `php artisan serve`: batas bawaan PHP 2 MB. Jalankan
> `php -d upload_max_filesize=1024M -d post_max_size=1100M artisan serve` atau gunakan Docker
> (sudah diatur di `docker/php.ini`).
