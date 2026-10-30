<?php

namespace Tests\Feature;

use App\Channels\WhatsAppChannel;
use App\Events\PaymentSucceeded;
use App\Models\ClassRoom;
use App\Models\Guardian;
use App\Models\Major;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Notifications\PaymentSuccessNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_with_email_only_is_notified_by_mail(): void
    {
        $guardian = Guardian::create(['name' => 'Pak Budi', 'email' => 'wali@example.com']);
        $payment = $this->paymentFor($guardian);
        Notification::fake();

        event(new PaymentSucceeded($payment));

        Notification::assertSentTo(
            $guardian,
            PaymentSuccessNotification::class,
            fn ($notification, array $channels) => $channels === ['mail']
        );
    }

    public function test_guardian_with_phone_only_is_notified_by_whatsapp(): void
    {
        $guardian = Guardian::create(['name' => 'Bu Siti', 'phone' => '081234567890']);
        $payment = $this->paymentFor($guardian);
        Notification::fake();

        event(new PaymentSucceeded($payment));

        Notification::assertSentTo(
            $guardian,
            PaymentSuccessNotification::class,
            fn ($notification, array $channels) => $channels === [WhatsAppChannel::class]
        );
    }

    public function test_nothing_is_sent_when_student_has_no_guardian(): void
    {
        $payment = $this->paymentFor(null);
        Notification::fake();

        event(new PaymentSucceeded($payment));

        Notification::assertNothingSent();
    }

    private function paymentFor(?Guardian $guardian): Payment
    {
        $major = Major::create(['name' => 'TKJ', 'code' => 'TKJ']);
        $class = ClassRoom::create(['name' => 'X TKJ 1', 'grade' => 'X', 'major_id' => $major->id]);
        $student = Student::create([
            'name' => 'Budi',
            'nis' => '12345',
            'class_id' => $class->id,
            'guardian_id' => $guardian?->id,
            'status' => 'AKTIF',
        ]);
        $method = PaymentMethod::create(['code' => 'ONLINE', 'name' => 'QRIS / Virtual Account', 'is_active' => true]);

        return Payment::create([
            'transaction_number' => 'PAY-20261027-00001',
            'student_id' => $student->id,
            'payment_method_id' => $method->id,
            'payment_date' => now(),
            'total_amount' => 150000,
            'status' => 'SUCCESS',
        ]);
    }
}
