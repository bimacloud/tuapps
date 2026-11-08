<?php

namespace App\Services\SetupData;

use App\Models\PaymentType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class PaymentTypeDataset extends SetupDataset
{
    private const TYPES = ['BULANAN', 'SEMESTER', 'TAHUNAN', 'KHUSUS'];

    /** @var Collection<string, PaymentType> */
    private Collection $existingByCode;

    public function key(): string
    {
        return 'payment-types';
    }

    public function title(): string
    {
        return 'Jenis Pembayaran';
    }

    public function role(): string
    {
        return 'Bendahara';
    }

    public function columns(): array
    {
        return [
            'kode' => ['label' => 'Kode', 'required' => true, 'format' => 'Singkatan unik, contoh SPP. Kode yang sudah ada akan diperbarui.'],
            'nama' => ['label' => 'Nama', 'required' => true, 'format' => 'Nama pos pembayaran.'],
            'tipe' => ['label' => 'Tipe', 'required' => true, 'format' => 'BULANAN, SEMESTER, TAHUNAN, atau KHUSUS.'],
            'kategori' => ['label' => 'Kategori', 'required' => false, 'format' => 'Boleh kosong.'],
            'keterangan' => ['label' => 'Keterangan', 'required' => false, 'format' => 'Boleh kosong.'],
            'aktif' => ['label' => 'Aktif', 'required' => false, 'format' => 'Ya atau Tidak. Kosong = Ya.'],
        ];
    }

    public function exampleRows(): array
    {
        return [
            ['kode' => 'SPP', 'nama' => 'SPP', 'tipe' => 'BULANAN', 'kategori' => 'Rutin', 'keterangan' => null, 'aktif' => 'Ya'],
            ['kode' => 'DU', 'nama' => 'Daftar Ulang', 'tipe' => 'TAHUNAN', 'kategori' => null, 'keterangan' => null, 'aktif' => 'Ya'],
            ['kode' => 'LKS1', 'nama' => 'LKS/Buku Semester 1', 'tipe' => 'SEMESTER', 'kategori' => null, 'keterangan' => null, 'aktif' => 'Ya'],
        ];
    }

    public function exportRows(): array
    {
        return PaymentType::query()->orderBy('code')->get()
            ->map(fn (PaymentType $type): array => [
                'kode' => $type->code,
                'nama' => $type->name,
                'tipe' => $type->type,
                'kategori' => $type->category,
                'keterangan' => $type->description,
                'aktif' => $this->yesNo($type->status),
            ])
            ->all();
    }

    public function prepare(): void
    {
        $this->existingByCode = PaymentType::query()->whereNotNull('code')->get()
            ->keyBy(fn (PaymentType $type): string => strtoupper($type->code));
    }

    public function inspectRow(array $row): array
    {
        $errors = $this->missingRequired($row);
        $code = $this->text($row, 'kode');
        $code = $code ? strtoupper($code) : null;
        $name = $this->text($row, 'nama');
        $type = $this->text($row, 'tipe');
        $type = $type ? strtoupper($type) : null;
        $isActive = $this->boolean($row, 'aktif', true);

        if ($code && strlen($code) > 50) {
            $errors[] = 'Kode paling panjang 50 karakter';
        }
        if ($type && ! in_array($type, self::TYPES, true)) {
            $errors[] = 'Tipe harus BULANAN, SEMESTER, TAHUNAN, atau KHUSUS';
        }
        if ($isActive === null) {
            $errors[] = 'Aktif harus diisi Ya atau Tidak';
        }

        return [
            'key' => $code,
            'label' => trim("{$code} {$name}"),
            'attributes' => [
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'category' => $this->text($row, 'kategori'),
                'description' => $this->text($row, 'keterangan'),
                'status' => (bool) $isActive,
            ],
            'existing' => $code ? $this->existingByCode->get($code) : null,
            'errors' => $errors,
        ];
    }

    public function save(array $attributes, ?Model $existing): void
    {
        $existing ? $existing->update($attributes) : PaymentType::create($attributes);
    }
}
