<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Support\Facades\App;

class PaymentGatewayService
{
    protected PaymentGatewayInterface $gateway;

    public function __construct()
    {
        // For Phase 7, we hardcode the resolution to MidtransGateway,
        // but in a real app, this can be bound in AppServiceProvider based on config
        $this->gateway = App::make(\App\Services\Gateways\MidtransGateway::class);
    }

    /**
     * Create a new transaction in the gateway.
     */
    public function createTransaction(Payment $payment): array
    {
        return $this->gateway->createTransaction($payment);
    }

    /**
     * Verify webhook signature.
     */
    public function verifySignature(array $payload, ?string $signature = null): bool
    {
        return $this->gateway->verifySignature($payload, $signature);
    }

    /**
     * Get the exact status from gateway API.
     */
    public function getPaymentStatus(string $gatewayReference): string
    {
        return $this->gateway->getPaymentStatus($gatewayReference);
    }

    /**
     * Parse webhook payload into standardized format.
     */
    public function parseWebhookPayload(array $payload): array
    {
        return $this->gateway->parseWebhookPayload($payload);
    }
}
