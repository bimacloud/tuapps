<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_type_id',
        'academic_year_id',
        'class_id',
        'amount',
        'effective_date',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'effective_date' => 'date',
    ];

    public function paymentType()
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }
}
