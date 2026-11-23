# Tahap 2 — Login, Peran & Audit Log

## Tujuan
Memastikan **hanya orang yang berhak** dapat masuk dan setiap orang hanya melihat menu
sesuai perannya. Semua aksi admin tercatat.

## Yang dibangun

| Fitur | Aturan | Lokasi kode |
|---|---|---|
| Login NIK + password | US-01 AC1 | `Auth/LoginController`, `Adapters/Auth/LocalAuthAdapter` |
| Wajib ganti password di login pertama / setelah reset | BR-01, US-01 AC2 | `Middleware/EnsureAccountReady` |
| Kebijakan password ≥ 8 karakter, huruf + angka | BR-02 | `PasswordController::rule()` |
| 5× gagal → terkunci 15 menit, pesan jelas + sisa percobaan | BR-02, US-01 AC3 | `LocalAuthAdapter` |
| Karyawan nonaktif ditolak (juga dikeluarkan bila sedang login) | BR-05, US-01 AC4 | `LocalAuthAdapter`, `EnsureAccountReady` |
| Lupa password via email: tautan 30 menit, sekali pakai; tanpa email → arahkan ke Admin | BR-03, US-02 | `ForgotPasswordController` |
| Admin: reset password sementara & buka kunci | US-03 | `Admin/UserController` |
| Super Admin: kelola peran Admin L&D | Brief Bagian 3 | `Admin/UserController::updateRoles` |
| Peran Atasan otomatis dari `supervisor_nik` | Brief Bagian 3 | `User::isSupervisor()` |
| RBAC tiap route | 9.2 | `Middleware/RequireRole` (`role:admin`, `role:supervisor`, …) |
| Audit log append-only + halaman filter | BR-47, US-29 | `Services/AuditLogger`, `Models/AuditLog` |
| Rate limiting login per IP | 9.2 | `routes/web.php` (`throttle`) |
| Sesi berakhir 60 menit tidak aktif | 9.2 | `.env` `SESSION_LIFETIME=60` |

## Konsep: *Adapter* autentikasi (BR-04)
`LoginController` tidak tahu cara memeriksa password. Ia hanya memanggil `AuthAdapter::attempt()`.
Hari ini adapternya `LocalAuthAdapter` (cek ke tabel `users`). Saat SSO/ESS siap, IT membuat
`SsoAuthAdapter` dan mengubah satu baris konfigurasi (`BLJ_AUTH_DRIVER=sso`) — halaman login,
menu, dan modul lain tidak perlu disentuh.

## Konsep: menu bertumbuh
Menu samping (`layouts/app.blade.php`) hanya menampilkan tautan ke halaman yang **sudah ada**
(`Route::has`). Jadi setiap tahap berikutnya otomatis memunculkan menunya sendiri.

## Keputusan desain yang perlu Anda ketahui
- **Pesan error login dibuat umum** ("NIK atau password salah") agar orang luar tidak bisa
  menebak NIK yang terdaftar. Pengecualian: akun terkunci & nonaktif (diminta AC3/AC4).
- **Password sementara dari Admin ditampilkan sekali** di layar admin, tidak dikirim lewat
  sistem. Admin menyampaikannya secara pribadi.
- **Super Admin** (akun `superadmin`) adalah akun sistem tanpa data karyawan, sesuai saran brief.

## Pengujian otomatis
`tests/Feature/AuthTest.php` (7 skenario): login sukses, wajib ganti password, penguncian
5× gagal, akun nonaktif, reset & buka kunci tercatat di audit log, karyawan tidak bisa membuka
halaman admin, peran atasan otomatis.

```bash
php artisan test --filter=AuthTest
```

## Cara mencoba manual
1. Login `100102` / `Bukaka123` → dipaksa ganti password.
2. Login `100101` dengan password salah 5× → pesan akun terkunci.
3. Login `100001` → menu **Akun & Password** → *Buka kunci* `100101` → cek **Audit Log**.
