<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'transaction_number',
        'student_id',
        'cashier_id',
        'cashier_session_id',
        'payment_method_id',
        'payment_date',
        'total_amount',
        'notes',
        'status',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items()
    {
        return $this->hasMany(PaymentItem::class);
    }
}
