<?php

namespace Tests\Unit;

use App\Support\Html;
use PHPUnit\Framework\TestCase;

/** Pembersih HTML materi teks kaya (anti-XSS, Brief 9.2). */
class HtmlSanitizerTest extends TestCase
{
    public function test_skrip_dan_atribut_event_dihapus(): void
    {
        $out = Html::clean('<p onclick="x()">Halo <b>tebal</b><script>alert(1)</script></p><iframe src="https://x"></iframe>');
        $this->assertSame('<p>Halo <b>tebal</b></p>', $out);
    }

    public function test_tautan_javascript_dibuang_tautan_aman_dipertahankan(): void
    {
        $this->assertStringNotContainsString('javascript:', Html::clean('<a href="javascript:alert(1)">x</a>'));
        $this->assertStringContainsString('href="https://contoh.co.id"', Html::clean('<a href="https://contoh.co.id">x</a>'));
        $this->assertStringContainsString('rel="noopener noreferrer"', Html::clean('<a href="https://contoh.co.id">x</a>'));
    }

    public function test_tag_tak_dikenal_dibuka_isinya_tetap(): void
    {
        $this->assertSame('<p>isi</p>', Html::clean('<p><foo>isi</foo></p>'));
        $this->assertSame('', Html::clean('   '));
    }
}
