<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chair extends Model
{
    protected $fillable = [
        'salon_id', 'label', 'status', 'current_booking_id',
    ];

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function currentBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'current_booking_id');
    }
}