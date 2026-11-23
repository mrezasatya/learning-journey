<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Bukaka Learning Journey (BLJ)
|--------------------------------------------------------------------------
| Nilai di sini adalah DEFAULT. Nilai yang dapat diubah Admin lewat menu
| Pengaturan disimpan di tabel `settings` dan dibaca melalui App\Support\Settings.
| Kode BR-xx merujuk ke Brief Produk v0.2.
*/

return [

    // Mode demo terbatas (Vercel): banner peringatan, data sementara, tanpa konversi media.
    'demo_mode' => (bool) env('BLJ_DEMO_MODE', false),

    // Disk penyimpanan konten (Storage adapter, Bagian 9.3). 'local' untuk prototipe,
    // 's3' untuk MinIO/S3-compatible di server internal.
    'content_disk' => env('BLJ_CONTENT_DISK', 'local'),

    'defaults' => [
        'passing_score' => 80,              // BR-22
        'max_attempts' => 3,                // BR-23 (1 + 2 remedial)
        'retry_cooldown_minutes' => 60,     // BR-23
        'question_count' => 10,             // BR-26
        'duration_minutes' => 20,           // BR-26
        'max_deadline_days' => 365,         // BR-14/BR-15
        'annual_late_join_days' => 90,      // BR-14 (asumsi Q-03)
        'video_completion_threshold' => 0.9, // BR-19
        'seconds_per_page' => 30,           // BR-19
        'text_min_seconds' => 60,           // BR-19
        'upload_max_mb' => 1024,            // US-07 AC3
        'reminder_days' => [90, 30, 14, 7, 1], // BR-38
        'supervisor_copy_from_days' => 30,  // BR-38
        'qr_session_tolerance_minutes' => 30, // US-23 AC1
    ],

    'auth' => [
        'driver' => env('BLJ_AUTH_DRIVER', 'local'), // BR-04: local | sso (fase 5)
        'max_failed_attempts' => 5,         // BR-02
        'lockout_minutes' => 15,            // BR-02
        'password_min_length' => 8,         // BR-02
    ],

    'api' => [
        'enabled' => (bool) env('BLJ_API_ENABLED', false), // US-30: nonaktif default
        'token' => env('BLJ_API_TOKEN'),
    ],

    'video_renditions' => [
        '360p' => 360,
        '480p' => 480,
        '720p' => 720,
    ],
];
