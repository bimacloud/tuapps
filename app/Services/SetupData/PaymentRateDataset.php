<?php

namespace App\Services\SetupData;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Rates are only added by import. An existing rate for the same payment type, year and class
 * is never overwritten, because changing a rate that already billed students belongs in the Tarif menu.
 */
class PaymentRateDataset extends SetupDataset
{
    /** @var Collection<string, PaymentType> */
    private Collection $typesByCode;

    /** @var Collection<string, AcademicYear> */
    private Collection $yearsByName;

    /** @var Collection<string, ClassRoom> */
    private Collection $classesByNameAndYear;

    /** @var array<string, true> */
    private array $existingKeys = [];

    public function key(): string
    {
        return 'payment-rates';
    }

    public function title(): string
    {
        return 'Tarif Pembayaran';
    }

    public function role(): string
    {
        return 'Bendahara';
    }

    public function columns(): array
    {
        return [
            'kode_jenis' => ['label' => 'Kode Jenis', 'required' => true, 'format' => 'Kode dari menu Jenis Pembayaran (lihat daftar di bawah).'],
            'tahun_pelajaran' => ['label' => 'Tahun Pelajaran', 'required' => true, 'format' => 'Nama dari menu Tahun Pelajaran, contoh 2026/2027.'],
            'kelas' => ['label' => 'Kelas', 'required' => false, 'format' => 'Nama kelas di tahun pelajaran itu. Kosong = berlaku untuk semua kelas.'],
            'nominal' => ['label' => 'Nominal', 'required' => true, 'format' => 'Rupiah tanpa titik, contoh 150000.'],
            'tanggal_berlaku' => ['label' => 'Tanggal Berlaku', 'required' => false, 'format' => 'Tanggal, boleh kosong.'],
            'aktif' => ['label' => 'Aktif', 'required' => false, 'format' => 'Ya atau Tidak. Kosong = Ya.'],
        ];
    }

    public function exampleRows(): array
    {
        $year = AcademicYear::query()->where('is_active', true)->value('name') ?? '2026/2027';

        return [
            ['kode_jenis' => 'SPP', 'tahun_pelajaran' => $year, 'kelas' => null, 'nominal' => 150000, 'tanggal_berlaku' => null, 'aktif' => 'Ya'],
            ['kode_jenis' => 'DU', 'tahun_pelajaran' => $year, 'kelas' => 'X TKJ 1', 'nominal' => 500000, 'tanggal_berlaku' => null, 'aktif' => 'Ya'],
        ];
    }

    public function exportRows(): array
    {
        return PaymentRate::query()->with(['paymentType', 'academicYear', 'classRoom'])->orderBy('academic_year_id')->orderBy('payment_type_id')->get()
            ->map(fn (PaymentRate $rate): array => [
                'kode_jenis' => $rate->paymentType?->code,
                'tahun_pelajaran' => $rate->academicYear?->name,
                'kelas' => $rate->classRoom?->name,
                'nominal' => (int) $rate->amount,
                'tanggal_berlaku' => $rate->effective_date?->format('Y-m-d'),
                'aktif' => $this->yesNo($rate->status),
            ])
            ->all();
    }

    public function references(): array
    {
        return [
            'Kode Jenis' => PaymentType::query()->orderBy('code')->get()->map(fn (PaymentType $type): string => "{$type->code} = {$type->name} ({$type->type})")->all(),
            'Tahun Pelajaran' => AcademicYear::query()->orderBy('name')->pluck('name')->all(),
        ];
    }

    public function prepare(): void
    {
        $this->typesByCode = PaymentType::query()->whereNotNull('code')->get()
            ->keyBy(fn (PaymentType $type): string => strtoupper($type->code));
        $this->yearsByName = AcademicYear::all()->keyBy(fn (AcademicYear $year): string => strtolower($year->name));
        $this->classesByNameAndYear = ClassRoom::all()->keyBy(fn (ClassRoom $class): string => strtolower($class->name).'|'.$class->academic_year_id);

        $this->existingKeys = [];
        foreach (PaymentRate::all() as $rate) {
            $this->existingKeys[$rate->payment_type_id.'|'.$rate->academic_year_id.'|'.($rate->class_id ?? '')] = true;
        }
    }

    public function inspectRow(array $row): array
    {
        $errors = $this->missingRequired($row);
        $typeCode = $this->text($row, 'kode_jenis');
        $yearName = $this->text($row, 'tahun_pelajaran');
        $className = $this->text($row, 'kelas');
        $amount = $this->amount($row, 'nominal');
        $effectiveDate = $this->date($row, 'tanggal_berlaku');
        $isActive = $this->boolean($row, 'aktif', true);

        $type = $typeCode ? $this->typesByCode->get(strtoupper($typeCode)) : null;
        $year = $yearName ? $this->yearsByName->get(strtolower($yearName)) : null;
        $class = $className && $year ? $this->classesByNameAndYear->get(strtolower($className).'|'.$year->id) : null;

        if ($typeCode && ! $type) {
            $errors[] = "Kode jenis pembayaran tidak ditemukan: {$typeCode}";
        }
        if ($yearName && ! $year) {
            $errors[] = "Tahun pelajaran tidak ditemukan: {$yearName}";
        }
        if ($className && $year && ! $class) {
            $errors[] = "Kelas {$className} tidak ada di tahun pelajaran {$yearName}";
        }
        if ($amount === false || ($amount !== null && $amount < 1)) {
            $errors[] = 'Nominal harus angka rupiah lebih dari 0';
        }
        if ($effectiveDate === false) {
            $errors[] = 'Tanggal Berlaku bukan tanggal yang benar';
        }
        if ($isActive === null) {
            $errors[] = 'Aktif harus diisi Ya atau Tidak';
        }

        $key = $type && $year ? $type->id.'|'.$year->id.'|'.($class?->id ?? '') : null;

        if ($key && isset($this->existingKeys[$key])) {
            $errors[] = 'Tarif ini sudah ada. Ubah nominalnya lewat menu Tarif Pembayaran.';
        }

        return [
            'key' => $key,
            'label' => trim("{$typeCode} {$yearName} ".($className ?? 'semua kelas')),
            'attributes' => [
                'payment_type_id' => $type?->id,
                'academic_year_id' => $year?->id,
                'class_id' => $class?->id,
                'amount' => $amount ?: null,
                'effective_date' => $effectiveDate ?: null,
                'status' => (bool) $isActive,
            ],
            'existing' => null,
            'errors' => $errors,
        ];
    }

    public function save(array $attributes, ?Model $existing): void
    {
        PaymentRate::create($attributes);
    }
}
