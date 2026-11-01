<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Major;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionSplitTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrasi;

    protected User $bendahara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->administrasi = User::factory()->create();
        $this->administrasi->assignRole('Administrasi');

        $this->bendahara = User::factory()->create();
        $this->bendahara->assignRole('Bendahara');
    }

    public function test_seeder_creates_the_administrasi_account(): void
    {
        $this->assertTrue(User::where('email', 'admin@smk.com')->first()->hasRole('Administrasi'));
    }

    public function test_administrasi_inputs_master_data_and_bendahara_only_reads_it(): void
    {
        $this->actingAs($this->administrasi)
            ->postJson('/api/v1/majors', ['code' => 'TKJ', 'name' => 'Teknik Komputer dan Jaringan'])
            ->assertCreated();

        $this->actingAs($this->bendahara)
            ->postJson('/api/v1/majors', ['code' => 'AKL', 'name' => 'Akuntansi'])
            ->assertForbidden();

        $this->actingAs($this->bendahara)->getJson('/api/v1/majors')->assertOk();
        $this->actingAs($this->bendahara)->getJson('/api/v1/students')->assertOk();
    }

    public function test_administrasi_cannot_open_finance(): void
    {
        $this->actingAs($this->administrasi)->getJson('/api/v1/payments')->assertForbidden();
        $this->actingAs($this->administrasi)->getJson('/api/v1/cashier-sessions')->assertForbidden();
        $this->actingAs($this->administrasi)->getJson('/api/v1/reports/harian')->assertForbidden();
        $this->actingAs($this->administrasi)->getJson('/api/v1/payment-rates')->assertForbidden();
    }

    public function test_school_settings_belong_to_administrasi_not_bendahara(): void
    {
        $this->actingAs($this->administrasi)
            ->postJson('/api/v1/settings', ['school_name' => 'SMK Uji'])
            ->assertOk();

        $this->actingAs($this->bendahara)
            ->postJson('/api/v1/settings', ['school_name' => 'SMK Lain'])
            ->assertForbidden();
    }

    public function test_administrasi_dashboard_flags_classes_without_active_students(): void
    {
        $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $major = Major::create(['code' => 'TKJ', 'name' => 'Teknik Komputer dan Jaringan']);
        $filled = ClassRoom::create(['name' => 'X TKJ 1', 'grade' => 'X', 'major_id' => $major->id, 'academic_year_id' => $year->id]);
        ClassRoom::create(['name' => 'X TKJ 2', 'grade' => 'X', 'major_id' => $major->id, 'academic_year_id' => $year->id]);

        Student::create(['name' => 'Siti', 'nis' => '1001', 'class_id' => $filled->id, 'status' => 'AKTIF']);
        Student::create(['name' => 'Andi', 'nis' => '1002', 'class_id' => $filled->id, 'status' => 'LULUS']);

        $this->actingAs($this->administrasi)
            ->getJson('/api/v1/dashboard/metrics-administrasi')
            ->assertOk()
            ->assertJsonPath('data.siswa_aktif', 1)
            ->assertJsonPath('data.kelas_tahun_aktif', 2)
            ->assertJsonPath('data.kelas_tanpa_siswa', 1)
            ->assertJsonPath('data.siswa_terbaru.0.name', 'Andi');
    }
}
