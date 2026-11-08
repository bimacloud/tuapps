<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\InvoiceItem;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Class recap: one row per student, one column per month (Juli to Juni) and per semester/annual
 * card slot, like the payment card but for a whole class at once.
 */
class ClassRecapService
{
    public function __construct(private PaymentCardService $cards) {}

    /**
     * @return array{
     *     academic_year: array{id: int, name: string},
     *     class: array{id: int, name: string},
     *     columns: list<array{key: string, label: string, group: string}>,
     *     students: list<array{id: int, nis: ?string, name: string, status: string, cells: array<string, array{billed: float, paid: float, remaining: float, status: string}>, total: array{billed: float, paid: float, remaining: float}}>,
     *     column_totals: array<string, array{billed: float, paid: float, remaining: float, unpaid: int}>,
     *     total: array{billed: float, paid: float, remaining: float, students: int, students_with_arrears: int}
     * }
     */
    public function build(AcademicYear $year, ClassRoom $class): array
    {
        $items = InvoiceItem::query()
            ->with(['paymentType', 'invoice:id,student_id'])
            ->whereHas('invoice', fn ($query) => $query
                ->where('academic_year_id', $year->id)
                ->where('class_id', $class->id))
            ->where('status', '!=', 'CANCELLED')
            ->get();

        $columns = $this->columns($items);
        $itemsByStudent = $items->groupBy(fn (InvoiceItem $item): int => $item->invoice->student_id);

        $studentIds = $itemsByStudent->keys();
        if ($class->academic_year_id === $year->id || $class->academic_year_id === null) {
            $studentIds = $studentIds->merge($class->students()->where('status', 'AKTIF')->pluck('id'));
        }

        $students = Student::query()->whereIn('id', $studentIds->unique())->orderBy('name')->get();

        $columnTotals = [];
        foreach ($columns as $column) {
            $columnTotals[$column['key']] = ['billed' => 0.0, 'paid' => 0.0, 'remaining' => 0.0, 'unpaid' => 0];
        }

        $rows = [];
        foreach ($students as $student) {
            $cells = [];
            $total = ['billed' => 0.0, 'paid' => 0.0, 'remaining' => 0.0];

            foreach ($itemsByStudent->get($student->id, collect())->groupBy(fn (InvoiceItem $item): string => $this->columnKey($item)) as $key => $cellItems) {
                $cell = $this->cell($cellItems);
                $cells[$key] = $cell;

                foreach (['billed', 'paid', 'remaining'] as $field) {
                    $total[$field] += $cell[$field];
                    $columnTotals[$key][$field] += $cell[$field];
                }
                if ($cell['remaining'] > 0) {
                    $columnTotals[$key]['unpaid']++;
                }
            }

            $rows[] = [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'status' => $student->status,
                'cells' => $cells,
                'total' => $total,
            ];
        }

        $rowTotals = collect($rows)->pluck('total');

        return [
            'academic_year' => ['id' => $year->id, 'name' => $year->name],
            'class' => ['id' => $class->id, 'name' => $class->name],
            'columns' => $columns,
            'students' => $rows,
            'column_totals' => $columnTotals,
            'total' => [
                'billed' => (float) $rowTotals->sum('billed'),
                'paid' => (float) $rowTotals->sum('paid'),
                'remaining' => (float) $rowTotals->sum('remaining'),
                'students' => count($rows),
                'students_with_arrears' => $rowTotals->filter(fn (array $total): bool => $total['remaining'] > 0)->count(),
            ],
        ];
    }

    /**
     * All twelve months, then only the semester/annual slots this class was billed for.
     *
     * @param  Collection<int, InvoiceItem>  $items
     * @return list<array{key: string, label: string, group: string}>
     */
    private function columns(Collection $items): array
    {
        $columns = [];
        foreach (PaymentCardService::MONTHS as $index => $month) {
            $columns[] = ['key' => 'm'.($index + 1), 'label' => $month, 'group' => 'bulanan'];
        }

        $usedKeys = $items->map(fn (InvoiceItem $item): string => $this->columnKey($item))->unique();
        foreach (PaymentCardService::annualSlots() as $slot) {
            if ($usedKeys->contains($slot['key'])) {
                $columns[] = ['key' => $slot['key'], 'label' => $slot['label'] ?: 'Lainnya', 'group' => 'lainnya'];
            }
        }

        return $columns;
    }

    /**
     * Monthly items go to their month column (by the first word of the period); everything else
     * goes to the same slot it uses on the payment card.
     */
    private function columnKey(InvoiceItem $item): string
    {
        if ($item->paymentType?->type === 'BULANAN') {
            $firstWord = strtok(mb_strtolower(trim((string) $item->period)), ' ');
            foreach (PaymentCardService::MONTHS as $index => $month) {
                if ($firstWord === mb_strtolower($month)) {
                    return 'm'.($index + 1);
                }
            }
        }

        return $this->cards->slotKeyFor($item);
    }

    /**
     * @param  Collection<int, InvoiceItem>  $items
     * @return array{billed: float, paid: float, remaining: float, status: string}
     */
    private function cell(Collection $items): array
    {
        $billed = (float) $items->sum('amount');
        $paid = (float) $items->sum('paid_amount');
        $remaining = (float) $items->sum('remaining_amount');

        return [
            'billed' => $billed,
            'paid' => $paid,
            'remaining' => $remaining,
            'status' => match (true) {
                $remaining <= 0 => 'LUNAS',
                $paid > 0 => 'SEBAGIAN',
                default => 'BELUM BAYAR',
            },
        ];
    }
}
