<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Major;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashierSessionTest extends TestCase
{
    use RefreshDatabase;

    protected User $bendahara;

    protected Student $student;

    protected InvoiceItem $invoiceItem;

    protected PaymentMethod $cash;

    protected PaymentMethod $transfer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bendahara = User::factory()->create();
        $this->bendahara->assignRole(Role::firstOrCreate(['name' => 'Bendahara']));

        $academicYear = AcademicYear::create(['name' => '2026/2027', 'is_active' => true]);
        $major = Major::create(['name' => 'TKJ', 'code' => 'TKJ']);
        $class = ClassRoom::create(['name' => 'X TKJ 1', 'grade' => 'X', 'major_id' => $major->id]);
        $this->student = Student::create(['name' => 'Budi', 'nis' => '12345', 'class_id' => $class->id, 'status' => 'AKTIF']);

        $paymentType = PaymentType::create(['name' => 'SPP', 'type' => 'BULANAN', 'status' => true]);
        $invoice = Invoice::create([
            'student_id' => $this->student->id,
            'academic_year_id' => $academicYear->id,
            'total_amount' => 500000,
            'paid_amount' => 0,
            'remaining_amount' => 500000,
            'status' => 'BELUM BAYAR',
        ]);
        $this->invoiceItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'payment_type_id' => $paymentType->id,
            'period' => 'Juli',
            'amount' => 500000,
            'paid_amount' => 0,
            'remaining_amount' => 500000,
            'status' => 'BELUM BAYAR',
        ]);

        $this->cash = PaymentMethod::create(['code' => 'CASH', 'name' => 'Tunai (Cash)', 'is_active' => true]);
        $this->transfer = PaymentMethod::create(['code' => 'TRANSFER', 'name' => 'Transfer Bank', 'is_active' => true]);
    }

    public function test_session_opens_with_an_empty_drawer_without_counting(): void
    {
        $this->actingAs($this->bendahara)
            ->postJson('/api/v1/cashier-sessions/open', ['starting_balance' => 500000])
            ->assertCreated();

        $this->assertDatabaseHas('cashier_sessions', [
            'user_id' => $this->bendahara->id,
            'status' => 'OPEN',
            'starting_balance' => 0,
        ]);
    }

    public function test_summary_counts_only_cash_payments_made_in_the_session(): void
    {
        $this->actingAs($this->bendahara)->postJson('/api/v1/cashier-sessions/open')->assertCreated();
        $this->pay($this->cash, 200000);
        $this->pay($this->transfer, 300000);

        $response = $this->actingAs($this->bendahara)->getJson('/api/v1/cashier-sessions/summary');

        $response->assertOk();
        $this->assertSame(200000.0, (float) $response->json('data.total_cash_payments'));
        $this->assertSame(200000.0, (float) $response->json('data.expected_balance'));
    }

    public function test_close_compares_actual_balance_with_cash_received(): void
    {
        $this->actingAs($this->bendahara)->postJson('/api/v1/cashier-sessions/open')->assertCreated();
        $this->pay($this->cash, 200000);
        $this->pay($this->transfer, 300000);

        $response = $this->actingAs($this->bendahara)->postJson('/api/v1/cashier-sessions/close', [
            'actual_balance' => 200000,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('cashier_sessions', [
            'user_id' => $this->bendahara->id,
            'status' => 'CLOSED',
            'expected_balance' => 200000,
            'difference' => 0,
        ]);
    }

    private function pay(PaymentMethod $method, int $amount): void
    {
        $this->actingAs($this->bendahara)->postJson('/api/v1/payments', [
            'student_id' => $this->student->id,
            'payment_method_id' => $method->id,
            'items' => [
                ['invoice_item_id' => $this->invoiceItem->id, 'amount' => $amount],
            ],
        ])->assertCreated();
    }
}
