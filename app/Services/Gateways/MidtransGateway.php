<?php

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Exception;

class MidtransGateway implements PaymentGatewayInterface
{
    protected $serverKey;
    protected $isProduction;
    protected $baseUrl;

    public function __construct()
    {
        // For actual implementation, read from config('services.midtrans.server_key')
        $this->serverKey = config('services.midtrans.server_key', 'SB-Mid-server-DUMMY');
        $this->isProduction = config('services.midtrans.is_production', false);
        
        $this->baseUrl = $this->isProduction 
            ? 'https://app.midtrans.com/snap/v1' 
            : 'https://app.sandbox.midtrans.com/snap/v1';
    }

    public function createTransaction(Payment $payment): array
    {
        // In a real implementation, we would construct the item_details array from $payment->items
        $payload = [
            'transaction_details' => [
                'order_id' => $payment->transaction_number,
                'gross_amount' => (int) $payment->total_amount,
            ],
            'customer_details' => [
                'first_name' => $payment->student->name,
                'email' => $payment->student->nis . '@smktikwt.edu',
            ],
        ];

        // Using Http facade to make the request
        $response = Http::withBasicAuth($this->serverKey, '')
            ->post($this->baseUrl . '/transactions', $payload);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'gateway_reference' => $data['token'] ?? null,
                'checkout_url' => $data['redirect_url'] ?? null,
            ];
        }

        throw new Exception("Gagal membuat transaksi Midtrans: " . $response->body());
    }

    public function verifySignature(array $payload, ?string $signature = null): bool
    {
        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $providedSignature = $payload['signature_key'] ?? $signature;

        $calculatedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);

        return hash_equals($calculatedSignature, $providedSignature ?? '');
    }

    public function getPaymentStatus(string $gatewayReference): string
    {
        // Since Midtrans uses order_id for status checks, $gatewayReference should ideally be order_id
        $url = $this->isProduction 
            ? "https://api.midtrans.com/v2/{$gatewayReference}/status" 
            : "https://api.sandbox.midtrans.com/v2/{$gatewayReference}/status";

        $response = Http::withBasicAuth($this->serverKey, '')->get($url);

        if ($response->successful()) {
            $status = $response->json('transaction_status');
            return $this->mapMidtransStatus($status);
        }

        throw new Exception("Gagal mengecek status transaksi.");
    }

    public function parseWebhookPayload(array $payload): array
    {
        // Midtrans sends several notifications (pending, settlement, expire, ...) with the same
        // transaction_id, so the status is part of the idempotency key.
        $eventId = isset($payload['transaction_id'])
            ? $payload['transaction_id'].':'.($payload['transaction_status'] ?? '')
            : uniqid();

        return [
            'transaction_number' => $payload['order_id'] ?? '',
            'status' => $this->mapMidtransStatus($payload['transaction_status'] ?? ''),
            'amount' => (float) ($payload['gross_amount'] ?? 0),
            'event_id' => $eventId,
        ];
    }

    protected function mapMidtransStatus(string $midtransStatus): string
    {
        switch ($midtransStatus) {
            case 'capture':
            case 'settlement':
                return 'SUCCESS';
            case 'pending':
                return 'PENDING';
            case 'deny':
            case 'cancel':
            case 'expire':
                return 'EXPIRED'; // Or FAILED/CANCELLED depending on mapping
            default:
                return 'FAILED';
        }
    }
}
