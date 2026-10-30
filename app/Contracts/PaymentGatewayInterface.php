<?php

namespace App\Contracts;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Create a new transaction in the payment gateway.
     *
     * @param Payment $payment The payment record from our system
     * @return array Returns an array with 'gateway_reference' and 'checkout_url' (or QR string)
     */
    public function createTransaction(Payment $payment): array;

    /**
     * Verify the signature of an incoming webhook payload.
     *
     * @param array $payload The webhook payload
     * @param string|null $signature The signature provided in header/payload
     * @return bool True if valid, false otherwise
     */
    public function verifySignature(array $payload, ?string $signature = null): bool;

    /**
     * Fetch the actual payment status from the provider.
     *
     * @param string $gatewayReference The reference ID from the gateway
     * @return string Returns normalized status: 'SUCCESS', 'PENDING', 'FAILED', 'EXPIRED', 'CANCELLED'
     */
    public function getPaymentStatus(string $gatewayReference): string;

    /**
     * Parse the webhook payload to extract standardized data.
     *
     * @param array $payload
     * @return array [
     *     'transaction_number' => '...',
     *     'status' => 'SUCCESS|FAILED|EXPIRED...',
     *     'amount' => 100000,
     *     'event_id' => '...'
     * ]
     */
    public function parseWebhookPayload(array $payload): array;
}
