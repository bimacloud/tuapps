<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Builds the data printed on the student's "Bukti Pembayaran" card:
 * monthly administration (Juli to Juni) and the fixed list of semester/annual items.
 */
class PaymentCardService
{
    /**
     * School months in card order.
     *
     * @var list<string>
     */
    public const MONTHS = [
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    ];

    /**
     * Fixed rows of the semester/annual card. `rows` is the number of instalment lines printed.
     *
     * @return list<array{key: string, label: string, rows: int}>
     */
    public static function annualSlots(): array
    {
        return [
            ['key' => 'daftar_ulang', 'label' => 'Daftar Ulang', 'rows' => 4],
            ['key' => 'lks_1', 'label' => 'LKS/Buku Semester 1', 'rows' => 2],
            ['key' => 'lks_2', 'label' => 'LKS/Buku Semester 2', 'rows' => 2],
            ['key' => 'asts_1', 'label' => 'ASTS 1', 'rows' => 1],
            ['key' => 'asas_1', 'label' => 'ASAS 1', 'rows' => 1],
            ['key' => 'asts_2', 'label' => 'ASTS 2', 'rows' => 1],
            ['key' => 'asat', 'label' => 'ASAT', 'rows' => 1],
            ['key' => 'pkl', 'label' => 'PKL', 'rows' => 1],
            ['key' => 'asesmen_akhir', 'label' => 'Asesmen Akhir', 'rows' => 2],
            ['key' => 'lainnya', 'label' => '', 'rows' => 1],
        ];
    }

    /**
     * @return array{
     *     student: array{id: int, name: string, nis: ?string, class_name: ?string},
     *     academic_year: ?array{id: int, name: string},
     *     academic_years: list<array{id: int, name: string}>,
     *     monthly: list<array{no: int, month: string, billed: float, paid: float, date: ?string, status: ?string}>,
     *     annual: list<array{no: int, key: string, label: string, rows: list<array{date: string, amount: float}>, min_rows: int, items: list<string>}>
     * }
     */
    public function build(Student $student, ?int $academicYearId = null): array
    {
        $student->loadMissing('classRoom');

        $yearsWithInvoices = AcademicYear::query()
            ->whereIn('id', Invoice::where('student_id', $student->id)->select('academic_year_id'))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get(['id', 'name', 'is_active']);

        $academicYear = $this->resolveAcademicYear($yearsWithInvoices, $academicYearId);

        $items = $academicYear === null
            ? collect()
            : InvoiceItem::query()
                ->with('paymentType')
                ->whereHas('invoice', fn ($query) => $query
                    ->where('student_id', $student->id)
                    ->where('academic_year_id', $academicYear->id))
                ->where('status', '!=', 'CANCELLED')
                ->get();

        $paymentsByItem = $this->successfulPaymentsByItem($items->pluck('id'));

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'class_name' => $student->classRoom?->name,
            ],
            'academic_year' => $academicYear ? ['id' => $academicYear->id, 'name' => $academicYear->name] : null,
            'academic_years' => $yearsWithInvoices
                ->map(fn (AcademicYear $year): array => ['id' => $year->id, 'name' => $year->name])
                ->values()
                ->all(),
            'monthly' => $this->monthlyRows($items->filter(fn (InvoiceItem $item): bool => $item->paymentType?->type === 'BULANAN'), $paymentsByItem),
            'annual' => $this->annualRows($items->filter(fn (InvoiceItem $item): bool => $item->paymentType?->type !== 'BULANAN'), $paymentsByItem),
        ];
    }

    /**
     * Identity data for printing blank cards for a whole class: one card per active student.
     *
     * @return array{
     *     academic_year: ?array{id: int, name: string},
     *     class: array{id: int, name: string},
     *     months: list<string>,
     *     annual_slots: list<array{key: string, label: string, rows: int}>,
     *     students: list<array{id: int, name: string, nis: ?string, class_name: string}>
     * }
     */
    public function blankCardsForClass(ClassRoom $classRoom, ?int $academicYearId = null): array
    {
        $academicYear = $academicYearId !== null
            ? AcademicYear::find($academicYearId)
            : ($classRoom->academicYear ?? AcademicYear::where('is_active', true)->first());

        return [
            'academic_year' => $academicYear ? ['id' => $academicYear->id, 'name' => $academicYear->name] : null,
            'class' => ['id' => $classRoom->id, 'name' => $classRoom->name],
            'months' => self::MONTHS,
            'annual_slots' => self::annualSlots(),
            'students' => Student::query()
                ->where('class_id', $classRoom->id)
                ->where('status', 'AKTIF')
                ->orderBy('name')
                ->get(['id', 'name', 'nis'])
                ->map(fn (Student $student): array => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'nis' => $student->nis,
                    'class_name' => $classRoom->name,
                ])
                ->all(),
        ];
    }

    /**
     * What the treasurer copies onto the physical card for one payment, plus what is left of each bill after it.
     *
     * @return list<array{payment_item_id: int, side: string, no: int, row: string, date: string, amount: float, billed: float, remaining_after: float, write_now: bool, card_amount: float}>
     */
    public function linesForPayment(Payment $payment): array
    {
        $payment->loadMissing('items.invoiceItem.paymentType');

        $slots = collect(self::annualSlots())->values();

        return $payment->items
            ->map(function (PaymentItem $paymentItem) use ($payment, $slots): array {
                $invoiceItem = $paymentItem->invoiceItem;
                $isMonthly = $invoiceItem->paymentType?->type === 'BULANAN';

                if ($isMonthly) {
                    $monthIndex = collect(self::MONTHS)->search(fn (string $month): bool => $this->periodIsMonth($invoiceItem->period, $month));
                    $no = $monthIndex === false ? 0 : $monthIndex + 1;
                    $row = $monthIndex === false ? (string) $invoiceItem->period : self::MONTHS[$monthIndex];
                } else {
                    $key = $this->slotKeyFor($invoiceItem);
                    $no = $slots->search(fn (array $slot): bool => $slot['key'] === $key) + 1;
                    $label = $slots->firstWhere('key', $key)['label'];
                    $row = $label !== '' ? $label : trim($invoiceItem->paymentType?->name.' '.$invoiceItem->period);
                }

                $paidUpToThisPayment = PaymentItem::query()
                    ->where('invoice_item_id', $invoiceItem->id)
                    ->where('payment_id', '<=', $payment->id)
                    ->whereHas('payment', fn ($query) => $query->where('status', 'SUCCESS'))
                    ->sum('amount');

                $remainingAfter = max((float) $invoiceItem->amount - (float) $paidUpToThisPayment, 0.0);

                return [
                    'payment_item_id' => $paymentItem->id,
                    'side' => $isMonthly ? 'bulanan' : 'semester',
                    'no' => $no,
                    'row' => $row,
                    'date' => $payment->payment_date->toDateString(),
                    'amount' => (float) $paymentItem->amount,
                    'billed' => (float) $invoiceItem->amount,
                    'remaining_after' => $remainingAfter,
                    // A month has one card row: it is written once, when paid off, with the month's total.
                    // Semester/annual items get one card line per instalment.
                    'write_now' => ! $isMonthly || $remainingAfter <= 0,
                    'card_amount' => $isMonthly ? (float) $paidUpToThisPayment : (float) $paymentItem->amount,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Requested year if the student has invoices there, otherwise the active year, otherwise the latest one.
     *
     * @param  Collection<int, AcademicYear>  $yearsWithInvoices
     */
    private function resolveAcademicYear(Collection $yearsWithInvoices, ?int $academicYearId): ?AcademicYear
    {
        if ($academicYearId !== null) {
            $requested = $yearsWithInvoices->firstWhere('id', $academicYearId);

            return $requested ?? AcademicYear::find($academicYearId);
        }

        return $yearsWithInvoices->firstWhere('is_active', true)
            ?? $yearsWithInvoices->first()
            ?? AcademicYear::where('is_active', true)->first();
    }

    /**
     * @param  Collection<int, int>  $itemIds
     * @return Collection<int, Collection<int, array{date: string, amount: float}>>
     */
    private function successfulPaymentsByItem(Collection $itemIds): Collection
    {
        if ($itemIds->isEmpty()) {
            return collect();
        }

        return PaymentItem::query()
            ->with('payment:id,payment_date,status')
            ->whereIn('invoice_item_id', $itemIds)
            ->whereHas('payment', fn ($query) => $query->where('status', 'SUCCESS'))
            ->get()
            ->sortBy(fn (PaymentItem $paymentItem) => $paymentItem->payment->payment_date)
            ->groupBy('invoice_item_id')
            ->map(fn (Collection $group): Collection => $group->map(fn (PaymentItem $paymentItem): array => [
                'date' => $paymentItem->payment->payment_date->toDateString(),
                'amount' => (float) $paymentItem->amount,
            ])->values());
    }

    /**
     * @param  Collection<int, InvoiceItem>  $items
     * @param  Collection<int, Collection<int, array{date: string, amount: float}>>  $paymentsByItem
     * @return list<array{no: int, month: string, billed: float, paid: float, date: ?string, status: ?string}>
     */
    private function monthlyRows(Collection $items, Collection $paymentsByItem): array
    {
        $rows = [];

        foreach (self::MONTHS as $index => $month) {
            $monthItems = $items->filter(fn (InvoiceItem $item): bool => $this->periodIsMonth($item->period, $month));
            $payments = $monthItems->flatMap(fn (InvoiceItem $item) => $paymentsByItem->get($item->id, collect()));

            $rows[] = [
                'no' => $index + 1,
                'month' => $month,
                'billed' => (float) $monthItems->sum('amount'),
                'paid' => (float) $payments->sum('amount'),
                'date' => $payments->max('date'),
                'status' => $this->combinedStatus($monthItems),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, InvoiceItem>  $items
     * @param  Collection<int, Collection<int, array{date: string, amount: float}>>  $paymentsByItem
     * @return list<array{no: int, key: string, label: string, rows: list<array{date: string, amount: float}>, min_rows: int, items: list<string>}>
     */
    private function annualRows(Collection $items, Collection $paymentsByItem): array
    {
        $grouped = $items->groupBy(fn (InvoiceItem $item): string => $this->slotKeyFor($item));

        return collect(self::annualSlots())
            ->values()
            ->map(function (array $slot, int $index) use ($grouped, $paymentsByItem): array {
                $slotItems = $grouped->get($slot['key'], collect());

                return [
                    'no' => $index + 1,
                    'key' => $slot['key'],
                    'label' => $slot['label'],
                    'rows' => $slotItems
                        ->flatMap(fn (InvoiceItem $item) => $paymentsByItem->get($item->id, collect()))
                        ->sortBy('date')
                        ->values()
                        ->all(),
                    'min_rows' => $slot['rows'],
                    'items' => $slotItems
                        ->map(fn (InvoiceItem $item): string => trim($item->paymentType?->name.' '.$item->period))
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * Maps an invoice item to a card row using the payment type name and period, e.g. "LKS" + "Semester 2".
     */
    public function slotKeyFor(InvoiceItem $item): string
    {
        $text = mb_strtolower(trim(($item->paymentType?->name ?? '').' '.($item->period ?? '')));
        $semester = $this->semesterOf($text);

        return match (true) {
            str_contains($text, 'daftar ulang') => 'daftar_ulang',
            (bool) preg_match('/\b(lks|buku)\b/', $text) => $semester === 2 ? 'lks_2' : 'lks_1',
            (bool) preg_match('/\basts\b/', $text) => $semester === 2 ? 'asts_2' : 'asts_1',
            (bool) preg_match('/\basas\b/', $text) => 'asas_1',
            (bool) preg_match('/\basat\b/', $text) => 'asat',
            (bool) preg_match('/\bpkl\b/', $text) => 'pkl',
            str_contains($text, 'asesmen akhir') => 'asesmen_akhir',
            default => 'lainnya',
        };
    }

    private function semesterOf(string $text): ?int
    {
        if (preg_match('/\b(?:semester|smt|sem|asts)\s*([12])\b/', $text, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\b(genap)\b/', $text)) {
            return 2;
        }

        if (preg_match('/\b(ganjil|gasal)\b/', $text)) {
            return 1;
        }

        return null;
    }

    private function periodIsMonth(?string $period, string $month): bool
    {
        $firstWord = strtok(mb_strtolower(trim((string) $period)), ' ');

        return $firstWord === mb_strtolower($month);
    }

    /**
     * @param  Collection<int, InvoiceItem>  $items
     */
    private function combinedStatus(Collection $items): ?string
    {
        if ($items->isEmpty()) {
            return null;
        }

        $statuses = $items->pluck('status')->unique();

        if ($statuses->count() === 1) {
            return $statuses->first();
        }

        return 'SEBAGIAN';
    }
}
