<?php

namespace App\Exports;

use App\Services\SetupData\SetupDataset;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template or export for one setup dataset: the data sheet first (the only sheet that is imported),
 * then a "Petunjuk" sheet describing every column and listing the values that may be used.
 */
class SetupDataExport implements Export, WithMultipleSheets
{
    /**
     * @param  list<array<string, mixed>>  $rows  keyed by column key
     */
    public function __construct(private SetupDataset $dataset, private array $rows) {}

    public function sheets(): array
    {
        return [
            new SetupDataSheet($this->dataset, $this->rows),
            new class($this->dataset) implements FromArray, ShouldAutoSize, WithStyles, WithTitle
            {
                public function __construct(private SetupDataset $dataset) {}

                public function title(): string
                {
                    return 'Petunjuk';
                }

                public function array(): array
                {
                    $lines = [
                        ['Petunjuk impor '.$this->dataset->title()],
                        ['Isi data di sheet pertama mulai baris 2. Jangan ubah atau hapus baris judul.'],
                        ['Baris contoh boleh ditimpa atau dihapus. Sebelum disimpan, aplikasi menampilkan hasil pemeriksaan setiap baris.'],
                        [],
                        ['Kolom', 'Wajib', 'Cara mengisi'],
                    ];

                    foreach ($this->dataset->columns() as $column) {
                        $lines[] = [$column['label'], $column['required'] ? 'Wajib' : 'Boleh kosong', $column['format']];
                    }

                    foreach ($this->dataset->references() as $title => $values) {
                        $lines[] = [];
                        $lines[] = ["Daftar {$title} yang sudah ada di aplikasi"];
                        foreach ($values ?: ['(belum ada, isi dulu di menunya)'] as $value) {
                            $lines[] = [$value];
                        }
                    }

                    return $lines;
                }

                public function styles(Worksheet $sheet): array
                {
                    return [1 => ['font' => ['bold' => true, 'size' => 14]], 5 => ['font' => ['bold' => true]]];
                }
            },
        ];
    }
}
