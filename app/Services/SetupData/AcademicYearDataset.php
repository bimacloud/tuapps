<?php

namespace App\Services\SetupData;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class AcademicYearDataset extends SetupDataset
{
    /** @var Collection<string, AcademicYear> */
    private Collection $existingByName;

    private int $activeRowCount = 0;

    public function key(): string
    {
        return 'academic-years';
    }

    public function title(): string
    {
        return 'Tahun Pelajaran';
    }

    public function role(): string
    {
        return 'Administrasi';
    }

    public function columns(): array
    {
        return [
            'nama' => ['label' => 'Nama', 'required' => true, 'format' => 'Contoh 2026/2027. Nama yang sudah ada akan diperbarui.'],
            'tanggal_mulai' => ['label' => 'Tanggal Mulai', 'required' => true, 'format' => 'Tanggal, contoh 2026-07-13 atau 13/07/2026.'],
            'tanggal_selesai' => ['label' => 'Tanggal Selesai', 'required' => true, 'format' => 'Tanggal, harus setelah tanggal mulai.'],
            'aktif' => ['label' => 'Aktif', 'required' => false, 'format' => 'Ya atau Tidak. Kosong = Tidak. Hanya satu baris boleh Ya.'],
        ];
    }

    public function exampleRows(): array
    {
        return [
            ['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-13', 'tanggal_selesai' => '2027-06-30', 'aktif' => 'Ya'],
        ];
    }

    public function exportRows(): array
    {
        return AcademicYear::query()->orderBy('name')->get()
            ->map(fn (AcademicYear $year): array => [
                'nama' => $year->name,
                'tanggal_mulai' => $year->start_date?->format('Y-m-d'),
                'tanggal_selesai' => $year->end_date?->format('Y-m-d'),
                'aktif' => $this->yesNo($year->is_active),
            ])
            ->all();
    }

    public function prepare(): void
    {
        $this->existingByName = AcademicYear::all()->keyBy(fn (AcademicYear $year): string => strtolower($year->name));
        $this->activeRowCount = 0;
    }

    public function inspectRow(array $row): array
    {
        $errors = $this->missingRequired($row);
        $name = $this->text($row, 'nama');
        $startDate = $this->date($row, 'tanggal_mulai');
        $endDate = $this->date($row, 'tanggal_selesai');
        $isActive = $this->boolean($row, 'aktif', false);

        if ($startDate === false) {
            $errors[] = 'Tanggal Mulai bukan tanggal yang benar';
        }
        if ($endDate === false) {
            $errors[] = 'Tanggal Selesai bukan tanggal yang benar';
        }
        if ($startDate && $endDate && $endDate <= $startDate) {
            $errors[] = 'Tanggal Selesai harus setelah Tanggal Mulai';
        }
        if ($isActive === null) {
            $errors[] = 'Aktif harus diisi Ya atau Tidak';
        }
        if ($isActive && ++$this->activeRowCount > 1) {
            $errors[] = 'Hanya satu tahun pelajaran yang boleh aktif';
        }

        return [
            'key' => $name ? strtolower($name) : null,
            'label' => (string) $name,
            'attributes' => [
                'name' => $name,
                'start_date' => $startDate ?: null,
                'end_date' => $endDate ?: null,
                'is_active' => (bool) $isActive,
            ],
            'existing' => $name ? $this->existingByName->get(strtolower($name)) : null,
            'errors' => $errors,
        ];
    }

    public function save(array $attributes, ?Model $existing): void
    {
        if ($attributes['is_active']) {
            AcademicYear::query()->update(['is_active' => false]);
        }

        $existing ? $existing->update($attributes) : AcademicYear::create($attributes);
    }
}
