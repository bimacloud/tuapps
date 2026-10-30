<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'nisn',
        'nis',
        'name',
        'gender',
        'birth_place',
        'birth_date',
        'address',
        'phone',
        'entry_year',
        'class_id',
        'major_id',
        'guardian_id',
        'status',
        'photo',
    ];

    protected $casts = [
        'birth_date' => 'date:Y-m-d',
    ];

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function major()
    {
        return $this->belongsTo(Major::class);
    }

    public function guardian()
    {
        return $this->belongsTo(Guardian::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
