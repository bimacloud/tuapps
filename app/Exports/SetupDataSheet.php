<?php

namespace App\Exports;

use App\Services\SetupData\SetupDataset;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The importable sheet of a setup template or export. Text columns are written as text so leading zeros survive.
 */
class SetupDataSheet extends DefaultValueBinder implements FromArray, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    /** @var list<string> */
    private array $textColumnLetters;

    /**
     * @param  list<array<string, mixed>>  $rows  keyed by column key
     */
    public function __construct(private SetupDataset $dataset, private array $rows)
    {
        $keys = array_keys($dataset->columns());
        $this->textColumnLetters = array_map(
            fn (string $key): string => Coordinate::stringFromColumnIndex(array_search($key, $keys, true) + 1),
            $dataset->textColumns(),
        );
    }

    public function title(): string
    {
        return $this->dataset->title();
    }

    public function headings(): array
    {
        return $this->dataset->headings();
    }

    public function array(): array
    {
        return array_map(fn (array $row): array => $this->dataset->toSheetRow($row), $this->rows);
    }

    public function columnFormats(): array
    {
        return array_fill_keys($this->textColumnLetters, NumberFormat::FORMAT_TEXT);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($value !== null && $cell->getRow() > 1 && in_array($cell->getColumn(), $this->textColumnLetters, true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
