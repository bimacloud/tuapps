<?php

namespace App\Exports;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function query(): Builder
    {
        $query = Student::with(['classRoom.major', 'major', 'guardian']);

        if ($this->request->filled('class_id')) {
            $query->where('class_id', $this->request->class_id);
        }

        if ($this->request->filled('major_id')) {
            $query->where('major_id', $this->request->major_id);
        }

        if ($this->request->filled('status')) {
            $query->where('status', $this->request->status);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'NISN',
            'NIS',
            'Nama Lengkap',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Alamat',
            'Telepon',
            'Tahun Masuk',
            'Kelas',
            'Jurusan',
            'Status',
            'Nama Wali',
            'Telepon Wali',
            'Hubungan Wali',
        ];
    }

    public function map($student): array
    {
        return [
            $student->nisn,
            $student->nis,
            $student->name,
            $student->gender,
            $student->birth_place,
            $student->birth_date ? $student->birth_date->format('Y-m-d') : null,
            $student->address,
            $student->phone,
            $student->entry_year,
            $student->classRoom ? $student->classRoom->name : null,
            $student->major ? $student->major->name : null,
            $student->status,
            $student->guardian ? $student->guardian->name : null,
            $student->guardian ? $student->guardian->phone : null,
            $student->guardian ? $student->guardian->relation : null,
        ];
    }
}
