<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'payment_type_id',
        'period',
        'description',
        'amount',
        'paid_amount',
        'remaining_amount',
        'status',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paymentType()
    {
        return $this->belongsTo(PaymentType::class);
    }
}
