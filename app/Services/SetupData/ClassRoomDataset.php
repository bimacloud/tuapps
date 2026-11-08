<?php

namespace App\Services\SetupData;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Major;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ClassRoomDataset extends SetupDataset
{
    /** @var Collection<string, Major> */
    private Collection $majorsByCode;

    /** @var Collection<string, AcademicYear> */
    private Collection $yearsByName;

    /** @var Collection<string, ClassRoom> */
    private Collection $existingByNameAndYear;

    public function key(): string
    {
        return 'classes';
    }

    public function title(): string
    {
        return 'Kelas';
    }

    public function role(): string
    {
        return 'Administrasi';
    }

    public function columns(): array
    {
        return [
            'nama_kelas' => ['label' => 'Nama Kelas', 'required' => true, 'format' => 'Contoh X TKJ 1. Kelas dengan nama dan tahun pelajaran yang sama akan diperbarui.'],
            'tingkat' => ['label' => 'Tingkat', 'required' => true, 'format' => 'X, XI, atau XII.'],
            'kode_jurusan' => ['label' => 'Kode Jurusan', 'required' => true, 'format' => 'Kode dari menu Jurusan (lihat daftar di bawah).'],
            'tahun_pelajaran' => ['label' => 'Tahun Pelajaran', 'required' => true, 'format' => 'Nama dari menu Tahun Pelajaran, contoh 2026/2027.'],
            'wali_kelas' => ['label' => 'Wali Kelas', 'required' => false, 'format' => 'Boleh kosong.'],
            'aktif' => ['label' => 'Aktif', 'required' => false, 'format' => 'Ya atau Tidak. Kosong = Ya.'],
        ];
    }

    public function exampleRows(): array
    {
        $year = AcademicYear::query()->where('is_active', true)->value('name') ?? '2026/2027';

        return [
            ['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'kode_jurusan' => 'TKJ', 'tahun_pelajaran' => $year, 'wali_kelas' => 'Nama Wali Kelas', 'aktif' => 'Ya'],
            ['nama_kelas' => 'XI TKJ 1', 'tingkat' => 'XI', 'kode_jurusan' => 'TKJ', 'tahun_pelajaran' => $year, 'wali_kelas' => null, 'aktif' => 'Ya'],
        ];
    }

    public function exportRows(): array
    {
        return ClassRoom::query()->with(['major', 'academicYear'])->orderBy('name')->get()
            ->map(fn (ClassRoom $class): array => [
                'nama_kelas' => $class->name,
                'tingkat' => $class->grade,
                'kode_jurusan' => $class->major?->code,
                'tahun_pelajaran' => $class->academicYear?->name,
                'wali_kelas' => $class->homeroom_teacher,
                'aktif' => $this->yesNo($class->status),
            ])
            ->all();
    }

    public function references(): array
    {
        return [
            'Kode Jurusan' => Major::query()->orderBy('code')->get()->map(fn (Major $major): string => "{$major->code} = {$major->name}")->all(),
            'Tahun Pelajaran' => AcademicYear::query()->orderBy('name')->pluck('name')->all(),
        ];
    }

    public function prepare(): void
    {
        $this->majorsByCode = Major::all()->keyBy(fn (Major $major): string => strtoupper($major->code));
        $this->yearsByName = AcademicYear::all()->keyBy(fn (AcademicYear $year): string => strtolower($year->name));
        $this->existingByNameAndYear = ClassRoom::all()->keyBy(fn (ClassRoom $class): string => strtolower($class->name).'|'.$class->academic_year_id);
    }

    public function inspectRow(array $row): array
    {
        $errors = $this->missingRequired($row);
        $name = $this->text($row, 'nama_kelas');
        $grade = $this->text($row, 'tingkat');
        $grade = $grade ? strtoupper($grade) : null;
        $majorCode = $this->text($row, 'kode_jurusan');
        $yearName = $this->text($row, 'tahun_pelajaran');
        $isActive = $this->boolean($row, 'aktif', true);

        $major = $majorCode ? $this->majorsByCode->get(strtoupper($majorCode)) : null;
        $year = $yearName ? $this->yearsByName->get(strtolower($yearName)) : null;

        if ($grade && ! in_array($grade, ['X', 'XI', 'XII'], true)) {
            $errors[] = 'Tingkat harus X, XI, atau XII';
        }
        if ($majorCode && ! $major) {
            $errors[] = "Kode jurusan tidak ditemukan: {$majorCode}";
        }
        if ($yearName && ! $year) {
            $errors[] = "Tahun pelajaran tidak ditemukan: {$yearName}";
        }
        if ($isActive === null) {
            $errors[] = 'Aktif harus diisi Ya atau Tidak';
        }

        $key = $name && $year ? strtolower($name).'|'.$year->id : null;

        return [
            'key' => $key,
            'label' => trim("{$name} ({$yearName})"),
            'attributes' => [
                'name' => $name,
                'grade' => $grade,
                'major_id' => $major?->id,
                'academic_year_id' => $year?->id,
                'homeroom_teacher' => $this->text($row, 'wali_kelas'),
                'status' => (bool) $isActive,
            ],
            'existing' => $key ? $this->existingByNameAndYear->get($key) : null,
            'errors' => $errors,
        ];
    }

    public function save(array $attributes, ?Model $existing): void
    {
        $existing ? $existing->update($attributes) : ClassRoom::create($attributes);
    }
}
