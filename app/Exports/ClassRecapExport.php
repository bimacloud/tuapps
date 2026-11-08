<?php

namespace App\Exports;

use App\Models\Setting;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Class recap workbook: one sheet with what is still owed per cell, one with what has been paid.
 * Cells are numbers so totals can be checked in Excel; an empty cell means nothing was billed.
 */
class ClassRecapExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $recap  output of ClassRecapService::build()
     */
    public function __construct(private array $recap) {}

    public function sheets(): array
    {
        return [
            $this->sheet('Sisa tunggakan', 'remaining'),
            $this->sheet('Sudah dibayar', 'paid'),
        ];
    }

    private function sheet(string $title, string $field): ReportSheet
    {
        $columns = $this->recap['columns'];
        $rows = [
            [Setting::query()->where('key', 'school_name')->value('value') ?? ''],
            ["Rekap {$title} kelas {$this->recap['class']['name']}, tahun pelajaran {$this->recap['academic_year']['name']}"],
            [],
            array_merge(['No', 'NIS', 'Nama siswa'], array_column($columns, 'label'), ['Total']),
        ];

        foreach ($this->recap['students'] as $index => $student) {
            $row = [$index + 1, (string) $student['nis'], $student['name']];
            foreach ($columns as $column) {
                $row[] = isset($student['cells'][$column['key']]) ? $student['cells'][$column['key']][$field] : null;
            }
            $row[] = $student['total'][$field];
            $rows[] = $row;
        }

        $totals = [null, null, 'Jumlah'];
        foreach ($columns as $column) {
            $totals[] = $this->recap['column_totals'][$column['key']][$field];
        }
        $totals[] = $this->recap['total'][$field];
        $rows[] = $totals;

        $moneyColumns = [];
        for ($index = 4; $index <= count($columns) + 4; $index++) {
            $moneyColumns[] = Coordinate::stringFromColumnIndex($index);
        }

        return new ReportSheet($title, $rows, [4, count($rows)], $moneyColumns);
    }
}
