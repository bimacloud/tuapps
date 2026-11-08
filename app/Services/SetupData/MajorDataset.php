<?php

namespace App\Services\SetupData;

use App\Models\Major;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MajorDataset extends SetupDataset
{
    /** @var Collection<string, Major> */
    private Collection $existingByCode;

    public function key(): string
    {
        return 'majors';
    }

    public function title(): string
    {
        return 'Jurusan';
    }

    public function role(): string
    {
        return 'Administrasi';
    }

    public function columns(): array
    {
        return [
            'kode' => ['label' => 'Kode', 'required' => true, 'format' => 'Singkatan unik, contoh TKJ. Kode yang sudah ada akan diperbarui.'],
            'nama' => ['label' => 'Nama', 'required' => true, 'format' => 'Nama lengkap jurusan.'],
            'aktif' => ['label' => 'Aktif', 'required' => false, 'format' => 'Ya atau Tidak. Kosong = Ya.'],
        ];
    }

    public function exampleRows(): array
    {
        return [
            ['kode' => 'TKJ', 'nama' => 'Teknik Komputer dan Jaringan', 'aktif' => 'Ya'],
            ['kode' => 'AKL', 'nama' => 'Akuntansi dan Keuangan Lembaga', 'aktif' => 'Ya'],
        ];
    }

    public function exportRows(): array
    {
        return Major::query()->orderBy('code')->get()
            ->map(fn (Major $major): array => [
                'kode' => $major->code,
                'nama' => $major->name,
                'aktif' => $this->yesNo($major->status),
            ])
            ->all();
    }

    public function prepare(): void
    {
        $this->existingByCode = Major::all()->keyBy(fn (Major $major): string => strtoupper($major->code));
    }

    public function inspectRow(array $row): array
    {
        $errors = $this->missingRequired($row);
        $code = $this->text($row, 'kode');
        $code = $code ? strtoupper($code) : null;
        $name = $this->text($row, 'nama');
        $isActive = $this->boolean($row, 'aktif', true);

        if ($code && strlen($code) > 50) {
            $errors[] = 'Kode paling panjang 50 karakter';
        }
        if ($isActive === null) {
            $errors[] = 'Aktif harus diisi Ya atau Tidak';
        }

        return [
            'key' => $code,
            'label' => trim("{$code} {$name}"),
            'attributes' => ['code' => $code, 'name' => $name, 'status' => (bool) $isActive],
            'existing' => $code ? $this->existingByCode->get($code) : null,
            'errors' => $errors,
        ];
    }

    public function save(array $attributes, ?Model $existing): void
    {
        $existing ? $existing->update($attributes) : Major::create($attributes);
    }
}
