<?php

namespace Tests\Feature;

use App\Events\EmployeesChanged;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** US-04 & US-05 */
class EmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function csv(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'karyawan.csv', 'text/csv', null, true);
    }

    public function test_pratinjau_lalu_simpan_impor(): void
    {
        Event::fake([EmployeesChanged::class]);
        $csv = "nik;full_name;email;phone;position_code;grade_code;department_code;work_location_code;supervisor_nik;employment_status;join_date;end_date;is_active\n"
            ."200001;Karyawan Baru;baru@contoh.co.id;;OPR;1A;PROD;PLT-01;100010;KONTRAK;2026-11-01;;1\n"   // baru
            ."100101;Budi Setiawan;;;WELD;2A;PROD;PLT-01;100010;TETAP;15/03/2020;;1\n"                      // berubah
            ."200002;Salah Data;;;XXX;9Z;PROD;PLT-01;;BEBAS;bukan-tanggal;;1\n";                            // error

        $admin = $this->user('100001');
        $res = $this->actingAs($admin)->post(route('admin.employees.preview'), ['file' => $this->csv($csv)]);
        $res->assertOk()->assertSee('Pratinjau impor');
        $preview = $res->viewData('preview');
        $this->assertSame(1, $preview['summary']['NEW']);
        $this->assertSame(1, $preview['summary']['CHANGED']);
        $this->assertSame(1, $preview['summary']['ERROR']);
        $this->assertSame(0, Employee::where('nik', '200001')->count(), 'Belum tersimpan sebelum konfirmasi');

        $this->actingAs($admin)->post(route('admin.employees.commit'), ['token' => $res->viewData('token')])->assertRedirect();

        $this->assertSame(1, Employee::where('nik', '200001')->count());
        $this->assertTrue(Employee::where('nik', '200001')->first()->user->must_change_password);
        $this->assertSame(Position::where('code', 'WELD')->value('id'), Employee::where('nik', '100101')->value('position_id'));
        $this->assertSame(0, Employee::where('nik', '200002')->count());
        $this->assertTrue(Employee::where('nik', '100010')->value('is_active'), 'Karyawan yang tidak ada di file tidak dinonaktifkan');
        Event::assertDispatched(EmployeesChanged::class);
    }

    public function test_master_data_yang_dipakai_tidak_bisa_dihapus(): void
    {
        $pos = Position::where('code', 'OPR')->first();
        $this->actingAsNik('100001')->delete(route('admin.master.destroy', ['positions', $pos->id]))->assertSessionHasErrors('delete');
        $this->assertNotNull($pos->fresh());
    }
}
