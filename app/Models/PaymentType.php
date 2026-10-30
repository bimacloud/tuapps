<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category',
        'type',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function rates()
    {
        return $this->hasMany(PaymentRate::class);
    }
}
