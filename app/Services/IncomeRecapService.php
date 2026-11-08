<?php

namespace App\Services;

use App\Models\CashierSession;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Income over any date range: per day, payment type, class, method and cashier,
 * plus a check of the cash counted when each session was closed.
 */
class IncomeRecapService
{
    /**
     * @return array{
     *     period: array{from: string, to: string},
     *     summary: array{total: float, transactions: int, students: int, cash: float, non_cash: float},
     *     per_day: list<array{date: string, transactions: int, total: float, cash: float, non_cash: float}>,
     *     per_type: list<array{name: string, type: ?string, transactions: int, total: float}>,
     *     per_class: list<array{name: string, transactions: int, total: float}>,
     *     per_method: list<array{name: string, code: ?string, transactions: int, total: float}>,
     *     per_cashier: list<array{name: string, transactions: int, total: float}>,
     *     cash_check: array{sessions: int, open_sessions: int, expected: float, actual: float, difference: float, mismatches: list<array{opened_at: ?string, closed_at: ?string, cashier: ?string, expected: float, actual: float, difference: float, notes: ?string}>}
     * }
     */
    public function build(string $from, string $to): array
    {
        $cashCase = "CASE WHEN payment_methods.code = 'CASH' THEN payments.total_amount ELSE 0 END";

        $summary = $this->payments($from, $to)
            ->selectRaw('COUNT(payments.id) as transactions, COUNT(DISTINCT payments.student_id) as students, COALESCE(SUM(payments.total_amount), 0) as total')
            ->selectRaw("COALESCE(SUM({$cashCase}), 0) as cash")
            ->first();

        $perDay = $this->payments($from, $to)
            ->selectRaw('DATE(payments.payment_date) as date, COUNT(payments.id) as transactions, SUM(payments.total_amount) as total')
            ->selectRaw("SUM({$cashCase}) as cash")
            ->groupByRaw('DATE(payments.payment_date)')
            ->orderBy('date')
            ->get()
            ->map(fn (object $row): array => [
                'date' => (string) $row->date,
                'transactions' => (int) $row->transactions,
                'total' => (float) $row->total,
                'cash' => (float) $row->cash,
                'non_cash' => (float) $row->total - (float) $row->cash,
            ])
            ->all();

        $perType = $this->paymentItems($from, $to)
            ->join('payment_types', 'payment_types.id', '=', 'invoice_items.payment_type_id')
            ->selectRaw('payment_types.name, payment_types.type, COUNT(DISTINCT payments.id) as transactions, SUM(payment_items.amount) as total')
            ->groupBy('payment_types.id', 'payment_types.name', 'payment_types.type')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => [
                'name' => $row->name,
                'type' => $row->type,
                'transactions' => (int) $row->transactions,
                'total' => (float) $row->total,
            ])
            ->all();

        $perClass = $this->paymentItems($from, $to)
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('classes', 'classes.id', '=', 'invoices.class_id')
            ->selectRaw("COALESCE(classes.name, 'Tanpa kelas') as name, COUNT(DISTINCT payments.id) as transactions, SUM(payment_items.amount) as total")
            ->groupBy('classes.id', 'classes.name')
            ->orderBy('name')
            ->get()
            ->map(fn (object $row): array => [
                'name' => $row->name,
                'transactions' => (int) $row->transactions,
                'total' => (float) $row->total,
            ])
            ->all();

        $perMethod = $this->payments($from, $to)
            ->selectRaw('payment_methods.name, payment_methods.code, COUNT(payments.id) as transactions, SUM(payments.total_amount) as total')
            ->groupBy('payment_methods.id', 'payment_methods.name', 'payment_methods.code')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => [
                'name' => $row->name ?? 'Tanpa metode',
                'code' => $row->code,
                'transactions' => (int) $row->transactions,
                'total' => (float) $row->total,
            ])
            ->all();

        $perCashier = $this->payments($from, $to)
            ->leftJoin('users', 'users.id', '=', 'payments.cashier_id')
            ->selectRaw("COALESCE(users.name, 'Pembayaran online') as name, COUNT(payments.id) as transactions, SUM(payments.total_amount) as total")
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => [
                'name' => $row->name,
                'transactions' => (int) $row->transactions,
                'total' => (float) $row->total,
            ])
            ->all();

        $total = (float) $summary->total;
        $cash = (float) $summary->cash;

        return [
            'period' => ['from' => $from, 'to' => $to],
            'summary' => [
                'total' => $total,
                'transactions' => (int) $summary->transactions,
                'students' => (int) $summary->students,
                'cash' => $cash,
                'non_cash' => $total - $cash,
            ],
            'per_day' => $perDay,
            'per_type' => $perType,
            'per_class' => $perClass,
            'per_method' => $perMethod,
            'per_cashier' => $perCashier,
            'cash_check' => $this->cashCheck($from, $to),
        ];
    }

    /**
     * Successful payments in the range, joined to their method.
     */
    private function payments(string $from, string $to): Builder
    {
        return DB::table('payments')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->where('payments.status', 'SUCCESS')
            ->whereDate('payments.payment_date', '>=', $from)
            ->whereDate('payments.payment_date', '<=', $to);
    }

    /**
     * Amounts paid per invoice item, for splitting income by payment type or class.
     */
    private function paymentItems(string $from, string $to): Builder
    {
        return DB::table('payment_items')
            ->join('payments', 'payments.id', '=', 'payment_items.payment_id')
            ->join('invoice_items', 'invoice_items.id', '=', 'payment_items.invoice_item_id')
            ->where('payments.status', 'SUCCESS')
            ->whereDate('payments.payment_date', '>=', $from)
            ->whereDate('payments.payment_date', '<=', $to);
    }

    /**
     * @return array{sessions: int, open_sessions: int, expected: float, actual: float, difference: float, mismatches: list<array{opened_at: ?string, closed_at: ?string, cashier: ?string, expected: float, actual: float, difference: float, notes: ?string}>}
     */
    private function cashCheck(string $from, string $to): array
    {
        $sessions = CashierSession::query()
            ->with('user:id,name')
            ->whereDate('start_time', '>=', $from)
            ->whereDate('start_time', '<=', $to)
            ->orderBy('start_time')
            ->get();

        $closed = $sessions->where('status', 'CLOSED');

        return [
            'sessions' => $closed->count(),
            'open_sessions' => $sessions->where('status', 'OPEN')->count(),
            'expected' => (float) $closed->sum('expected_balance'),
            'actual' => (float) $closed->sum('actual_balance'),
            'difference' => (float) $closed->sum('difference'),
            'mismatches' => $closed
                ->filter(fn (CashierSession $session): bool => (float) $session->difference !== 0.0)
                ->map(fn (CashierSession $session): array => [
                    'opened_at' => $session->start_time?->toIso8601String(),
                    'closed_at' => $session->end_time?->toIso8601String(),
                    'cashier' => $session->user?->name,
                    'expected' => (float) $session->expected_balance,
                    'actual' => (float) $session->actual_balance,
                    'difference' => (float) $session->difference,
                    'notes' => $session->notes,
                ])
                ->values()
                ->all(),
        ];
    }
}
