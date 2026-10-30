<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_receives_only_school_identity_settings(): void
    {
        Setting::create(['key' => 'school_name', 'value' => 'SMK Contoh']);
        Setting::create(['key' => 'school_logo', 'value' => '/storage/settings/logo.png']);
        Setting::create(['key' => 'payment_gateway_server_key', 'value' => 'rahasia']);

        $this->getJson('/api/v1/school-profile')
            ->assertOk()
            ->assertJsonPath('data.school_name', 'SMK Contoh')
            ->assertJsonPath('data.school_logo', '/storage/settings/logo.png')
            ->assertJsonMissingPath('data.payment_gateway_server_key');
    }
}
