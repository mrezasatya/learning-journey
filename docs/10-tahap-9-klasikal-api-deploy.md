# Tahap 9 — Sesi Klasikal, API Integrasi & Kesiapan Deploy

## Tujuan
Menutup celah karyawan site (training tatap muka/offline tetap tercatat), menyiapkan "rumah"
integrasi ESS, dan membuat aplikasi siap diserahkan ke IT.

## Yang dibangun

| Fitur | Rujukan | Catatan |
|---|---|---|
| Sesi tatap muka: program, judul, waktu, lokasi, kapasitas, catatan | BR-42, US-23 | |
| Undang peserta dari daftar yang ditugaskan (filter lokasi, pilih semua), batas kapasitas | BR-42 | |
| **QR check-in** dari HP (wajib login), hanya berlaku pada waktu sesi ± 30 menit | BR-43, US-23 AC1 | Karyawan yang ditugaskan program boleh check-in meski belum diundang (selama kapasitas ada) |
| Absensi manual & **koreksi wajib beralasan**; input nilai tes offline | BR-43, BR-44, US-23 AC2 | Semua tercatat di audit log |
| Cetak daftar hadir (bukti fisik) | BR-44 | |
| Kehadiran otomatis melengkapi program **blended** | BR-45 | |
| **Penyelesaian manual** dengan bukti wajib (daftar hadir/foto) + nilai opsional → sertifikat terbit | BR-44, US-24 | |
| Menu **Jadwal Klasikal** untuk karyawan | — | |
| **Pengaturan sistem**: passing grade, percobaan, jeda, jumlah soal, durasi, waktu baca, +90 hari (Q-03), batas unggah, jadwal pengingat | Layar 24 | Nilai default untuk program baru |
| **API v1** (OpenAPI): sinkronisasi karyawan, riwayat training, ringkasan kepatuhan, verifikasi sertifikat | US-30, 9.3 | **Nonaktif default**, token Bearer, rate limit |
| Perintah instalasi produksi `php artisan blj:install` | — | Membuat peran, template survei, Super Admin (password acak, wajib ganti) |

## Peta adapter (siap diganti tanpa mengubah modul inti)

| Adapter | Implementasi sekarang | Pengganti di fase berikutnya |
|---|---|---|
| Auth (`Adapters/Auth`) | `LocalAuthAdapter` — NIK + password | `SsoAuthAdapter` (ESS/SSO) |
| Employee sync | Impor Excel/CSV + `POST /api/v1/sync/employees` | Job sinkronisasi terjadwal dari ESS |
| Storage | Disk `local` (Laravel filesystem) | Disk `s3` → MinIO internal (`BLJ_CONTENT_DISK=s3`) |
| Notification (`Adapters/Notification`) | In-app + email | + WhatsApp gateway resmi |

## Pengujian otomatis
`tests/Feature/SessionApiTest.php`: QR hanya berlaku pada waktu sesi & hanya untuk yang
ditugaskan, koreksi kehadiran wajib alasan, penyelesaian manual wajib bukti → sertifikat,
API 404 saat nonaktif / 401 tanpa token / sinkronisasi memicu penugasan, pengaturan tersimpan.

Total pengujian otomatis prototipe: **40 skenario, ±370 asersi** — `php artisan test`.

Panduan teknis untuk IT: [DEPLOY.md](DEPLOY.md).
