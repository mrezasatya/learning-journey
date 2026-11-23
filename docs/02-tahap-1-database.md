# Tahap 1 — Database & Data Dummy

## Tujuan
Menerjemahkan **Bagian 7 brief (Rancangan Data)** menjadi tabel nyata, lalu mengisinya dengan
perusahaan fiktif agar setiap tahap berikutnya bisa langsung dicoba.

## Konsep kunci (untuk non-programmer)

| Istilah | Arti | Contoh di BLJ |
|---|---|---|
| **Migration** | "Resep" pembuatan tabel dalam bentuk kode. Bisa dijalankan ulang di server mana pun → struktur identik | `database/migrations/2026_11_20_000001_create_organization_tables.php` |
| **Model** | Representasi satu tabel di kode, lengkap dengan relasinya | `Employee` punya `supervisor()` dan `subordinates()` |
| **Seeder** | Pengisi data awal/dummy | `EmployeeSeeder` membuat 68 karyawan fiktif |
| **Foreign key (FK)** | Penghubung antartabel | `assignments.employee_id` → `employees.id` |
| **Unique constraint** | Database menolak data ganda | `(employee_id, program_id, period_year)` = BR-12 |

## Peta tabel (dikelompokkan sesuai brief)

| Bagian | Tabel | Catatan |
|---|---|---|
| 7.1 Organisasi | `divisions`, `departments`, `positions`, `grades`, `work_locations`, `employees`, `users`, `roles`, `user_roles` | Peran Atasan **tidak** disimpan — dihitung dari `supervisor_nik` |
| 7.2 Konten | `programs`, `modules`, `lessons`, `files` | `files.renditions` menyimpan versi 360p/480p/720p atau gambar per halaman |
| 7.3 Penilaian | `question_banks`, `questions`, `question_options`, `assessments`, `assessment_attempts`, `attempt_answers`, `survey_templates`, `survey_responses` | `attempt_answers.option_order` menyimpan urutan opsi yang diacak |
| 7.4 Penugasan | `assignment_rules`, `assignments`, `lesson_progress`, `exemptions`, `manual_completions` | `lesson_progress` dipisah per `cycle_no` agar reset materi (BR-24) tidak menghapus riwayat |
| 7.5 Klasikal | `training_sessions`, `session_participants` | |
| 7.6 Lain-lain | `certificates`, `notifications`, `reminder_logs`, `disciplinary_followups`, `audit_logs`, `settings` | `reminder_logs` unik per kode pengingat → tidak ada pengingat ganda |
| 7.7 Kompetensi | `competencies`, `competency_levels`, `position_competency_requirements`, `program_competencies` | Dibuat kosong untuk fase 4 |

## Penyesuaian terhadap brief (perlu Anda ketahui)

| Brief | Implementasi | Alasan |
|---|---|---|
| Tabel `sessions` (sesi klasikal) | `training_sessions` | Nama `sessions` sudah dipakai Laravel untuk sesi login |
| `assignments.period_year` = NULL untuk non-tahunan | `0` | Di MySQL/SQLite, dua NULL tidak dianggap sama sehingga UNIQUE tidak mencegah duplikasi. Dengan `0`, database sendiri yang menjaga BR-12 |
| — | Kolom tambahan: `lesson_progress.started_at/last_heartbeat_at/last_position_seconds`, `exemptions.reason_category/old_due_at`, `session_participants.offline_score`, `programs.survey_template_id` | Dibutuhkan untuk anti-skip video, riwayat perpanjangan, nilai tes offline (BR-44), dan template survei per program (BR-31) |
| `audit_logs` | Tanpa `updated_at`, model menolak update/delete | BR-47 append-only |

## Data dummy

- **Organisasi**: 4 divisi, 8 departemen, 11 jabatan, golongan 1A–7B, 4 lokasi (HO, Plant, 2 Site).
- **68 karyawan** dengan hierarki atasan bertingkat (Division Head → Dept. Head → staf),
  campuran status Tetap/Kontrak/Proyek, 1 karyawan nonaktif (resign) untuk menguji BR-05.
- **4 program terbit**: Onboarding (ONB-01), Induksi K3 Dasar (K3-01, tahunan),
  ISO 9001 Awareness (ISO-01, tahunan), Praktik APAR (K3-02, *blended*), masing-masing dengan
  bank soal (41 soal total; tipe pilihan ganda, kompleks, benar/salah; tingkat mudah–sulit).

### Akun demo (password `Bukaka123`)

| Username | Peran | Kegunaan |
|---|---|---|
| `superadmin` | Super Admin | Pengaturan sistem & akun admin |
| `100001` | Admin L&D (+ Karyawan) | Mengelola seluruh modul admin |
| `100010` | Atasan (+ Karyawan) | Dept. Head Produksi, punya ±17 anggota tim |
| `100101` | Karyawan | Operator produksi |
| `100102` | Karyawan baru | Dipaksa ganti password saat login pertama (BR-01) |
| `100199` | Nonaktif | Tidak bisa login (BR-05) |

## Cara memverifikasi
```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="echo App\Models\Employee::count();"   # 68
```
