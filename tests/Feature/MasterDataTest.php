<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Major;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected $kepsek;

    protected $administrasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed'); // Run DatabaseSeeder to setup roles & permissions

        $this->kepsek = User::factory()->create();
        $this->kepsek->assignRole('Kepala Sekolah');

        $this->administrasi = User::factory()->create();
        $this->administrasi->assignRole('Administrasi');
    }

    public function test_kepsek_can_view_academic_years()
    {
        AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-01',
            'end_date' => '2025-06-30',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->kepsek, 'sanctum')->getJson('/api/v1/academic-years');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_kepsek_cannot_create_academic_year()
    {
        $response = $this->actingAs($this->kepsek, 'sanctum')->postJson('/api/v1/academic-years', [
            'name' => '2025/2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_administrasi_can_create_academic_year()
    {
        $response = $this->actingAs($this->administrasi, 'sanctum')->postJson('/api/v1/academic-years', [
            'name' => '2025/2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('academic_years', ['name' => '2025/2026']);
    }

    public function test_only_one_academic_year_active()
    {
        $year1 = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-01',
            'end_date' => '2025-06-30',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->administrasi, 'sanctum')->postJson('/api/v1/academic-years', [
            'name' => '2025/2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        $response->assertStatus(201);

        $year1->refresh();
        $this->assertFalse($year1->is_active);
    }

    public function test_cannot_delete_academic_year_with_relations()
    {
        $major = Major::create(['code' => 'TJKT', 'name' => 'Teknik Jaringan']);
        $year = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-01',
            'end_date' => '2025-06-30',
            'is_active' => false, // must not be active to test relation only
        ]);

        ClassRoom::create([
            'name' => 'X TJKT',
            'major_id' => $major->id,
            'academic_year_id' => $year->id,
            'grade' => 'X',
        ]);

        $response = $this->actingAs($this->administrasi, 'sanctum')->deleteJson('/api/v1/academic-years/'.$year->id);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Tahun Pelajaran tidak dapat dihapus karena sudah digunakan di data kelas']);
    }
}
