<?php

namespace Tests\Feature;

use App\Models\CashierSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashierSessionHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $bendahara;

    protected User $kepsek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bendahara = User::factory()->create(['name' => 'Bendahara Satu']);
        $this->bendahara->assignRole(Role::firstOrCreate(['name' => 'Bendahara']));

        $this->kepsek = User::factory()->create();
        $this->kepsek->assignRole(Role::firstOrCreate(['name' => 'Kepala Sekolah']));

        CashierSession::create([
            'user_id' => $this->bendahara->id,
            'start_time' => '2026-10-29 07:00:00',
            'end_time' => '2026-10-29 15:00:00',
            'starting_balance' => 100000,
            'expected_balance' => 550000,
            'actual_balance' => 545000,
            'difference' => -5000,
            'notes' => 'Uang kembalian kurang',
            'status' => 'CLOSED',
        ]);

        CashierSession::create([
            'user_id' => $this->bendahara->id,
            'start_time' => '2026-10-30 07:00:00',
            'end_time' => '2026-10-30 15:00:00',
            'starting_balance' => 0,
            'expected_balance' => 300000,
            'actual_balance' => 300000,
            'difference' => 0,
            'status' => 'CLOSED',
        ]);
    }

    public function test_kepala_sekolah_sees_closed_sessions_with_their_difference_and_notes(): void
    {
        $this->actingAs($this->kepsek)
            ->getJson('/api/v1/cashier-sessions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.start_time', fn ($value) => str_starts_with($value, '2026-10-30'))
            ->assertJsonPath('data.1.difference', fn ($value) => (int) $value === -5000)
            ->assertJsonPath('data.1.notes', 'Uang kembalian kurang')
            ->assertJsonPath('data.1.user.name', 'Bendahara Satu')
            ->assertJsonPath('data.1.cash_received', fn ($value) => (int) $value === 0);
    }

    public function test_only_sessions_with_a_difference_can_be_listed(): void
    {
        $this->actingAs($this->bendahara)
            ->getJson('/api/v1/cashier-sessions?only_difference=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.notes', 'Uang kembalian kurang');
    }

    public function test_history_requires_authentication(): void
    {
        $this->getJson('/api/v1/cashier-sessions')->assertUnauthorized();
    }
}
