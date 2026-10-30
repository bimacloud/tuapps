<?php

use App\Models\Major;
use App\Models\ClassRoom;
use App\Models\Student;

$tkjMajor = Major::where('code', 'TKJ')->first();
$apMajor = Major::where('code', 'AP')->first();

$tkjClass = ClassRoom::firstOrCreate([
    'name' => 'X TKJ 1',
], [
    'grade' => 'X',
    'major_id' => $tkjMajor->id,
    'academic_year_id' => 1,
    'homeroom_teacher' => 'Guru TKJ',
    'status' => true
]);

$apClass = ClassRoom::firstOrCreate([
    'name' => 'X AP 1',
], [
    'grade' => 'X',
    'major_id' => $apMajor->id,
    'academic_year_id' => 1,
    'homeroom_teacher' => 'Guru AP',
    'status' => true
]);

$students = [
    [
        'nis' => '1001',
        'nisn' => '0010010011',
        'name' => 'Ahmad Reza',
        'gender' => 'L',
        'class_id' => $tkjClass->id,
        'status' => 'AKTIF',
    ],
    [
        'nis' => '1002',
        'nisn' => '0010010012',
        'name' => 'Budi Santoso',
        'gender' => 'L',
        'class_id' => $tkjClass->id,
        'status' => 'AKTIF',
    ],
    [
        'nis' => '1003',
        'nisn' => '0010010013',
        'name' => 'Citra Lestari',
        'gender' => 'P',
        'class_id' => $tkjClass->id,
        'status' => 'AKTIF',
    ],
    [
        'nis' => '2001',
        'nisn' => '0020020021',
        'name' => 'Dina Mariana',
        'gender' => 'P',
        'class_id' => $apClass->id,
        'status' => 'AKTIF',
    ],
    [
        'nis' => '2002',
        'nisn' => '0020020022',
        'name' => 'Eko Prasetyo',
        'gender' => 'L',
        'class_id' => $apClass->id,
        'status' => 'AKTIF',
    ]
];

foreach ($students as $data) {
    Student::firstOrCreate(['nis' => $data['nis']], $data);
}

echo "Berhasil menambahkan 5 siswa di 2 jurusan (TKJ & AP).\n";
