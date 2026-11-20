<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US-01, US-03 — BR-01, BR-02, BR-05 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_login_dengan_nik_dan_password_valid(): void
    {
        $this->post('/login', ['username' => '100101', 'password' => 'Bukaka123'])->assertRedirect(route('home'));
        $this->assertAuthenticated();
    }

    public function test_login_pertama_wajib_ganti_password(): void
    {
        $this->post('/login', ['username' => '100102', 'password' => 'Bukaka123'])->assertRedirect(route('password.change'));
        $this->get('/profil')->assertRedirect(route('password.change'));

        $this->post('/ganti-password', ['current_password' => 'Bukaka123', 'password' => 'abc', 'password_confirmation' => 'abc'])
            ->assertSessionHasErrors('password');
        $this->post('/ganti-password', ['current_password' => 'Bukaka123', 'password' => 'Baru2026x', 'password_confirmation' => 'Baru2026x'])
            ->assertRedirect(route('home'));
        $this->assertFalse($this->user('100102')->must_change_password);
    }

    public function test_lima_kali_gagal_akun_terkunci_15_menit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => '100101', 'password' => 'salah']);
        }
        $user = $this->user('100101');
        $this->assertTrue($user->isLocked());
        $this->assertEqualsWithDelta(15, now()->diffInMinutes($user->locked_until), 1);

        // Password benar pun ditolak selama terkunci
        $this->post('/login', ['username' => '100101', 'password' => 'Bukaka123'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_karyawan_nonaktif_tidak_bisa_login(): void
    {
        $this->post('/login', ['username' => '100199', 'password' => 'Bukaka123'])
            ->assertSessionHasErrors(['username' => 'Akun tidak aktif, hubungi Admin L&D.']);
        $this->assertGuest();
    }

    public function test_admin_reset_password_dan_buka_kunci_tercatat_di_audit_log(): void
    {
        $target = $this->user('100101');
        $target->forceFill(['locked_until' => now()->addMinutes(10)])->save();

        $this->actingAsNik('100001')->post(route('admin.users.unlock', $target))->assertSessionHas('success');
        $this->actingAsNik('100001')->post(route('admin.users.reset', $target))->assertSessionHas('success');

        $target->refresh();
        $this->assertFalse($target->isLocked());
        $this->assertTrue($target->must_change_password);
        $this->assertSame(2, AuditLog::whereIn('action', ['user.unlock', 'user.reset_password'])->count());
    }

    public function test_karyawan_biasa_tidak_bisa_membuka_halaman_admin(): void
    {
        $this->actingAsNik('100101')->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_peran_atasan_dihitung_otomatis(): void
    {
        $this->assertTrue($this->user('100010')->isSupervisor());
        $this->assertFalse($this->user('100101')->isSupervisor());
    }
}
