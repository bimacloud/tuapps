<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\PaymentType;
use App\Models\PaymentRate;
use App\Models\AcademicYear;
use App\Models\Major;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\Invoice;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected $bendahara;
    protected $academicYear;
    protected $classRoom;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->artisan('db:seed'); // Run DatabaseSeeder to setup roles & permissions

        $this->bendahara = User::factory()->create();
        $this->bendahara->assignRole('Bendahara');

        $this->academicYear = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-01',
            'end_date' => '2025-06-30',
            'is_active' => true
        ]);

        $major = Major::create(['code' => 'TJKT', 'name' => 'Teknik Jaringan']);
        
        $this->classRoom = ClassRoom::create([
            'name' => 'X TJKT',
            'major_id' => $major->id,
            'academic_year_id' => $this->academicYear->id,
            'grade' => 'X'
        ]);

        // Create 2 students
        Student::create([
            'name' => 'Student 1',
            'class_id' => $this->classRoom->id,
            'status' => 'AKTIF'
        ]);
        Student::create([
            'name' => 'Student 2',
            'class_id' => $this->classRoom->id,
            'status' => 'AKTIF'
        ]);
    }

    public function test_can_create_payment_type()
    {
        $response = $this->actingAs($this->bendahara, 'sanctum')->postJson('/api/v1/payment-types', [
            'code' => 'SPP',
            'name' => 'Sumbangan Pembinaan Pendidikan',
            'type' => 'BULANAN',
            'status' => true
        ]);
        
        $response->assertStatus(201);
        $this->assertDatabaseHas('payment_types', ['code' => 'SPP']);
    }

    public function test_cannot_create_duplicate_active_rate()
    {
        $pt = PaymentType::create([
            'code' => 'SPP',
            'name' => 'SPP',
            'type' => 'BULANAN',
            'status' => true
        ]);

        // Rate 1
        $this->actingAs($this->bendahara, 'sanctum')->postJson('/api/v1/payment-rates', [
            'payment_type_id' => $pt->id,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->classRoom->id,
            'amount' => 150000,
            'status' => true
        ])->assertStatus(201);

        // Rate 2 (duplicate)
        $response = $this->actingAs($this->bendahara, 'sanctum')->postJson('/api/v1/payment-rates', [
            'payment_type_id' => $pt->id,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->classRoom->id,
            'amount' => 200000,
            'status' => true
        ]);

        $response->assertStatus(422)
                 ->assertJson(['message' => 'Tarif aktif untuk Jenis Pembayaran, Tahun Ajaran, dan Kelas ini sudah ada.']);
    }

    public function test_can_generate_invoices_and_prevent_duplicate()
    {
        $pt = PaymentType::create([
            'code' => 'SPP',
            'name' => 'SPP',
            'type' => 'BULANAN',
            'status' => true
        ]);

        PaymentRate::create([
            'payment_type_id' => $pt->id,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->classRoom->id,
            'amount' => 150000,
            'status' => true
        ]);

        // First Generate
        $response1 = $this->actingAs($this->bendahara, 'sanctum')->postJson('/api/v1/invoices/generate', [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->classRoom->id,
            'payment_type_id' => $pt->id,
            'period' => 'Juli'
        ]);

        $response1->assertStatus(200)
                  ->assertJsonPath('generated', 2);
                  
        $this->assertEquals(2, Invoice::count());

        // Second Generate (Should be skipped)
        $response2 = $this->actingAs($this->bendahara, 'sanctum')->postJson('/api/v1/invoices/generate', [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->classRoom->id,
            'payment_type_id' => $pt->id,
            'period' => 'Juli'
        ]);

        $response2->assertStatus(200)
                  ->assertJsonPath('skipped', 2)
                  ->assertJsonPath('generated', 0);
                  
        $this->assertEquals(2, Invoice::count()); // Still 2
    }
}
