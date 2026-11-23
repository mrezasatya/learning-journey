<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    public const TYPES = ['VIDEO' => 'Video', 'PDF' => 'Dokumen PDF', 'SLIDE' => 'Slide (PPT)', 'TEXT' => 'Artikel/Teks', 'IMAGE' => 'Gambar'];

    protected $guarded = ['id'];

    protected $casts = ['completion_threshold' => 'float'];

    public function module() { return $this->belongsTo(Module::class); }

    public function file() { return $this->belongsTo(StoredFile::class, 'file_id'); }

    public function typeLabel(): string { return self::TYPES[$this->type] ?? $this->type; }

    public function isDocument(): bool { return in_array($this->type, ['PDF', 'SLIDE'], true); }

    /** Detik minimum: per halaman untuk dokumen, total untuk teks/gambar (BR-19). */
    public function effectiveMinSeconds(): int
    {
        if ($this->min_seconds !== null) {
            return (int) $this->min_seconds;
        }

        return $this->isDocument()
            ? (int) config('blj.defaults.seconds_per_page')
            : (int) config('blj.defaults.text_min_seconds');
    }

    public function icon(): string
    {
        return ['VIDEO' => '▶', 'PDF' => '📄', 'SLIDE' => '📊', 'TEXT' => '📝', 'IMAGE' => '🖼'][$this->type] ?? '•';
    }
}
