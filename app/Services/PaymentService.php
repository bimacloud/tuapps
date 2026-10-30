<?php

namespace App\Services;

use App\Models\CashierSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentItem;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Process a new payment transaction.
     *
     * @param  array{student_id: int, payment_method_id: int, notes?: string|null, items: array<int, array{invoice_item_id: int, amount: float|int|string}>}  $data
     *
     * @throws Exception
     */
    public function processPayment(array $data): Payment
    {
        if (empty($data['items'])) {
            throw new Exception('Minimal satu item pembayaran harus dipilih.');
        }

        $activeSession = CashierSession::where('user_id', Auth::id())
            ->where('status', 'OPEN')
            ->first();

        if (! $activeSession) {
            throw new Exception('Anda harus membuka sesi kasir terlebih dahulu untuk menerima pembayaran.');
        }

        return DB::transaction(function () use ($data, $activeSession) {
            $payableItems = $this->lockPayableItems($data['student_id'], $data['items']);

            $payment = Payment::create([
                'transaction_number' => $this->generateTransactionNumber(),
                'student_id' => $data['student_id'],
                'cashier_id' => Auth::id(),
                'cashier_session_id' => $activeSession->id,
                'payment_method_id' => $data['payment_method_id'],
                'payment_date' => now(),
                'total_amount' => $this->sumPayableItems($payableItems),
                'notes' => $data['notes'] ?? null,
                'status' => 'SUCCESS',
            ]);

            $this->createPaymentItems($payment, $payableItems);
            $this->settleInvoiceItems($payableItems);

            AuditService::log(
                'PAYMENT_CREATED',
                'PAYMENT',
                'Menerima pembayaran sejumlah '.number_format($payment->total_amount, 0, ',', '.'),
                $payment,
                null,
                ['transaction_number' => $payment->transaction_number, 'amount' => $payment->total_amount]
            );

            return $payment->load(['student.classRoom.major', 'paymentMethod', 'cashier']);
        });
    }

    /**
     * Lock the requested invoice items and make sure the student can pay them.
     *
     * Must be called inside a database transaction.
     *
     * @param  array<int, array{invoice_item_id: int, amount: float|int|string}>  $items
     * @return array<int, array{model: InvoiceItem, amount: float}>
     *
     * @throws Exception
     */
    public function lockPayableItems(int $studentId, array $items): array
    {
        if (empty($items)) {
            throw new Exception('Minimal satu item pembayaran harus dipilih.');
        }

        $payableItems = [];

        foreach ($items as $itemData) {
            if ($itemData['amount'] <= 0) {
                throw new Exception('Nominal pembayaran tidak valid.');
            }

            // Use lockForUpdate to prevent race conditions (double payment)
            $invoiceItem = InvoiceItem::where('id', $itemData['invoice_item_id'])->lockForUpdate()->first();

            if (! $invoiceItem) {
                throw new Exception('Item tagihan tidak ditemukan.');
            }

            if ($invoiceItem->status === 'LUNAS') {
                throw new Exception("Item tagihan '{$invoiceItem->paymentType->name}' sudah lunas.");
            }

            if ($invoiceItem->status === 'CANCELLED') {
                throw new Exception("Item tagihan '{$invoiceItem->paymentType->name}' telah dibatalkan.");
            }

            if ($invoiceItem->invoice->student_id != $studentId) {
                throw new Exception('Item tagihan tidak sesuai dengan siswa yang dipilih.');
            }

            $remaining = $invoiceItem->amount - $invoiceItem->paid_amount;
            if ($itemData['amount'] > $remaining) {
                throw new Exception('Nominal pembayaran melebih sisa tagihan untuk item.');
            }

            $payableItems[] = [
                'model' => $invoiceItem,
                'amount' => (float) $itemData['amount'],
            ];
        }

        return $payableItems;
    }

    /**
     * Add the paid amounts to the invoice items and recalculate their parent invoices.
     *
     * @param  array<int, array{model: InvoiceItem, amount: float}>  $payableItems
     */
    public function settleInvoiceItems(array $payableItems): void
    {
        $invoiceIds = [];

        foreach ($payableItems as $payable) {
            $invoiceItem = $payable['model'];

            $invoiceItem->paid_amount += $payable['amount'];
            $invoiceItem->remaining_amount = $invoiceItem->amount - $invoiceItem->paid_amount;
            $invoiceItem->status = $invoiceItem->remaining_amount == 0 ? 'LUNAS' : 'SEBAGIAN';
            $invoiceItem->save();

            $invoiceIds[$invoiceItem->invoice_id] = true;
        }

        foreach (array_keys($invoiceIds) as $invoiceId) {
            $invoice = Invoice::where('id', $invoiceId)->lockForUpdate()->first();

            // Recalculate based on items to be absolutely safe
            $invoice->paid_amount = $invoice->items()->sum('paid_amount');
            $invoice->remaining_amount = $invoice->total_amount - $invoice->paid_amount;

            if ($invoice->remaining_amount == 0) {
                $invoice->status = 'LUNAS';
            } elseif ($invoice->paid_amount > 0) {
                $invoice->status = 'SEBAGIAN';
            }
            $invoice->save();
        }
    }

    /**
     * Generate a unique transaction number (Format: PAY-YYYYMMDD-XXXXX)
     */
    public function generateTransactionNumber(): string
    {
        $datePrefix = Carbon::now()->format('Ymd');
        $prefix = "PAY-{$datePrefix}-";

        // Find the last transaction for today
        $lastPayment = Payment::where('transaction_number', 'LIKE', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastPayment) {
            $lastSequence = (int) str_replace($prefix, '', $lastPayment->transaction_number);
            $sequence = $lastSequence + 1;
        }

        return $prefix.str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, array{model: InvoiceItem, amount: float}>  $payableItems
     */
    private function sumPayableItems(array $payableItems): float
    {
        return array_sum(array_column($payableItems, 'amount'));
    }

    /**
     * @param  array<int, array{model: InvoiceItem, amount: float}>  $payableItems
     */
    private function createPaymentItems(Payment $payment, array $payableItems): void
    {
        foreach ($payableItems as $payable) {
            PaymentItem::create([
                'payment_id' => $payment->id,
                'invoice_id' => $payable['model']->invoice_id,
                'invoice_item_id' => $payable['model']->id,
                'amount' => $payable['amount'],
            ]);
        }
    }
}
