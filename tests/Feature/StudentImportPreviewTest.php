<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Major;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_marks_rows_with_unknown_class_as_invalid(): void
    {
        $bendahara = User::factory()->create();
        $bendahara->assignRole(Role::firstOrCreate(['name' => 'Bendahara']));

        $academicYear = AcademicYear::create(['name' => '2026/2027', 'is_active' => true]);
        $major = Major::create(['name' => 'Teknik Komputer dan Jaringan', 'code' => 'TKJ']);
        ClassRoom::create(['name' => 'X TKJ 1', 'grade' => 'X', 'major_id' => $major->id, 'academic_year_id' => $academicYear->id]);

        $csv = "nama_lengkap,kelas,nis\nSiswa Satu,X TKJ 1,2026101\nSiswa Dua,X TKJ 9,2026102\n";
        $file = UploadedFile::fake()->createWithContent('siswa.csv', $csv);

        $response = $this->actingAs($bendahara)
            ->postJson('/api/v1/students/import-preview', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('has_errors', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.is_valid', true)
            ->assertJsonPath('data.1.is_valid', false)
            ->assertJsonPath('data.1.errors.0', 'Kelas tidak ditemukan: X TKJ 9');
    }
}
