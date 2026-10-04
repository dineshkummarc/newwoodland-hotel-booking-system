<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An additional room on a multi-room booking (rooms 2..n). The booking's
 * own room_id is always its primary room.
 */
class BookingRoom extends Model
{
    protected $fillable = ['booking_id', 'room_id', 'guests_label', 'nightly_rate', 'price_breakdown', 'subtotal'];

    protected function casts(): array
    {
        return [
            'nightly_rate' => 'decimal:2',
            'price_breakdown' => 'array',
            'subtotal' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
