<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ApprovalRequest;
use App\Models\Payment;
use App\Models\PaymentRate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\AuditService;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    /**
     * List all pending requests.
     */
    public function index(Request $request)
    {
        $query = ApprovalRequest::with(['requester', 'approver', 'record'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        return response()->json([
            'data' => $query->get()
        ]);
    }

    /**
     * Request cancellation of a payment (Bendahara).
     */
    public function requestCancellation(Request $request, $paymentId)
    {
        $request->validate([
            'reason' => 'required|string'
        ]);

        $payment = Payment::findOrFail($paymentId);

        if ($payment->status !== 'SUCCESS') {
            return response()->json(['message' => 'Hanya pembayaran SUCCESS yang bisa dibatalkan.'], 422);
        }

        // Check if there is an existing pending request
        $existing = ApprovalRequest::where('record_type', Payment::class)
            ->where('record_id', $payment->id)
            ->where('status', 'PENDING')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Permintaan pembatalan sudah diajukan sebelumnya dan masih PENDING.'], 422);
        }

        $approval = ApprovalRequest::create([
            'requester_id' => Auth::id(),
            'type' => 'BATAL_BAYAR',
            'record_type' => Payment::class,
            'record_id' => $payment->id,
            'reason' => $request->reason,
            'status' => 'PENDING'
        ]);

        AuditService::log(
            'APPROVAL_REQUESTED',
            'APPROVAL',
            "Mengajukan pembatalan pembayaran {$payment->transaction_number}",
            $approval
        );

        return response()->json([
            'message' => 'Permintaan pembatalan berhasil diajukan',
            'data' => $approval
        ], 201);
    }

    /**
     * Approve a request (Kepsek).
     */
    public function approve(Request $request, $id)
    {
        $request->validate([
            'review_note' => 'nullable|string'
        ]);

        $approval = ApprovalRequest::findOrFail($id);

        if ($approval->status !== 'PENDING') {
            return response()->json(['message' => 'Permintaan ini sudah diproses.'], 422);
        }

        if ($approval->requester_id === Auth::id()) {
            return response()->json(['message' => 'Anda tidak bisa menyetujui permintaan Anda sendiri (Self-approval).'], 422);
        }

        DB::beginTransaction();
        try {
            $approval->status = 'APPROVED';
            $approval->approver_id = Auth::id();
            $approval->review_note = $request->review_note;
            $approval->reviewed_at = Carbon::now();
            $approval->save();

            if ($approval->type === 'BATAL_BAYAR' && $approval->record_type === Payment::class) {
                $payment = Payment::with('items.invoiceItem.invoice')->find($approval->record_id);
                if ($payment && $payment->status === 'SUCCESS') {
                    $payment->status = 'CANCELLED';
                    $payment->save();

                    // Restore invoice items
                    $invoicesToUpdate = [];
                    foreach ($payment->items as $item) {
                        $invoiceItem = $item->invoiceItem;
                        $invoiceItem->paid_amount -= $item->amount;
                        $invoiceItem->remaining_amount += $item->amount;
                        $invoiceItem->status = ($invoiceItem->paid_amount == 0) ? 'BELUM BAYAR' : 'SEBAGIAN';
                        $invoiceItem->save();

                        $invoicesToUpdate[$invoiceItem->invoice_id] = $invoiceItem->invoice;
                    }

                    // Restore parent invoices
                    foreach ($invoicesToUpdate as $invoice) {
                        $totalPaid = $invoice->items()->sum('paid_amount');
                        $invoice->paid_amount = $totalPaid;
                        $invoice->remaining_amount = $invoice->total_amount - $totalPaid;
                        $invoice->status = ($totalPaid == 0) ? 'BELUM BAYAR' : 'SEBAGIAN';
                        $invoice->save();
                    }
                }
            }

            AuditService::log(
                'APPROVAL_APPROVED',
                'APPROVAL',
                "Menyetujui permintaan " . $approval->type,
                $approval
            );

            DB::commit();

            return response()->json([
                'message' => 'Permintaan berhasil disetujui'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses persetujuan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a request (Kepsek).
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'review_note' => 'required|string'
        ]);

        $approval = ApprovalRequest::findOrFail($id);

        if ($approval->status !== 'PENDING') {
            return response()->json(['message' => 'Permintaan ini sudah diproses.'], 422);
        }

        if ($approval->requester_id === Auth::id()) {
            return response()->json(['message' => 'Anda tidak bisa menolak permintaan Anda sendiri.'], 422);
        }

        $approval->status = 'REJECTED';
        $approval->approver_id = Auth::id();
        $approval->review_note = $request->review_note;
        $approval->reviewed_at = Carbon::now();
        $approval->save();

        AuditService::log(
            'APPROVAL_REJECTED',
            'APPROVAL',
            "Menolak permintaan " . $approval->type,
            $approval
        );

        return response()->json([
            'message' => 'Permintaan berhasil ditolak'
        ]);
    }
}
