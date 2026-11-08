<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One sheet of a report workbook: rows are written as given, the listed rows are bold,
 * and the listed columns use a thousands format (150,000) for rupiah.
 */
class ReportSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
{
    /**
     * @param  list<list<mixed>>  $rows
     * @param  list<int>  $boldRows  1-based row numbers
     * @param  list<string>  $moneyColumns  column letters, e.g. ['C', 'D']
     */
    public function __construct(
        private string $title,
        private array $rows,
        private array $boldRows = [],
        private array $moneyColumns = [],
    ) {}

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function columnFormats(): array
    {
        return array_fill_keys($this->moneyColumns, '#,##0');
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [1 => ['font' => ['bold' => true, 'size' => 13]]];
        foreach ($this->boldRows as $row) {
            $styles[$row] = ['font' => ['bold' => true]];
        }

        return $styles;
    }
}
