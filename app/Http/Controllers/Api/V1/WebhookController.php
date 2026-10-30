<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WebhookLog;
use App\Models\Payment;
use App\Models\Invoice;
use App\Services\PaymentGatewayService;
use App\Services\PaymentService;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Events\PaymentSucceeded;

class WebhookController extends Controller
{
    protected PaymentGatewayService $gateway;

    protected PaymentService $paymentService;

    public function __construct(PaymentGatewayService $gateway, PaymentService $paymentService)
    {
        $this->gateway = $gateway;
        $this->paymentService = $paymentService;
    }

    public function handle(Request $request)
    {
        $payload = $request->all();
        
        // 1. Parse standard fields
        $parsed = $this->gateway->parseWebhookPayload($payload);
        $eventId = $parsed['event_id'];
        $signature = $request->header('X-Signature') ?? $payload['signature_key'] ?? null;

        // 2. Check Idempotency (Duplicate webhook)
        $existingLog = WebhookLog::where('event_id', $eventId)->first();
        if ($existingLog) {
            return response()->json(['message' => 'Webhook already processed.'], 200);
        }

        // 3. Create Webhook Log (PENDING)
        $log = WebhookLog::create([
            'provider' => 'MIDTRANS', // Dynamic in multi-gateway
            'event_id' => $eventId,
            'payload' => $payload,
            'signature' => $signature,
            'received_at' => now(),
            'processing_status' => 'PENDING',
        ]);

        try {
            // 4. Verify Signature
            if (!$this->gateway->verifySignature($payload, $signature)) {
                throw new Exception("Invalid signature.");
            }

            // 5. Update DB inside Transaction
            DB::beginTransaction();

            // 6. Find and lock Payment so concurrent notifications are processed one at a time
            $payment = Payment::where('transaction_number', $parsed['transaction_number'])->lockForUpdate()->first();
            if (!$payment) {
                throw new Exception("Payment not found.");
            }

            // 7. Verify Amount (critical for security)
            if (abs($payment->total_amount - $parsed['amount']) > 0.01) {
                throw new Exception("Amount mismatch. Expected: {$payment->total_amount}, Received: {$parsed['amount']}");
            }

            // We only process if payment is still PENDING
            if ($payment->status === 'PENDING') {
                if ($parsed['status'] === 'SUCCESS') {
                    $this->settle($payment);
                } else {
                    $payment->status = $parsed['status'];
                    $payment->save();
                }
            }

            DB::commit();

            $log->update(['processing_status' => 'SUCCESS']);

            return response()->json(['message' => 'Webhook processed successfully.']);

        } catch (Exception $e) {
            DB::rollBack();
            $log->update([
                'processing_status' => 'FAILED',
                'error_message' => $e->getMessage()
            ]);

            // We return 200 to gateway so they don't retry if it's a permanent error (like invalid signature)
            // But realistically, if it's a 500 issue we might return 500. For now, 200.
            return response()->json(['message' => 'Error processing webhook: ' . $e->getMessage()], 200);
        }
    }

    /**
     * Settle a paid online payment, or flag it for refund when its bills were already paid elsewhere.
     */
    private function settle(Payment $payment): void
    {
        $items = $payment->items->map(fn ($item) => [
            'invoice_item_id' => $item->invoice_item_id,
            'amount' => $item->amount,
        ])->all();

        try {
            $payableItems = $this->paymentService->lockPayableItems($payment->student_id, $items);
        } catch (Exception $e) {
            $payment->status = 'REFUND_REQUIRED';
            $payment->save();

            AuditService::log(
                'ONLINE_PAYMENT_REFUND_REQUIRED',
                'PAYMENT',
                "Pembayaran online {$payment->transaction_number} diterima tetapi tagihan tidak bisa dilunasi ({$e->getMessage()}). Dana perlu dikembalikan.",
                $payment
            );

            return;
        }

        $payment->status = 'SUCCESS';
        $payment->save();

        $this->paymentService->settleInvoiceItems($payableItems);

        AuditService::log(
            'ONLINE_PAYMENT_SUCCESS',
            'PAYMENT',
            "Pembayaran online {$payment->transaction_number} berhasil via Webhook",
            $payment
        );

        event(new PaymentSucceeded($payment));
    }
}
