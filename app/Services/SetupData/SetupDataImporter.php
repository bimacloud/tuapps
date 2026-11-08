<?php

namespace App\Services\SetupData;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reads a setup file, checks every row, and saves the valid ones.
 * The file is checked again on import, so the client never decides which rows are valid.
 */
class SetupDataImporter
{
    public const MAX_ROWS = 5000;

    /**
     * Datasets in the order they must be filled during initial setup.
     *
     * @var list<class-string<SetupDataset>>
     */
    private const DATASETS = [
        AcademicYearDataset::class,
        MajorDataset::class,
        ClassRoomDataset::class,
        StudentDataset::class,
        PaymentTypeDataset::class,
        PaymentRateDataset::class,
    ];

    /**
     * @return list<SetupDataset>
     */
    public function datasets(): array
    {
        return array_map(fn (string $class): SetupDataset => app($class), self::DATASETS);
    }

    public function find(string $key): ?SetupDataset
    {
        foreach ($this->datasets() as $dataset) {
            if ($dataset->key() === $key) {
                return $dataset;
            }
        }

        return null;
    }

    /**
     * @return array{summary: array{total: int, create: int, update: int, invalid: int}, rows: list<array{row: int, label: string, action: string|null, errors: list<string>, is_valid: bool}>}
     */
    public function preview(SetupDataset $dataset, UploadedFile $file): array
    {
        $inspected = $this->inspect($dataset, $file);

        return [
            'summary' => $this->summarize($inspected),
            'rows' => array_map(fn (array $row): array => [
                'row' => $row['row'],
                'label' => $row['label'],
                'action' => $row['errors'] ? null : ($row['existing'] ? 'update' : 'create'),
                'errors' => $row['errors'],
                'is_valid' => ! $row['errors'],
            ], $inspected),
        ];
    }

    /**
     * Save every valid row in one transaction. Invalid rows are skipped.
     *
     * @return array{total: int, create: int, update: int, invalid: int}
     */
    public function import(SetupDataset $dataset, UploadedFile $file): array
    {
        $inspected = $this->inspect($dataset, $file);

        DB::transaction(function () use ($dataset, $inspected): void {
            foreach ($inspected as $row) {
                if (! $row['errors']) {
                    $dataset->save($row['attributes'], $row['existing']);
                }
            }
        });

        return $this->summarize($inspected);
    }

    /**
     * @return list<array{row: int, label: string, attributes: array<string, mixed>, existing: mixed, errors: list<string>}>
     */
    private function inspect(SetupDataset $dataset, UploadedFile $file): array
    {
        $rows = $this->readRows($dataset, $file);
        $dataset->prepare();

        $firstRowByKey = [];
        $inspected = [];

        foreach ($rows as $rowNumber => $row) {
            $result = $dataset->inspectRow($row);
            $errors = $result['errors'];

            if ($result['key'] !== null) {
                if (isset($firstRowByKey[$result['key']])) {
                    $errors[] = "Sama dengan baris {$firstRowByKey[$result['key']]} di file";
                } else {
                    $firstRowByKey[$result['key']] = $rowNumber;
                }
            }

            $inspected[] = [
                'row' => $rowNumber,
                'label' => $result['label'],
                'attributes' => $result['attributes'],
                'existing' => $result['existing'],
                'errors' => array_values(array_unique($errors)),
            ];
        }

        return $inspected;
    }

    /**
     * Rows of the first sheet keyed by their Excel row number, without empty rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readRows(SetupDataset $dataset, UploadedFile $file): array
    {
        $sheets = Excel::toArray(new class implements ToArray, WithHeadingRow
        {
            public function array(array $array): void {}
        }, $file);

        $sheet = $sheets[0] ?? [];
        $rows = [];
        foreach ($sheet as $index => $row) {
            $hasValue = collect($row)->contains(fn (mixed $value): bool => $value !== null && trim((string) $value) !== '');
            if ($hasValue) {
                $rows[$index + 2] = $row;
            }
        }

        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi data di bawah baris judul.']);
        }

        $headings = array_keys($sheet[0]);
        $requiredColumns = array_keys(array_filter($dataset->columns(), fn (array $column): bool => $column['required']));
        $missingColumns = array_diff($requiredColumns, $headings);

        if ($missingColumns) {
            $labels = array_map(fn (string $key): string => $dataset->columns()[$key]['label'], $missingColumns);

            throw ValidationException::withMessages([
                'file' => 'Kolom '.implode(', ', $labels).' tidak ada di baris pertama. Pakai template '.$dataset->title().' dari aplikasi.',
            ]);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Paling banyak '.self::MAX_ROWS.' baris per file. Bagi file menjadi beberapa bagian.']);
        }

        return $rows;
    }

    /**
     * @param  list<array{existing: mixed, errors: list<string>}>  $inspected
     * @return array{total: int, create: int, update: int, invalid: int}
     */
    private function summarize(array $inspected): array
    {
        $summary = ['total' => count($inspected), 'create' => 0, 'update' => 0, 'invalid' => 0];

        foreach ($inspected as $row) {
            $action = $row['errors'] ? 'invalid' : ($row['existing'] ? 'update' : 'create');
            $summary[$action]++;
        }

        return $summary;
    }
}
