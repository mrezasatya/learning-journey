# Tahap 3 — Data Karyawan & Master Data

## Tujuan
Aturan penugasan (Tahap 5) hanya akan benar bila data karyawan benar. Tahap ini menyediakan
cara memasukkan & merawat data karyawan **sebelum integrasi ESS**, dengan pengaman agar
data salah tidak masuk diam-diam.

## Yang dibangun

| Fitur | Rujukan | Catatan |
|---|---|---|
| Template impor .xlsx (3 sheet: Karyawan, Petunjuk, Kode Referensi) | US-04 AC1 | Kode referensi diambil langsung dari master data aktif |
| Impor .xlsx / .csv (pemisah `,` atau `;` dideteksi otomatis) | US-04 | Tanggal diterima dalam `YYYY-MM-DD`, `DD/MM/YYYY`, atau format tanggal Excel |
| **Pratinjau** baris Baru / Berubah (dengan detail perubahan) / Tetap / Error (dengan alasan) | US-04 AC2 | Tidak ada yang tersimpan sebelum admin menekan *Simpan impor* |
| NIK sebagai kunci unik — update, bukan duplikasi | US-04 AC3 | |
| Karyawan yang tidak ada di file **tidak** dinonaktifkan kecuali dicentang | US-04 AC4 | |
| Akun login otomatis untuk karyawan baru + **daftar password awal sekali unduh** | BR-01 | File dihapus dari server setelah diunduh |
| Event `EmployeesChanged` setelah impor/edit | US-04 AC5, BR-11 | Didengar oleh mesin penugasan di Tahap 5 |
| Daftar karyawan + filter + ekspor Excel, detail, edit | — | Edit memakai validasi yang sama dengan impor |
| CRUD master: divisi, departemen, jabatan, golongan (1A–7B), lokasi (HO/Plant/Site) | US-05 | Data yang dipakai tidak bisa dihapus, hanya dinonaktifkan |

## Validasi per baris impor
NIK wajib & format valid · nama wajib · email valid (bila diisi) · kode jabatan/golongan/
departemen/lokasi harus ada di master **aktif** · status TETAP/KONTRAK/PROYEK · tanggal masuk
valid · NIK atasan harus ada (di sistem atau di file yang sama) · atasan ≠ diri sendiri ·
NIK tidak boleh ganda dalam satu file.

## Mengapa pratinjau penting (perspektif HR)
Risiko di brief Bagian 11: *"Data karyawan tidak akurat → penugasan & eskalasi salah sasaran."*
Pratinjau memaksa pemeriksaan sebelum simpan, dan kolom "Berubah" menunjukkan **apa** yang
berubah (mis. jabatan OPR → WELD) sehingga mutasi yang tidak disengaja mudah terlihat.

## Pengujian otomatis
`tests/Feature/EmployeeImportTest.php`: satu file berisi baris baru, berubah, dan error →
pratinjau benar, tidak tersimpan sebelum konfirmasi, hasil akhir benar, karyawan lain tidak
dinonaktifkan, event terkirim; master yang dipakai tidak bisa dihapus.

## Cara mencoba
1. Login `100001` → **Karyawan → Impor data → Unduh template**.
2. Isi 1–2 baris, ubah jabatan salah satu karyawan lama, buat satu baris dengan kode salah.
3. Unggah → periksa pratinjau → **Simpan impor** → unduh daftar password awal.
