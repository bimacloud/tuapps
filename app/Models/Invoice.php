<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'student_id',
        'academic_year_id',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'status',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
    
    // Using boot to generate invoice_number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->invoice_number)) {
                $prefix = 'INV-' . date('Ymd') . '-';
                $lastInvoice = static::where('invoice_number', 'LIKE', $prefix . '%')->orderBy('id', 'desc')->first();
                $nextId = $lastInvoice ? intval(substr($lastInvoice->invoice_number, -4)) + 1 : 1;
                $model->invoice_number = $prefix . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
