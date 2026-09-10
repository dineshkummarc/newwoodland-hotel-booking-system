<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const BLOCKING_STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED];

    /**
     * A pending booking blocks availability only while it's still fresh
     * (i.e. the customer may still be mid-payment). Once a pending, unpaid
     * booking is older than this, it no longer blocks the room for other
     * customers — otherwise an abandoned checkout would squat the room
     * forever. Confirmed bookings always block, regardless of age.
     */
    public const PENDING_HOLD_MINUTES = 30;

    protected $fillable = [
        'confirmation_code', 'user_id', 'room_id', 'check_in', 'check_out', 'nights',
        'guests_label', 'customer_name', 'email', 'phone', 'country',
        'special_requests', 'nightly_rate', 'total_price', 'status', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date:Y-m-d',
            'check_out' => 'date:Y-m-d',
            'nightly_rate' => 'decimal:2',
            'total_price' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'confirmation_code';
    }

    public function latestPayment(): ?Payment
    {
        return $this->relationLoaded('payments')
            ? $this->payments->sortByDesc('created_at')->first()
            : $this->payments()->latest()->first();
    }

    public function isPaid(): bool
    {
        return $this->payments()->where('status', Payment::STATUS_COMPLETED)->exists();
    }

    /**
     * A booking may be self-cancelled by the guest only while it's still
     * pending/confirmed and check-in is at least $minHoursNotice away. The
     * hotel's actual notice requirement lives in Settings and is resolved by
     * BookingService::cancellationWindowHours() — the default of 0 here only
     * enforces the hard rule that a booking can never be cancelled once
     * check-in day has arrived.
     */
    public function isCancellable(int $minHoursNotice = 0): bool
    {
        return in_array($this->status, self::BLOCKING_STATUSES, true)
            && now()->addHours($minHoursNotice)->lt($this->check_in);
    }

    public function scopeOverlapping(Builder $query, int $roomId, string $checkIn, string $checkOut): Builder
    {
        return $query->where('room_id', $roomId)
            ->where(function (Builder $q) {
                $q->where('status', self::STATUS_CONFIRMED)
                    ->orWhere(function (Builder $pending) {
                        $pending->where('status', self::STATUS_PENDING)
                            ->where('created_at', '>=', now()->subMinutes(self::PENDING_HOLD_MINUTES));
                    });
            })
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn);
    }
}
