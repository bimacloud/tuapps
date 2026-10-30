<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DateOnlyFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_only_fields_are_sent_without_a_time_so_wib_does_not_shift_the_day(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');

        $bendahara = User::factory()->create();
        $bendahara->assignRole(Role::firstOrCreate(['name' => 'Bendahara']));

        AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->actingAs($bendahara)
            ->getJson('/api/v1/academic-years')
            ->assertOk()
            ->assertJsonPath('data.0.start_date', '2026-07-01')
            ->assertJsonPath('data.0.end_date', '2027-06-30');
    }
}
