<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrasi_can_download_the_student_export(): void
    {
        Excel::fake();

        $administrasi = User::factory()->create();
        $administrasi->assignRole(Role::firstOrCreate(['name' => 'Administrasi']));

        $this->actingAs($administrasi)
            ->get('/api/v1/students/export')
            ->assertOk();

        Excel::assertDownloaded('students.xlsx');
    }
}
