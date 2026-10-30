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

    public function test_bendahara_can_download_the_student_export(): void
    {
        Excel::fake();

        $bendahara = User::factory()->create();
        $bendahara->assignRole(Role::firstOrCreate(['name' => 'Bendahara']));

        $this->actingAs($bendahara)
            ->get('/api/v1/students/export')
            ->assertOk();

        Excel::assertDownloaded('students.xlsx');
    }
}
