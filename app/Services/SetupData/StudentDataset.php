<?php

namespace App\Services\SetupData;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Guardian;
use App\Models\Major;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Students with their guardian. Column names match the student export on the Siswa page,
 * so a file downloaded there can be imported here.
 */
class StudentDataset extends SetupDataset
{
    private const STATUSES = ['AKTIF', 'LULUS', 'PINDAH', 'KELUAR'];

    /** @var array<string, list<ClassRoom>> */
    private array $classesByName = [];

    private ?int $activeYearId = null;

    /** @var Collection<int, Major> */
    private Collection $majorsById;

    /** @var Collection<string, Major> */
    private Collection $majorsByName;

    /** @var Collection<string, Major> */
    private Collection $majorsByCode;

    /** @var Collection<string, Student> */
    private Collection $studentsByNis;

    /** @var Collection<string, Student> */
    private Collection $studentsByNisn;

    /** @var array<string, int> */
    private array $nisInFile = [];

    /** @var array<string, int> */
    private array $nisnInFile = [];

    public function key(): string
    {
        return 'students';
    }

    public function title(): string
    {
        return 'Siswa';
    }

    public function role(): string
    {
        return 'Administrasi';
    }

    public function columns(): array
    {
        return [
            'nisn' => ['label' => 'NISN', 'required' => false, 'format' => 'Teks, 10 digit. Siswa dengan NIS atau NISN yang sudah ada akan diperbarui.'],
            'nis' => ['label' => 'NIS', 'required' => false, 'format' => 'Teks. Isi NIS atau NISN supaya impor ulang tidak membuat siswa ganda.'],
            'nama_lengkap' => ['label' => 'Nama Lengkap', 'required' => true, 'format' => 'Nama siswa.'],
            'jenis_kelamin' => ['label' => 'Jenis Kelamin', 'required' => false, 'format' => 'L atau P.'],
            'tempat_lahir' => ['label' => 'Tempat Lahir', 'required' => false, 'format' => 'Nama kota.'],
            'tanggal_lahir' => ['label' => 'Tanggal Lahir', 'required' => false, 'format' => 'Tanggal, contoh 2010-05-21 atau 21/05/2010.'],
            'alamat' => ['label' => 'Alamat', 'required' => false, 'format' => 'Alamat rumah.'],
            'telepon' => ['label' => 'Telepon', 'required' => false, 'format' => 'Nomor HP siswa.'],
            'tahun_masuk' => ['label' => 'Tahun Masuk', 'required' => false, 'format' => '4 digit, contoh 2026.'],
            'kelas' => ['label' => 'Kelas', 'required' => true, 'format' => 'Nama dari menu Kelas, sama persis (lihat daftar di bawah).'],
            'jurusan' => ['label' => 'Jurusan', 'required' => false, 'format' => 'Nama atau kode jurusan. Kosong = mengikuti jurusan kelas.'],
            'status' => ['label' => 'Status', 'required' => false, 'format' => 'AKTIF, LULUS, PINDAH, atau KELUAR. Kosong = AKTIF.'],
            'nama_wali' => ['label' => 'Nama Wali', 'required' => false, 'format' => 'Orang tua atau wali penerima pemberitahuan pembayaran.'],
            'telepon_wali' => ['label' => 'Telepon Wali', 'required' => false, 'format' => 'Nomor WhatsApp wali, contoh 081234567890.'],
            'hubungan_wali' => ['label' => 'Hubungan Wali', 'required' => false, 'format' => 'Ayah, Ibu, atau Wali.'],
        ];
    }

    public function textColumns(): array
    {
        return ['nisn', 'nis', 'telepon', 'telepon_wali'];
    }

    public function exampleRows(): array
    {
        return [[
            'nisn' => '0101234567',
            'nis' => '2026001',
            'nama_lengkap' => 'Nama Siswa',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Nama Kota',
            'tanggal_lahir' => '2010-05-21',
            'alamat' => 'Alamat rumah',
            'telepon' => '081200000001',
            'tahun_masuk' => 2026,
            'kelas' => ClassRoom::query()->orderBy('name')->value('name') ?? 'X TKJ 1',
            'jurusan' => null,
            'status' => 'AKTIF',
            'nama_wali' => 'Nama Wali',
            'telepon_wali' => '081200000002',
            'hubungan_wali' => 'Ayah',
        ]];
    }

    public function exportRows(): array
    {
        return Student::query()->with(['classRoom', 'major', 'guardian'])->orderBy('name')->get()
            ->map(fn (Student $student): array => [
                'nisn' => $student->nisn,
                'nis' => $student->nis,
                'nama_lengkap' => $student->name,
                'jenis_kelamin' => $student->gender,
                'tempat_lahir' => $student->birth_place,
                'tanggal_lahir' => $student->birth_date?->format('Y-m-d'),
                'alamat' => $student->address,
                'telepon' => $student->phone,
                'tahun_masuk' => $student->entry_year,
                'kelas' => $student->classRoom?->name,
                'jurusan' => $student->major?->name,
                'status' => $student->status,
                'nama_wali' => $student->guardian?->name,
                'telepon_wali' => $student->guardian?->phone,
                'hubungan_wali' => $student->guardian?->relation,
            ])
            ->all();
    }

    public function references(): array
    {
        return [
            'Kelas' => ClassRoom::query()->with('academicYear')->orderBy('name')->get()
                ->map(fn (ClassRoom $class): string => trim("{$class->name} ({$class->academicYear?->name})"))
                ->all(),
            'Jurusan' => Major::query()->orderBy('code')->get()->map(fn (Major $major): string => "{$major->code} = {$major->name}")->all(),
        ];
    }

    public function prepare(): void
    {
        $this->activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        $this->classesByName = [];
        foreach (ClassRoom::all() as $class) {
            $this->classesByName[strtolower($class->name)][] = $class;
        }

        $majors = Major::all();
        $this->majorsById = $majors->keyBy('id');
        $this->majorsByName = $majors->keyBy(fn (Major $major): string => strtolower($major->name));
        $this->majorsByCode = $majors->keyBy(fn (Major $major): string => strtolower($major->code));

        $students = Student::all();
        $this->studentsByNis = $students->whereNotNull('nis')->keyBy('nis');
        $this->studentsByNisn = $students->whereNotNull('nisn')->keyBy('nisn');
        $this->nisInFile = [];
        $this->nisnInFile = [];
    }

    public function inspectRow(array $row): array
    {
        $errors = $this->missingRequired($row);
        $nis = $this->text($row, 'nis');
        $nisn = $this->text($row, 'nisn');
        $name = $this->text($row, 'nama_lengkap');
        $className = $this->text($row, 'kelas');
        $majorName = $this->text($row, 'jurusan');
        $gender = $this->gender($this->text($row, 'jenis_kelamin'));
        $birthDate = $this->date($row, 'tanggal_lahir');
        $entryYear = $this->text($row, 'tahun_masuk');
        $status = strtoupper($this->text($row, 'status') ?? 'AKTIF');

        $class = $className ? $this->findClass($className, $errors) : null;
        $major = $class ? $this->majorsById->get($class->major_id) : null;

        if ($majorName) {
            $major = $this->majorsByName->get(strtolower($majorName)) ?? $this->majorsByCode->get(strtolower($majorName));
            if (! $major) {
                $errors[] = "Jurusan tidak ditemukan: {$majorName}";
            }
        }

        if ($gender === false) {
            $errors[] = 'Jenis Kelamin harus L atau P';
        }
        if ($birthDate === false) {
            $errors[] = 'Tanggal Lahir bukan tanggal yang benar';
        }
        if ($entryYear && ! preg_match('/^\d{4}$/', $entryYear)) {
            $errors[] = 'Tahun Masuk harus 4 digit';
        }
        if (! in_array($status, self::STATUSES, true)) {
            $errors[] = 'Status harus AKTIF, LULUS, PINDAH, atau KELUAR';
        }

        $existing = $this->findExisting($nis, $nisn, $errors);
        $this->rejectDuplicatesInFile($nis, $nisn, $errors);

        return [
            'key' => null,
            'label' => trim($name.($nis ? " ({$nis})" : '').($className ? " - {$className}" : '')),
            'attributes' => [
                'nis' => $nis,
                'nisn' => $nisn,
                'name' => $name,
                'gender' => $gender ?: null,
                'birth_place' => $this->text($row, 'tempat_lahir'),
                'birth_date' => $birthDate ?: null,
                'address' => $this->text($row, 'alamat'),
                'phone' => $this->text($row, 'telepon'),
                'entry_year' => $entryYear,
                'class_id' => $class?->id,
                'major_id' => $major?->id,
                'status' => $status,
                'guardian_name' => $this->text($row, 'nama_wali'),
                'guardian_phone' => $this->text($row, 'telepon_wali'),
                'guardian_relation' => $this->text($row, 'hubungan_wali'),
            ],
            'existing' => $existing,
            'errors' => $errors,
        ];
    }

    public function save(array $attributes, ?Model $existing): void
    {
        $guardianName = $attributes['guardian_name'];
        $guardianPhone = $attributes['guardian_phone'];
        $guardianRelation = $attributes['guardian_relation'];
        unset($attributes['guardian_name'], $attributes['guardian_phone'], $attributes['guardian_relation']);

        if ($guardianName) {
            $attributes['guardian_id'] = Guardian::firstOrCreate(
                ['name' => $guardianName, 'phone' => $guardianPhone],
                ['relation' => $guardianRelation],
            )->id;
        }

        $existing ? $existing->update($attributes) : Student::create($attributes);
    }

    private function gender(?string $value): string|false|null
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($value)) {
            'l', 'laki-laki', 'laki laki' => 'L',
            'p', 'perempuan' => 'P',
            default => false,
        };
    }

    /**
     * Class names repeat across academic years, so prefer the class in the active year.
     *
     * @param  list<string>  $errors
     */
    private function findClass(string $className, array &$errors): ?ClassRoom
    {
        $candidates = $this->classesByName[strtolower($className)] ?? [];

        if (count($candidates) === 0) {
            $errors[] = "Kelas tidak ditemukan: {$className}";

            return null;
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $inActiveYear = array_values(array_filter($candidates, fn (ClassRoom $class): bool => $class->academic_year_id === $this->activeYearId));

        if (count($inActiveYear) === 1) {
            return $inActiveYear[0];
        }

        $errors[] = "Kelas {$className} ada di beberapa tahun pelajaran. Tandai tahun pelajaran aktif dulu.";

        return null;
    }

    /**
     * @param  list<string>  $errors
     */
    private function findExisting(?string $nis, ?string $nisn, array &$errors): ?Student
    {
        $byNis = $nis ? $this->studentsByNis->get($nis) : null;
        $byNisn = $nisn ? $this->studentsByNisn->get($nisn) : null;

        if ($byNis && $byNisn && $byNis->id !== $byNisn->id) {
            $errors[] = 'NIS dan NISN milik dua siswa yang berbeda';

            return null;
        }

        return $byNis ?? $byNisn;
    }

    /**
     * @param  list<string>  $errors
     */
    private function rejectDuplicatesInFile(?string $nis, ?string $nisn, array &$errors): void
    {
        if ($nis && isset($this->nisInFile[$nis])) {
            $errors[] = 'NIS sama dengan baris lain di file';
        }
        if ($nisn && isset($this->nisnInFile[$nisn])) {
            $errors[] = 'NISN sama dengan baris lain di file';
        }

        if ($nis) {
            $this->nisInFile[$nis] = 1;
        }
        if ($nisn) {
            $this->nisnInFile[$nisn] = 1;
        }
    }
}
