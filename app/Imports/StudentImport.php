<?php

namespace App\Imports;

use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;

class StudentImport implements ToModel
{
    public function model(array $row): Model|null
    {
        return new Student([
            //
        ]);
    }
}
