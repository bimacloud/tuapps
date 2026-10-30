<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Major;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClassRoomUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected User $bendahara;

    protected ClassRoom $classRoom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bendahara = User::factory()->create();
        $this->bendahara->assignRole(Role::firstOrCreate(['name' => 'Bendahara']));

        $academicYear = AcademicYear::create(['name' => '2026/2027', 'is_active' => true]);
        $major = Major::create(['name' => 'Teknik Komputer dan Jaringan', 'code' => 'TKJ']);
        $this->classRoom = ClassRoom::create([
            'name' => 'X TKJ 1',
            'grade' => 'X',
            'major_id' => $major->id,
            'academic_year_id' => $academicYear->id,
        ]);
    }

    public function test_bendahara_can_update_a_class(): void
    {
        $this->actingAs($this->bendahara)
            ->putJson("/api/v1/classes/{$this->classRoom->id}", [
                'name' => 'X TKJ 2',
                'grade' => 'X',
                'major_id' => $this->classRoom->major_id,
                'academic_year_id' => $this->classRoom->academic_year_id,
                'homeroom_teacher' => 'Wali Kelas',
                'status' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $this->classRoom->id)
            ->assertJsonPath('data.name', 'X TKJ 2');

        $this->assertDatabaseHas('classes', [
            'id' => $this->classRoom->id,
            'name' => 'X TKJ 2',
            'homeroom_teacher' => 'Wali Kelas',
        ]);
    }

    public function test_bendahara_can_delete_an_empty_class(): void
    {
        $this->actingAs($this->bendahara)
            ->deleteJson("/api/v1/classes/{$this->classRoom->id}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('classes', ['id' => $this->classRoom->id]);
    }
}
