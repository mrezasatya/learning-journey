# Tahap 7 — Atasan, Notifikasi & Pengingat

## Tujuan
Kepatuhan tidak bergantung pada ingatan karyawan. Sistem mengingatkan secara bertahap,
melibatkan atasan sebelum terlambat, dan menandai overdue secara otomatis.

## Yang dibangun

| Fitur | Rujukan | Lokasi kode |
|---|---|---|
| Notifikasi dalam aplikasi (ikon 🔔 + jumlah belum dibaca, halaman notifikasi) + email | US-20 | `Adapters/Notification/EmailInAppNotifier` |
| Notifikasi **saat ditugaskan** | BR-38 | `Listeners/NotifyAssignment` |
| Pengingat **H-90, H-30, H-14, H-7, H-1**; salinan ke Atasan mulai H-30 | BR-38, US-22 | `Services/ReminderService::sendReminders()` |
| **H+1 → Overdue** otomatis; notifikasi Karyawan & Atasan; Admin menerima **ringkasan** (bukan ratusan email) | BR-39 | `ReminderService::markOverdue()` |
| Rekomendasi teguran tertulis dibuat otomatis untuk overdue (diverifikasi Admin di Tahap 8) | BR-40 | tabel `disciplinary_followups` |
| Gagal post-test 3× → notifikasi Atasan & Admin | BR-24 | `Listeners/NotifyRelearn` |
| **Tim Saya**: kartu ringkas, tabel anggota × program (status, progres %, tenggat, nilai), filter status/program, ekspor Excel | US-21 | `TeamController` |
| Tampilan tim **tidak langsung** untuk Dept./Division Head (tombol "Termasuk tim tidak langsung") | Q-05 (Should) | `Employee::allSubordinateNiks()` |
| Atasan hanya melihat timnya | BR-48 | |
| Proses harian terjadwal pukul 06.00 | — | `php artisan blj:daily` |

## Logika pengingat (tanpa duplikasi, tanpa "banjir")
- Setiap pengingat dicatat di `reminder_logs` dengan kode unik per penugasan (`ASSIGNED`, `H-30`,
  `OVERDUE`, …) → menjalankan proses dua kali tidak mengirim dua kali.
- Sistem mengirim **ambang terdekat** yang sudah tercapai. Contoh: karyawan ditugaskan saat sisa
  25 hari → langsung menerima H-30, **tidak** menerima H-90 susulan.
- Email dikirim lewat antrian (worker) agar penerapan aturan ke 1.000 karyawan tidak membuat
  halaman admin menunggu.

## Konsep: Notifier adapter
Semua pemanggil cukup memanggil `Notifier::send(user, judul, isi, tautan, kanal)`. Saat WA
gateway resmi tersedia, cukup menambah `WhatsAppNotifier` dan kanal `WA` — kolom `employees.phone`
sudah disiapkan sejak Tahap 1.

## ⚠ Catatan Q-06
Karyawan tanpa email tetap menerima notifikasi **dalam aplikasi**, tetapi hanya melihatnya saat
login. Untuk karyawan site tanpa email, eskalasi lewat atasan (mulai H-30) menjadi jalur utama —
ini alasan WA gateway layak diprioritaskan pada fase berikutnya.

## Pengujian otomatis
`tests/Feature/NotificationReminderTest.php`: notifikasi saat ditugaskan, pengingat bertahap
tanpa duplikasi + salinan atasan, H+1 overdue + rekomendasi teguran + notifikasi 3 pihak,
Tim Saya hanya anggota tim (langsung vs tidak langsung), halaman notifikasi & kepemilikan.

## Cara mencoba
1. `php artisan blj:daily` (atau tunggu jadwal 06.00 bila `schedule:work` berjalan).
2. Login `100010` → **Tim Saya** → filter *Overdue* → ekspor Excel.
3. Login `100901` (Division Head) → **Termasuk tim tidak langsung**.
4. Email (mode pengembangan) tercatat di `storage/logs/laravel.log` setelah `php artisan queue:work`.
