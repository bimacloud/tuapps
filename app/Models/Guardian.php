<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Guardian extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'relation',
    ];

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Route notifications for the WhatsApp channel.
     */
    public function routeNotificationForWhatsApp(): ?string
    {
        return $this->phone;
    }
}
