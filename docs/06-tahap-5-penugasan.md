# Tahap 5 — Penugasan & Tenggat

## Tujuan
Program yang sudah terbit sampai ke **orang yang tepat, dengan tenggat yang tepat, tanpa
duplikasi** — dan tetap tepat ketika data karyawan berubah.

## Yang dibangun

| Fitur | Rujukan | Lokasi kode |
|---|---|---|
| Aturan berbasis atribut: jabatan, golongan, departemen, divisi, lokasi, tipe lokasi (HO/Plant/Site), status karyawan; operator "salah satu dari"/"bukan"; gabungan DAN/ATAU | BR-10a, US-11 AC1 | `Services/AssignmentEngine::matches()` |
| **Pratinjau** jumlah & daftar karyawan (baru vs sudah ditugaskan) sebelum simpan; tombol Simpan aktif setelah pratinjau | US-11 AC2 | `RuleController::preview()` |
| Aturan **dinamis**: impor/edit karyawan → event → aturan dievaluasi ulang | BR-11, US-04 AC5 | `Listeners/ReevaluateAssignmentRules` |
| Tidak ada penugasan ganda per karyawan–program–periode (termasuk lintas versi program) | BR-12 | `AssignmentEngine::assign()` + UNIQUE di database |
| Onboarding hanya untuk karyawan dengan tanggal masuk ≥ tanggal berlaku aturan | BR-13 | `matchingEmployees()` |
| Tenggat: Onboarding/Wajib Sekali = penugasan + `deadline_days` (default & maks 365); Wajib Tahunan = 31 Des, atau +90 hari bila lebih lambat | BR-14, BR-15 | `AssignmentEngine::dueDate()` |
| Penugasan manual: cari & pilih, tempel daftar NIK, atau impor berkas; tenggat khusus opsional | BR-10b, US-12 | `AssignmentController::store()` |
| Penugasan ulang Wajib Tahunan setiap **1 Januari** (terjadwal) + perintah manual | US-13 | `routes/console.php` → `blj:assign-annual` |
| Pengecualian/perpanjangan per individu, alasan wajib (kategori + uraian ≥ 10 karakter), bukti opsional, tercatat di audit log | BR-16, US-14 | `AssignmentEngine::exempt()` |
| Daftar penugasan + filter (program, status, periode, divisi, departemen, lokasi) + ekspor Excel; detail penugasan | — | `AssignmentController` |

## Logika tenggat (contoh)

| Situasi | Tanggal penugasan | Tenggat |
|---|---|---|
| Onboarding (deadline 90 hari) | 31 Jan 2026 | 1 Mei 2026 |
| Wajib Tahunan, aturan aktif sejak awal tahun | 1 Jan 2026 | 31 Des 2026 |
| Wajib Tahunan, karyawan masuk 10 Nov 2026 | 10 Nov 2026 | 8 Feb 2027 (+90 hari lebih lambat dari 31 Des) |
| Tenggat apa pun | — | Tidak pernah > 365 hari sejak penugasan |

## Keputusan desain yang perlu Anda ketahui

1. **Penugasan tidak dicabut otomatis** bila karyawan tidak lagi memenuhi aturan (mis. mutasi dari
   site ke HO). Alasannya: mencabut otomatis menghapus jejak kepatuhan. Admin memakai
   *Pengecualian* dengan alasan "Mutasi" bila memang tidak relevan lagi.
2. **Perpanjangan melewati 1 tahun** sejak penugasan hanya diizinkan untuk alasan cuti panjang/
   sakit — selebihnya gunakan pengecualian. Ini menjaga BR-14 tetap bermakna.
3. **Pengecualian otomatis membatalkan rekomendasi teguran** yang belum diproses (Tahap 8).

## ⚠ Catatan untuk Q-03 (perlu keputusan)
Dengan aturan "+90 hari bila lebih lambat", karyawan yang ditugaskan pada Oktober–Desember
mendapat tenggat di tahun berikutnya, **bersamaan** dengan penugasan periode baru 1 Januari.
Akibatnya karyawan tersebut bisa memegang dua penugasan program yang sama di awal tahun.
Alternatif yang bisa dipertimbangkan:
- (a) Karyawan yang masuk ≥ 1 Oktober tidak mendapat periode berjalan, langsung periode baru; atau
- (b) Penyelesaian periode berjalan yang terlambat dianggap juga memenuhi periode baru.
Nilai 90 hari dapat diubah di **Pengaturan** tanpa mengubah kode.

## Data contoh
`AssignmentSeeder` membuat 4 aturan (Onboarding semua karyawan baru, K3 untuk plant & site,
ISO untuk semua, Praktik APAR untuk operator/welder/supervisor/HSE) → ±170 penugasan, termasuk
Onboarding yang sudah **overdue** untuk karyawan yang masuk di awal tahun.

## Pengujian otomatis
`tests/Feature/AssignmentEngineTest.php` (8 skenario): tenggat per tipe, tanpa duplikasi, aturan
dinamis saat pindah jabatan, Onboarding & tanggal berlaku, penugasan ulang tahunan (idempoten),
perpanjangan & pengecualian, pratinjau = hasil simpan, halaman dapat dibuka.

## Cara mencoba
1. Login `100001` → **Aturan Penugasan → + Aturan baru** → pilih K3-02, kondisi *Tipe lokasi = Site*
   → **Pratinjau peserta** → **Simpan & terapkan**.
2. **Karyawan** → ubah jabatan seseorang menjadi *HSE Officer* → buka detailnya: K3-02 otomatis muncul.
3. **Penugasan** → filter *Overdue* → buka detail → perpanjang tenggat dengan alasan.
4. Simulasi 1 Januari: `php artisan blj:assign-annual 2027`.
