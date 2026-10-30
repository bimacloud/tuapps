<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        $kepsekRole = Role::firstOrCreate(['name' => 'Kepala Sekolah']);
        $bendaharaRole = Role::firstOrCreate(['name' => 'Bendahara']);
    }

    public function test_kepsek_can_access_kepsek_only_route(): void
    {
        $kepsek = User::factory()->create();
        $kepsek->assignRole('Kepala Sekolah');

        $token = $kepsek->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/approvals');

        $response->assertStatus(200);
    }

    public function test_bendahara_can_access_bendahara_only_route(): void
    {
        $bendahara = User::factory()->create();
        $bendahara->assignRole('Bendahara');

        $token = $bendahara->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/cashier-sessions/current');

        $response->assertStatus(200);
    }

    public function test_cross_role_authorization_fails(): void
    {
        $bendahara = User::factory()->create();
        $bendahara->assignRole('Bendahara');

        $token = $bendahara->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/approvals');

        // Spatie returns 403 for unauthorized roles
        $response->assertStatus(403);
    }
}
