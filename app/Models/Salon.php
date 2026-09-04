<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Salon extends Model
{
    protected $fillable = [
        'owner_id', 'name', 'type', 'address', 'latitude', 'longitude', 'phone', 'status',
        'opens_at', 'closes_at',
    ];

    protected $casts = [
        'opens_at' => 'datetime:H:i',
        'closes_at' => 'datetime:H:i',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function chairs(): HasMany
    {
        return $this->hasMany(Chair::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Whether the salon is currently within its operating hours.
     * If no hours are set, defaults to always open (backward compatible
     * with salons created before this feature existed).
     */
    public function isCurrentlyOpen(): bool
    {
        if (! $this->opens_at || ! $this->closes_at) {
            return true;
        }

        $now = now()->format('H:i:s');
        $opens = $this->opens_at->format('H:i:s');
        $closes = $this->closes_at->format('H:i:s');

        if ($opens < $closes) {
            return $now >= $opens && $now < $closes;
        }

        // Handles salons open past midnight (e.g. 10:00 - 02:00)
        return $now >= $opens || $now < $closes;
    }
}