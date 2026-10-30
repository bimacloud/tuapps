<?php

namespace Tests\Feature;

use App\Events\PaymentSucceeded;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class Phase7OnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected Student $student;

    protected Invoice $invoice;

    protected InvoiceItem $invoiceItem;

    protected PaymentMethod $onlineMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $academicYear = \App\Models\AcademicYear::create([
            'name' => '2023/2024',
            'is_active' => true,
        ]);

        $major = \App\Models\Major::create([
            'name' => 'Rekayasa Perangkat Lunak',
            'code' => 'RPL',
            'is_active' => true,
        ]);

        $classRoom = \App\Models\ClassRoom::create([
            'name' => 'X RPL 1',
            'grade' => 'X',
            'major_id' => $major->id,
            'academic_year_id' => $academicYear->id,
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'nis' => '123456',
            'nisn' => '1234567890',
            'name' => 'John Doe',
            'gender' => 'L',
            'class_id' => $classRoom->id,
            'status' => 'AKTIF',
        ]);

        $paymentType = PaymentType::create([
            'name' => 'SPP',
            'type' => 'BULANAN',
            'is_active' => true,
        ]);

        $this->invoice = Invoice::create([
            'student_id' => $this->student->id,
            'academic_year_id' => $academicYear->id,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'BELUM BAYAR',
        ]);

        $this->invoiceItem = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'payment_type_id' => $paymentType->id,
            'amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'BELUM BAYAR',
        ]);

        $this->onlineMethod = PaymentMethod::create([
            'name' => 'Midtrans Online',
            'code' => 'ONLINE',
            'is_active' => true,
        ]);
    }

    public function test_webhook_successfully_updates_payment()
    {
        $this->pendingPayment('PAY-ONLINE-TEST');
        Event::fake([PaymentSucceeded::class]);

        $response = $this->postJson('/api/v1/payment-gateway/webhook', $this->webhookPayload('PAY-ONLINE-TEST', 'capture'));

        $response->assertStatus(200);

        // Assert payment is SUCCESS
        $this->assertDatabaseHas('payments', [
            'transaction_number' => 'PAY-ONLINE-TEST',
            'status' => 'SUCCESS',
        ]);

        // Assert invoice is LUNAS
        $this->assertDatabaseHas('invoices', [
            'id' => $this->invoice->id,
            'status' => 'LUNAS',
            'paid_amount' => 150000,
            'remaining_amount' => 0,
        ]);

        // Assert WebhookLog is logged
        $this->assertDatabaseHas('webhook_logs', [
            'event_id' => 'midtrans-12345:capture',
            'processing_status' => 'SUCCESS',
        ]);

        Event::assertDispatched(PaymentSucceeded::class);
    }

    public function test_webhook_settlement_after_pending_notification_settles_payment()
    {
        $this->pendingPayment('PAY-ONLINE-TEST');
        Event::fake([PaymentSucceeded::class]);

        $this->postJson('/api/v1/payment-gateway/webhook', $this->webhookPayload('PAY-ONLINE-TEST', 'pending', '201'));
        $response = $this->postJson('/api/v1/payment-gateway/webhook', $this->webhookPayload('PAY-ONLINE-TEST', 'settlement'));

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'transaction_number' => 'PAY-ONLINE-TEST',
            'status' => 'SUCCESS',
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'id' => $this->invoiceItem->id,
            'status' => 'LUNAS',
            'paid_amount' => 150000,
        ]);
        Event::assertDispatched(PaymentSucceeded::class);
    }

    public function test_webhook_flags_refund_when_bill_was_already_paid_at_the_counter()
    {
        $this->pendingPayment('PAY-ONLINE-TEST');
        $this->invoiceItem->update(['paid_amount' => 150000, 'remaining_amount' => 0, 'status' => 'LUNAS']);
        Event::fake([PaymentSucceeded::class]);

        $response = $this->postJson('/api/v1/payment-gateway/webhook', $this->webhookPayload('PAY-ONLINE-TEST', 'settlement'));

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'transaction_number' => 'PAY-ONLINE-TEST',
            'status' => 'REFUND_REQUIRED',
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'id' => $this->invoiceItem->id,
            'paid_amount' => 150000,
            'remaining_amount' => 0,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ONLINE_PAYMENT_REFUND_REQUIRED',
            'model_type' => Payment::class,
        ]);
        Event::assertNotDispatched(PaymentSucceeded::class);
    }

    private function pendingPayment(string $transactionNumber): Payment
    {
        $payment = Payment::create([
            'transaction_number' => $transactionNumber,
            'student_id' => $this->student->id,
            'payment_method_id' => $this->onlineMethod->id,
            'payment_date' => now(),
            'total_amount' => 150000,
            'status' => 'PENDING',
        ]);

        PaymentItem::create([
            'payment_id' => $payment->id,
            'invoice_id' => $this->invoice->id,
            'invoice_item_id' => $this->invoiceItem->id,
            'amount' => 150000,
        ]);

        return $payment;
    }

    /**
     * @return array<string, string>
     */
    private function webhookPayload(string $orderId, string $transactionStatus, string $statusCode = '200'): array
    {
        $serverKey = config('services.midtrans.server_key', 'SB-Mid-server-DUMMY');

        return [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => '150000',
            'transaction_status' => $transactionStatus,
            'transaction_id' => 'midtrans-12345',
            'signature_key' => hash('sha512', $orderId.$statusCode.'150000'.$serverKey),
        ];
    }
}
