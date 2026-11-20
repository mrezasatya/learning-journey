<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Membaca pengaturan: nilai di tabel `settings` (diubah Admin) menimpa default `config/blj.php`.
 * Contoh: Settings::get('passing_score') → 80
 */
class Settings
{
    public const EDITABLE = [
        'passing_score' => ['Passing grade post-test default', 'int'],
        'max_attempts' => ['Jumlah percobaan post-test per siklus (1 + remedial)', 'int'],
        'retry_cooldown_minutes' => ['Jeda antar percobaan (menit)', 'int'],
        'question_count' => ['Jumlah soal default per tes', 'int'],
        'duration_minutes' => ['Durasi tes default (menit)', 'int'],
        'seconds_per_page' => ['Waktu baca minimum per halaman dokumen (detik)', 'int'],
        'text_min_seconds' => ['Waktu minimum materi teks (detik)', 'int'],
        'annual_late_join_days' => ['Tenggat minimum Wajib Tahunan untuk penugasan di tengah tahun (hari)', 'int'],
        'upload_max_mb' => ['Batas ukuran unggah per file (MB)', 'int'],
        'reminder_days' => ['Jadwal pengingat (H-… hari, pisahkan koma)', 'list'],
        'supervisor_copy_from_days' => ['Salinan pengingat ke Atasan mulai H-…', 'int'],
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function all(): array
    {
        return Cache::remember('blj.settings', 300, function () {
            $values = config('blj.defaults');
            if (Schema::hasTable('settings')) {
                foreach (Setting::all() as $s) {
                    $values[$s->key] = $s->value;
                }
            }

            return $values;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('blj.settings');
    }
}
