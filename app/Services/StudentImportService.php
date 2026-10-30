<?php

namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;
use App\Models\Student;
use App\Models\ClassRoom;
use App\Models\Major;
use App\Models\Guardian;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentImportService
{
    public function preview($file)
    {
        $data = Excel::toArray(new class implements ToArray, WithHeadingRow {
            public function array(array $array) {}
        }, $file);

        $rows = $data[0] ?? [];
        $validatedRows = [];
        $hasErrors = false;

        $existingNis = Student::pluck('nis')->filter()->toArray();
        $existingNisn = Student::pluck('nisn')->filter()->toArray();
        
        $classes = ClassRoom::pluck('id', 'name')->toArray();
        $majors = Major::pluck('id', 'name')->toArray();

        foreach ($rows as $index => $row) {
            $errors = [];
            
            // Check mandatory
            if (empty($row['nama_lengkap'])) $errors[] = 'Nama Lengkap wajib diisi';
            if (empty($row['kelas'])) $errors[] = 'Kelas wajib diisi';
            
            // Check duplicate NIS
            if (!empty($row['nis']) && in_array($row['nis'], $existingNis)) {
                $errors[] = 'NIS sudah terdaftar';
            }
            
            // Check duplicate NISN
            if (!empty($row['nisn']) && in_array($row['nisn'], $existingNisn)) {
                $errors[] = 'NISN sudah terdaftar';
            }

            // Check class & major
            $classId = null;
            if (!empty($row['kelas'])) {
                if (!isset($classes[$row['kelas']])) {
                    $errors[] = 'Kelas tidak ditemukan: ' . $row['kelas'];
                } else {
                    $classId = $classes[$row['kelas']];
                }
            }

            $majorId = null;
            if (!empty($row['jurusan'])) {
                if (!isset($majors[$row['jurusan']])) {
                    $errors[] = 'Jurusan tidak ditemukan: ' . $row['jurusan'];
                } else {
                    $majorId = $majors[$row['jurusan']];
                }
            }

            if (count($errors) > 0) {
                $hasErrors = true;
            }

            $validatedRows[] = [
                'nisn' => $row['nisn'] ?? null,
                'nis' => $row['nis'] ?? null,
                'name' => $row['nama_lengkap'] ?? null,
                'gender' => $row['jenis_kelamin'] ?? null,
                'birth_place' => $row['tempat_lahir'] ?? null,
                'birth_date' => $row['tanggal_lahir'] ?? null,
                'address' => $row['alamat'] ?? null,
                'phone' => $row['telepon'] ?? null,
                'entry_year' => $row['tahun_masuk'] ?? null,
                'class_name' => $row['kelas'] ?? null,
                'class_id' => $classId,
                'major_name' => $row['jurusan'] ?? null,
                'major_id' => $majorId,
                'status' => $row['status'] ?? 'AKTIF',
                'guardian_name' => $row['nama_wali'] ?? null,
                'guardian_phone' => $row['telepon_wali'] ?? null,
                'guardian_relation' => $row['hubungan_wali'] ?? null,
                'errors' => $errors,
                'is_valid' => count($errors) === 0
            ];
        }

        return [
            'has_errors' => $hasErrors,
            'data' => $validatedRows
        ];
    }

    public function import(array $validatedData)
    {
        DB::beginTransaction();
        try {
            foreach ($validatedData as $row) {
                if (!$row['is_valid']) continue;

                $guardianId = null;
                if (!empty($row['guardian_name'])) {
                    $guardian = Guardian::firstOrCreate(
                        ['name' => $row['guardian_name'], 'phone' => $row['guardian_phone'] ?? null],
                        ['relation' => $row['guardian_relation'] ?? null]
                    );
                    $guardianId = $guardian->id;
                }

                Student::create([
                    'nisn' => $row['nisn'],
                    'nis' => $row['nis'],
                    'name' => $row['name'],
                    'gender' => $row['gender'],
                    'birth_place' => $row['birth_place'],
                    'birth_date' => $row['birth_date'],
                    'address' => $row['address'],
                    'phone' => $row['phone'],
                    'entry_year' => $row['entry_year'],
                    'class_id' => $row['class_id'],
                    'major_id' => $row['major_id'],
                    'status' => $row['status'] ?? 'AKTIF',
                    'guardian_id' => $guardianId,
                ]);
            }
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
