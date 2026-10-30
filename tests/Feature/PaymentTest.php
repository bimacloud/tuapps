<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\ClassRoom;
use App\Models\Major;
use App\Models\AcademicYear;
use App\Models\PaymentType;
use App\Models\PaymentRate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentMethod;
use App\Models\Payment;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected $bendahara;
    protected $kepsek;
    protected $student;
    protected $invoice;
    protected $invoiceItem;
    protected $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();
        
        $bendaharaRole = Role::firstOrCreate(['name' => 'Bendahara']);
        $kepsekRole = Role::firstOrCreate(['name' => 'Kepala Sekolah']);

        $this->bendahara = User::factory()->create();
        $this->bendahara->assignRole($bendaharaRole);

        $this->kepsek = User::factory()->create();
        $this->kepsek->assignRole($kepsekRole);

        $academicYear = AcademicYear::create(['name' => '2024/2025', 'is_active' => true]);
        $major = Major::create(['name' => 'TKJ', 'code' => 'TKJ']);
        $class = ClassRoom::create(['name' => 'X TKJ 1', 'grade' => 'X', 'major_id' => $major->id]);
        $this->student = Student::create(['name' => 'Budi', 'nis' => '12345', 'class_id' => $class->id, 'status' => 'AKTIF']);

        $paymentType = PaymentType::create(['name' => 'SPP', 'type' => 'BULANAN', 'status' => true]);
        $paymentRate = PaymentRate::create(['payment_type_id' => $paymentType->id, 'amount' => 500000, 'academic_year_id' => $academicYear->id]);

        $this->invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'student_id' => $this->student->id,
            'academic_year_id' => $academicYear->id,
            'total_amount' => 500000,
            'paid_amount' => 0,
            'remaining_amount' => 500000,
            'status' => 'BELUM BAYAR'
        ]);

        $this->invoiceItem = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'payment_type_id' => $paymentType->id,
            'period' => 'Januari',
            'amount' => 500000,
            'paid_amount' => 0,
            'remaining_amount' => 500000,
            'status' => 'BELUM BAYAR'
        ]);

        $this->paymentMethod = PaymentMethod::create(['code' => 'CASH', 'name' => 'Tunai', 'is_active' => true]);

        // Open Cashier Session for Bendahara
        \App\Models\CashierSession::create([
            'user_id' => $this->bendahara->id,
            'start_time' => now(),
            'starting_balance' => 0,
            'status' => 'OPEN',
        ]);
    }

    public function test_successful_full_payment()
    {
        $response = $this->actingAs($this->bendahara)->postJson('/api/v1/payments', [
            'student_id' => $this->student->id,
            'payment_method_id' => $this->paymentMethod->id,
            'items' => [
                ['invoice_item_id' => $this->invoiceItem->id, 'amount' => 500000]
            ],
            'notes' => 'Lunas'
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('invoices', [
            'id' => $this->invoice->id,
            'status' => 'LUNAS',
            'paid_amount' => 500000,
            'remaining_amount' => 0
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'id' => $this->invoiceItem->id,
            'status' => 'LUNAS',
            'paid_amount' => 500000,
            'remaining_amount' => 0
        ]);

        $this->assertDatabaseHas('payments', [
            'student_id' => $this->student->id,
            'total_amount' => 500000,
            'status' => 'SUCCESS'
        ]);
    }

    public function test_successful_partial_payment()
    {
        $response = $this->actingAs($this->bendahara)->postJson('/api/v1/payments', [
            'student_id' => $this->student->id,
            'payment_method_id' => $this->paymentMethod->id,
            'items' => [
                ['invoice_item_id' => $this->invoiceItem->id, 'amount' => 200000]
            ],
            'notes' => 'DP'
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('invoices', [
            'id' => $this->invoice->id,
            'status' => 'SEBAGIAN',
            'paid_amount' => 200000,
            'remaining_amount' => 300000
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'id' => $this->invoiceItem->id,
            'status' => 'SEBAGIAN',
            'paid_amount' => 200000,
            'remaining_amount' => 300000
        ]);
    }

    public function test_amount_less_than_zero_rejected()
    {
        $response = $this->actingAs($this->bendahara)->postJson('/api/v1/payments', [
            'student_id' => $this->student->id,
            'payment_method_id' => $this->paymentMethod->id,
            'items' => [
                ['invoice_item_id' => $this->invoiceItem->id, 'amount' => 0]
            ],
        ]);

        $response->assertStatus(422); // Validation error
    }

    public function test_overpayment_rejected()
    {
        $response = $this->actingAs($this->bendahara)->postJson('/api/v1/payments', [
            'student_id' => $this->student->id,
            'payment_method_id' => $this->paymentMethod->id,
            'items' => [
                ['invoice_item_id' => $this->invoiceItem->id, 'amount' => 600000]
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Gagal memproses pembayaran: Nominal pembayaran melebih sisa tagihan untuk item.']);
    }

    public function test_cancelled_invoice_rejected()
    {
        $this->invoiceItem->update(['status' => 'CANCELLED']);

        $response = $this->actingAs($this->bendahara)->postJson('/api/v1/payments', [
            'student_id' => $this->student->id,
            'payment_method_id' => $this->paymentMethod->id,
            'items' => [
                ['invoice_item_id' => $this->invoiceItem->id, 'amount' => 500000]
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_transaction_number_uniqueness()
    {
        $service = app(\App\Services\PaymentService::class);
        $tx1 = $service->generateTransactionNumber();
        
        // Mock a payment record
        Payment::create([
            'transaction_number' => $tx1,
            'student_id' => $this->student->id,
            'cashier_id' => $this->bendahara->id,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_date' => now(),
            'total_amount' => 100,
            'status' => 'SUCCESS'
        ]);

        $tx2 = $service->generateTransactionNumber();
        
        $this->assertNotEquals($tx1, $tx2);
    }

    public function test_payment_is_recorded_in_the_open_cashier_session()
    {
        $session = \App\Models\CashierSession::where('user_id', $this->bendahara->id)->first();

        $response = $this->actingAs($this->bendahara)->postJson('/api/v1/payments', [
            'student_id' => $this->student->id,
            'payment_method_id' => $this->paymentMethod->id,
            'items' => [
                ['invoice_item_id' => $this->invoiceItem->id, 'amount' => 500000]
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('payments', [
            'id' => $response->json('data.id'),
            'cashier_session_id' => $session->id,
        ]);
    }

    public function test_history_search_keeps_the_date_filter()
    {
        foreach (['2026-01-01' => 'PAY-20260101-00001', '2026-01-02' => 'PAY-20260102-00001'] as $date => $number) {
            Payment::create([
                'transaction_number' => $number,
                'student_id' => $this->student->id,
                'cashier_id' => $this->bendahara->id,
                'payment_method_id' => $this->paymentMethod->id,
                'payment_date' => $date . ' 10:00:00',
                'total_amount' => 100000,
                'status' => 'SUCCESS',
            ]);
        }

        $response = $this->actingAs($this->kepsek)->getJson('/api/v1/payments?search=PAY-2026&date=2026-01-02');

        $response->assertStatus(200);
        $this->assertSame(['PAY-20260102-00001'], array_column($response->json('data'), 'transaction_number'));
    }

    public function test_kepala_sekolah_cannot_create_payment()
    {
        $response = $this->actingAs($this->kepsek)->postJson('/api/v1/payments', [
            'student_id' => $this->student->id,
            'payment_method_id' => $this->paymentMethod->id,
            'items' => [
                ['invoice_item_id' => $this->invoiceItem->id, 'amount' => 500000]
            ],
        ]);

        // Assumes role middleware throws 403 Forbidden
        $response->assertStatus(403);
    }
}
