<?php

namespace App\Services\SetupData;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * One kind of initial-setup data (majors, classes, students, ...) that can be
 * downloaded as an Excel template, exported, previewed and imported.
 *
 * Column keys are the slug of the column label (as produced by the heading row formatter),
 * so "Nama Lengkap" in the file is read back as "nama_lengkap".
 */
abstract class SetupDataset
{
    /** URL key, e.g. "majors". */
    abstract public function key(): string;

    /** Human title, e.g. "Jurusan". */
    abstract public function title(): string;

    /** Role allowed to download, export and import this dataset. */
    abstract public function role(): string;

    /**
     * Template columns, keyed by column key.
     *
     * @return array<string, array{label: string, required: bool, format: string}>
     */
    abstract public function columns(): array;

    /**
     * Example rows written into the template.
     *
     * @return list<array<string, string|int|null>>
     */
    abstract public function exampleRows(): array;

    /**
     * Current data in the template format, so an export can be edited and imported again.
     *
     * @return list<array<string, string|int|float|null>>
     */
    abstract public function exportRows(): array;

    /**
     * Load lookups (existing records, related names) once before rows are inspected.
     */
    abstract public function prepare(): void;

    /**
     * Validate one row and resolve what it would change.
     *
     * @param  array<string, mixed>  $row
     * @return array{key: string|null, label: string, attributes: array<string, mixed>, existing: Model|null, errors: list<string>}
     */
    abstract public function inspectRow(array $row): array;

    /**
     * Persist one valid row.
     *
     * @param  array<string, mixed>  $attributes
     */
    abstract public function save(array $attributes, ?Model $existing): void;

    /**
     * Lists of valid values written to the guide sheet, e.g. existing major codes.
     *
     * @return array<string, list<string>>
     */
    public function references(): array
    {
        return [];
    }

    /**
     * Columns that must stay text in Excel so leading zeros (NISN, NIS) are kept.
     *
     * @return list<string>
     */
    public function textColumns(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_values(array_map(fn (array $column): string => $column['label'], $this->columns()));
    }

    /**
     * Order a keyed row by the template columns.
     *
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    public function toSheetRow(array $row): array
    {
        return array_map(fn (string $key): mixed => $row[$key] ?? null, array_keys($this->columns()));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    protected function missingRequired(array $row): array
    {
        $errors = [];

        foreach ($this->columns() as $key => $column) {
            if ($column['required'] && $this->text($row, $key) === null) {
                $errors[] = "{$column['label']} wajib diisi";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function text(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_float($value) && floor($value) === $value) {
            $value = number_format($value, 0, '', '');
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Date as Y-m-d. Accepts Excel date cells, 2026-07-13, 13/07/2026 and 13-07-2026.
     *
     * @param  array<string, mixed>  $row
     * @return string|false|null null when empty, false when the value is not a date
     */
    protected function date(array $row, string $key): string|false|null
    {
        $value = $row[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('Y-m-d');
            } catch (Throwable) {
                return false;
            }
        }

        $value = trim((string) $value);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date !== null && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return false;
    }

    /**
     * Yes/no column. Accepts Ya/Tidak, Aktif/Nonaktif, Y/T, 1/0.
     *
     * @param  array<string, mixed>  $row
     * @return bool|null null when the value is not recognised
     */
    protected function boolean(array $row, string $key, bool $default): ?bool
    {
        $value = $this->text($row, $key);

        if ($value === null) {
            return $default;
        }

        return match (strtolower($value)) {
            'ya', 'y', 'aktif', '1', 'true' => true,
            'tidak', 't', 'nonaktif', 'non aktif', '0', 'false' => false,
            default => null,
        };
    }

    /**
     * Whole rupiah. Accepts 150000, "150.000" and "Rp 150.000".
     *
     * @param  array<string, mixed>  $row
     * @return int|false|null null when empty, false when the value is not an amount
     */
    protected function amount(array $row, string $key): int|false|null
    {
        $value = $row[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (int) round($value);
        }

        $digits = preg_replace('/^rp\.?\s*/i', '', trim((string) $value));
        $digits = str_replace('.', '', $digits);

        return ctype_digit($digits) ? (int) $digits : false;
    }

    protected function yesNo(bool $value): string
    {
        return $value ? 'Ya' : 'Tidak';
    }
}
