<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CashierSession;
use App\Models\Payment;
use App\Services\AuditService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class CashierSessionController extends Controller
{
    /**
     * Get the current active session for the authenticated user.
     */
    public function current(Request $request)
    {
        $session = CashierSession::where('user_id', auth()->id())
            ->where('status', 'OPEN')
            ->first();

        return response()->json([
            'data' => $session
        ]);
    }

    /**
     * Open a new cashier session.
     */
    public function open(Request $request)
    {
        $request->validate([
            'starting_balance' => 'required|numeric|min:0'
        ]);

        // Check if already open
        $activeSession = CashierSession::where('user_id', auth()->id())
            ->where('status', 'OPEN')
            ->first();

        if ($activeSession) {
            return response()->json([
                'message' => 'Anda masih memiliki sesi kasir yang aktif.'
            ], 422);
        }

        $session = CashierSession::create([
            'user_id' => auth()->id(),
            'start_time' => Carbon::now(),
            'starting_balance' => $request->starting_balance,
            'status' => 'OPEN'
        ]);

        AuditService::log(
            'CASHIER_OPEN',
            'CASHIER_SESSION',
            'Membuka sesi kasir dengan saldo awal ' . number_format($request->starting_balance, 0, ',', '.'),
            $session,
            null,
            $session->toArray()
        );

        return response()->json([
            'message' => 'Sesi kasir berhasil dibuka',
            'data' => $session
        ], 201);
    }

    /**
     * Get summary before closing.
     */
    public function summary(Request $request)
    {
        $session = CashierSession::where('user_id', auth()->id())
            ->where('status', 'OPEN')
            ->firstOrFail();

        $cashPayments = $this->cashPaymentsTotal($session);

        $expectedCash = $session->starting_balance + $cashPayments;

        return response()->json([
            'data' => [
                'session' => $session,
                'total_cash_payments' => $cashPayments,
                'expected_balance' => $expectedCash
            ]
        ]);
    }

    /**
     * Close cashier session.
     */
    public function close(Request $request)
    {
        $request->validate([
            'actual_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        $session = CashierSession::where('user_id', auth()->id())
            ->where('status', 'OPEN')
            ->firstOrFail();

        $cashPayments = $this->cashPaymentsTotal($session);

        $expectedCash = $session->starting_balance + $cashPayments;
        $actualCash = $request->actual_balance;
        $difference = $actualCash - $expectedCash;

        if ($difference != 0 && empty($request->notes)) {
            return response()->json([
                'message' => 'Terdapat selisih kas. Anda wajib mengisi catatan.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldData = $session->toArray();
            
            $session->update([
                'end_time' => Carbon::now(),
                'expected_balance' => $expectedCash,
                'actual_balance' => $actualCash,
                'difference' => $difference,
                'notes' => $request->notes,
                'status' => 'CLOSED'
            ]);

            AuditService::log(
                'CASHIER_CLOSE',
                'CASHIER_SESSION',
                'Menutup sesi kasir. Selisih: ' . number_format($difference, 0, ',', '.'),
                $session,
                $oldData,
                $session->fresh()->toArray()
            );

            DB::commit();

            return response()->json([
                'message' => 'Sesi kasir berhasil ditutup',
                'data' => $session
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menutup sesi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sum successful cash (payment method code CASH) payments in the given session.
     */
    private function cashPaymentsTotal(CashierSession $session): float
    {
        return (float) Payment::where('cashier_session_id', $session->id)
            ->where('status', 'SUCCESS')
            ->whereHas('paymentMethod', fn ($q) => $q->where('code', 'CASH'))
            ->sum('total_amount');
    }
}
