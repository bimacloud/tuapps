<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_twice_keeps_one_row_per_payment_method(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('payment_methods', 3);
        $this->assertDatabaseHas('payment_methods', ['code' => 'CASH']);
        $this->assertDatabaseHas('payment_methods', ['code' => 'TRANSFER']);
        $this->assertDatabaseHas('payment_methods', ['code' => 'ONLINE']);
    }}
