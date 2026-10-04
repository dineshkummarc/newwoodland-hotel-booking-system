<?php

namespace App\Models;

use App\Support\BookingAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class Booking extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_CHECKED_OUT = 'checked_out';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    public const STATUSES = [
        self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_CHECKED_IN,
        self::STATUS_CHECKED_OUT, self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_NO_SHOW,
    ];

    /**
     * The only status a booking may move to from a given status. Enforced by
     * BookingService — never mutate `status` directly outside of it, or a
     * booking can end up in a state this map says is unreachable.
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_CHECKED_IN, self::STATUS_CANCELLED, self::STATUS_NO_SHOW],
        // checked_in → cancelled: the guest arrived, then cancelled the stay
        // (BookingService::cancelAfterArrival — after-arrival cancellation charge).
        self::STATUS_CHECKED_IN => [self::STATUS_CHECKED_OUT, self::STATUS_CANCELLED],
        self::STATUS_CHECKED_OUT => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_NO_SHOW => [],
    ];

    /**
     * Statuses that represent an unresolved/self-cancellable hold — used by
     * isCancellable(). Deliberately excludes checked_in/checked_out: once a
     * guest has actually arrived, self-service cancellation no longer makes
     * sense and any correction becomes a front-desk/admin matter.
     */
    public const BLOCKING_STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED];

    /**
     * Statuses that block room availability for other bookings. Unlike
     * BLOCKING_STATUSES above, this always includes checked_in — a guest
     * physically in the room must block it regardless of self-cancellation
     * rules.
     */
    public const AVAILABILITY_BLOCKING_STATUSES = [self::STATUS_CONFIRMED, self::STATUS_CHECKED_IN];

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
        'special_requests', 'nightly_rate', 'price_breakdown', 'discount_percent', 'discount_amount', 'total_price',
        'deposit_percent', 'status', 'cancelled_at', 'cancellation_charge_percent',
        'checked_in_at', 'checked_out_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date:Y-m-d',
            'check_out' => 'date:Y-m-d',
            'nightly_rate' => 'decimal:2',
            'price_breakdown' => 'array',
            'total_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'deposit_percent' => 'integer',
            'cancelled_at' => 'datetime',
            'cancellation_charge_percent' => 'integer',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * Last line of defence against double booking: whatever code path
     * (admin UI, service, a future API) moves a booking onto its room as
     * confirmed, it is refused if another booking already holds any of its
     * rooms for overlapping dates. The services do the authoritative check
     * under a room lock (BookingService::lockRooms()); this catches any
     * path that forgets to.
     */
    protected static function booted(): void
    {
        static::updating(function (Booking $booking) {
            if (! $booking->isDirty('status')
                || $booking->status !== self::STATUS_CONFIRMED
                || in_array($booking->getOriginal('status'), self::AVAILABILITY_BLOCKING_STATUSES, true)) {
                return;
            }

            foreach ($booking->roomIds() as $roomId) {
                $taken = static::query()
                    ->overlapping($roomId, $booking->check_in->toDateString(), $booking->check_out->toDateString())
                    ->whereKeyNot($booking->id)
                    ->exists();

                if ($taken) {
                    throw ValidationException::withMessages([
                        'status' => 'This booking cannot be confirmed: one of its rooms is already held by another booking for overlapping dates.',
                    ]);
                }
            }
        });
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

    /**
     * Incidental charges added during the stay (voided ones included — use
     * chargesTotal() for the amount actually owed).
     */
    public function charges(): HasMany
    {
        return $this->hasMany(BookingCharge::class);
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

    /**
     * Whether this booking is still within its short (online) room hold:
     * pending and created less than PENDING_HOLD_MINUTES ago. Offline
     * holds (bank transfer) are separate — see Payment::scopeActiveOfflineHold().
     */
    public function holdsRoomsNow(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->created_at->gte(now()->subMinutes(self::PENDING_HOLD_MINUTES));
    }

    public function isPaid(): bool
    {
        return $this->payments()->where('status', Payment::STATUS_COMPLETED)->exists();
    }

    /**
     * Total actually collected across every completed payment on this
     * booking (online or manual/cash). In today's architecture exactly one
     * payment ever reaches "completed" per booking, but this sums rather
     * than assumes that, so it stays correct even if that ever changes.
     */
    public function amountPaid(): float
    {
        return (float) $this->payments()->where('status', Payment::STATUS_COMPLETED)->sum('amount');
    }

    /**
     * Positive = still owed. Zero = paid in full. Negative = overpaid —
     * e.g. a booking modification reduced the total after payment was
     * already collected. Never auto-resolved; a negative balance is
     * surfaced to staff, not refunded automatically.
     */
    public function outstandingBalance(): float
    {
        return round($this->grandTotal() - $this->netPaid(), 2);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Rooms 2..n of a multi-room booking. room_id is always the primary
     * room; a single-room booking has no rows here.
     */
    public function additionalRooms(): HasMany
    {
        return $this->hasMany(BookingRoom::class);
    }

    public function isMultiRoom(): bool
    {
        return $this->additionalRooms->isNotEmpty();
    }

    public function roomCount(): int
    {
        return 1 + $this->additionalRooms->count();
    }

    /** @return list<int> every room this booking occupies, primary first */
    public function roomIds(): array
    {
        return array_values(array_merge([(int) $this->room_id], $this->additionalRooms->pluck('room_id')->map(fn ($id) => (int) $id)->all()));
    }

    /**
     * Every room on the booking as display/report lines, primary first. The
     * primary room's subtotal is whatever the booking's subtotal leaves after
     * the additional rooms, so lines always add up to the booking exactly.
     *
     * @return list<array{room:?Room, guests_label:string, nightly_rate:float, subtotal:float, primary:bool}>
     */
    public function roomLines(): array
    {
        $extras = $this->additionalRooms->loadMissing('room');
        $extrasSubtotal = (float) $extras->sum('subtotal');

        return array_merge([[
            'room' => $this->room,
            'guests_label' => (string) $this->guests_label,
            'nightly_rate' => (float) $this->nightly_rate,
            'subtotal' => round($this->subtotal() - $extrasSubtotal, 2),
            'primary' => true,
        ]], $extras->map(fn (BookingRoom $line) => [
            'room' => $line->room,
            'guests_label' => $line->guests_label,
            'nightly_rate' => (float) $line->nightly_rate,
            'subtotal' => (float) $line->subtotal,
            'primary' => false,
        ])->all());
    }

    /**
     * Guest-facing room description: the room name for a single-room
     * booking (unchanged from before multi-room support), or e.g.
     * "Deluxe Room × 2, Family Suite" for several rooms.
     */
    public function roomNames(string $fallback = '—'): string
    {
        if (! $this->isMultiRoom()) {
            return $this->room?->name ?? $fallback;
        }

        return collect($this->roomLines())
            ->map(fn ($line) => $line['room']?->name ?? $fallback)
            ->countBy()
            ->map(fn ($count, $name) => $count > 1 ? "{$name} × {$count}" : $name)
            ->implode(', ');
    }

    /** "Deluxe Room — Room 101 + 1 more" style label for lists. */
    public function roomsSummary(): string
    {
        $label = $this->room?->label() ?? '—';

        return $this->isMultiRoom() ? $label.' + '.$this->additionalRooms->count().' more' : $label;
    }

    /** Staff-recorded ID documents (staff-only; see GuestDocument). */
    public function guestDocuments(): HasMany
    {
        return $this->hasMany(GuestDocument::class);
    }

    /** Money actually returned to the guest (completed refunds). */
    public function refundedAmount(): float
    {
        return round((float) $this->refunds()->where('status', Refund::STATUS_COMPLETED)->sum('amount'), 2);
    }

    /** What the hotel has kept: collected payments minus completed refunds. */
    public function netPaid(): float
    {
        return round($this->amountPaid() - $this->refundedAmount(), 2);
    }

    /**
     * The most that can still be recorded as a refund: money collected
     * minus refunds already pending or completed. Never negative.
     */
    public function refundableAmount(): float
    {
        $committed = (float) $this->refunds()->openOrCompleted()->sum('amount');

        return max(0, round($this->amountPaid() - $committed, 2));
    }

    /**
     * Sum of the stay's non-voided incidental charges (restaurant, laundry…).
     */
    public function chargesTotal(): float
    {
        return round((float) $this->charges()->active()->sum('amount'), 2);
    }

    /**
     * Everything the guest owes for the stay: the agreed room price
     * (total_price, which deposits/discounts/cancellation charges are based
     * on) plus any incidental charges.
     */
    public function grandTotal(): float
    {
        // A stay cancelled after arrival owes the cancellation charge (not
        // the full room price), plus any extra charges already incurred.
        if ($this->wasCancelledAfterArrival()) {
            return round($this->cancellationCharge(0) + $this->chargesTotal(), 2);
        }

        return round((float) $this->total_price + $this->chargesTotal(), 2);
    }

    /**
     * The guest had arrived (checked in) and then cancelled the stay — the
     * after-arrival cancellation charge was frozen on the booking.
     */
    public function wasCancelledAfterArrival(): bool
    {
        return $this->status === self::STATUS_CANCELLED && $this->cancellation_charge_percent !== null;
    }

    /**
     * Incidental charges can be added only while the guest is staying or
     * the folio is still open after check-out.
     */
    public function acceptsCharges(): bool
    {
        return in_array($this->status, [self::STATUS_CHECKED_IN, self::STATUS_CHECKED_OUT], true);
    }

    /**
     * Price before the first-booking discount (nightly rate × nights).
     */
    public function subtotal(): float
    {
        return round((float) $this->total_price + (float) $this->discount_amount, 2);
    }

    /**
     * Whether nights were priced at different rates (weekend/season/holiday
     * rules) — the per-night rates are frozen in price_breakdown.
     */
    public function hasVariableRates(): bool
    {
        return ! empty($this->price_breakdown);
    }

    /**
     * The room charge as display lines: one per distinct (rule, rate), e.g.
     * "2 × NPR 2,000 (base)" and "1 × NPR 2,400 (Weekend nights)". Falls back
     * to nightly_rate × nights for bookings priced at a single rate.
     *
     * @return list<array{label:string, nights:int, rate:float, amount:float}>
     */
    public function rateLines(): array
    {
        if (! $this->hasVariableRates()) {
            return [[
                'label' => 'Standard rate',
                'nights' => (int) $this->nights,
                'rate' => (float) $this->nightly_rate,
                'amount' => round((float) $this->nightly_rate * $this->nights, 2),
            ]];
        }

        return collect($this->price_breakdown)
            ->groupBy(fn ($n) => ($n['rule'] ?? 'Standard rate').'|'.$n['rate'])
            ->map(fn ($nights) => [
                'label' => $nights->first()['rule'] ?? 'Standard rate',
                'nights' => $nights->count(),
                'rate' => (float) $nights->first()['rate'],
                'amount' => round($nights->sum('rate'), 2),
            ])
            ->values()
            ->all();
    }

    public function hasDiscount(): bool
    {
        return (float) $this->discount_amount > 0;
    }

    /**
     * What the guest should pay in the payment they are about to make: the
     * deposit (e.g. 50%) if they chose to pay part now and nothing is paid
     * yet, otherwise the full outstanding balance. Always server-calculated.
     */
    public function amountDueNow(): float
    {
        if ($this->deposit_percent && $this->amountPaid() <= 0) {
            return round((float) $this->total_price * $this->deposit_percent / 100, 2);
        }

        return max(0, $this->outstandingBalance());
    }

    /**
     * The amount the hotel keeps when this booking is cancelled
     * ($percent of the booking total, per the hotel's cancellation policy).
     */
    public function cancellationCharge(int $percent): float
    {
        return round((float) $this->total_price * $this->appliedCancellationPercent($percent) / 100, 2);
    }

    /**
     * The cancellation % that applies to this booking: the one frozen on it
     * when a stay was cancelled after arrival, otherwise $percent (the
     * Settings deduction for pre-arrival cancellations).
     */
    public function appliedCancellationPercent(int $percent): int
    {
        return $this->cancellation_charge_percent ?? $percent;
    }

    /**
     * What should still be refunded if this booking is cancelled: anything
     * paid beyond the cancellation charge, less refunds already completed.
     * Refunds are made manually by staff and recorded as Refund rows.
     */
    public function refundDue(int $percent): float
    {
        // Extra charges (only possible during a stay) are owed on top of the
        // cancellation charge; for pre-arrival cancellations they are zero.
        return max(0, round($this->amountPaid() - $this->cancellationCharge($percent) - $this->chargesTotal() - $this->refundedAmount(), 2));
    }

    /**
     * A booking may be self-cancelled by the guest only while it's still
     * pending/confirmed — i.e. before arrival (check-in). The notice
     * requirement lives in Settings (BookingService::cancellationWindowHours());
     * hotel policy is free cancellation before arrival, so it is 0 and the
     * guest may cancel until the end of the check-in day, matching the expiry
     * of the signed guest cancellation link. With a notice of N > 0 hours,
     * check-in must be at least N hours away.
     */
    public function isCancellable(int $minHoursNotice = 0): bool
    {
        $deadline = $minHoursNotice > 0 ? $this->check_in : $this->check_in->copy()->endOfDay();

        return in_array($this->status, self::BLOCKING_STATUSES, true)
            && now()->addHours($minHoursNotice)->lt($deadline);
    }

    /**
     * Whether this booking may currently move to $status per Booking::TRANSITIONS.
     */
    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * A booking made without a registered account — the only kind that
     * needs the signed-URL self-cancellation link, since account holders
     * already have /my-bookings.
     */
    public function isGuestBooking(): bool
    {
        return is_null($this->user_id);
    }

    /**
     * A signed, tamper-proof, self-expiring cancellation link for a guest
     * (no-account) booking. It cannot be forged without the app key, and
     * naturally expires once check-in day arrives — after which
     * BookingService::cancel() would refuse the cancellation anyway.
     * Clicking it never bypasses the normal cancellation rules (notice
     * window, already-cancelled, etc.) — see GuestCancellationController.
     */
    public function guestCancellationUrl(): string
    {
        return URL::temporarySignedRoute(
            'booking.cancel.guest',
            $this->check_in->copy()->endOfDay(),
            ['booking' => $this->confirmation_code]
        );
    }

    /**
     * Check-in date with the hotel's check-in time, e.g. "Sat, 10 Oct 2026,
     * from 12:00 PM". Stays are by DATE with fixed times (Admin → Settings):
     * a guest arriving at 3 PM checks out on the check-out date by the
     * hotel's check-out time — never "24 hours after arrival".
     */
    public function checkInLabel(): string
    {
        return $this->check_in->format('D, d M Y').self::timeSuffix('from', app(\App\Services\SettingsService::class)->current()->checkin_time);
    }

    /** Check-out date with the hotel's fixed check-out time, e.g. "Sun, 11 Oct 2026, by 12:00 PM". */
    public function checkOutLabel(): string
    {
        return $this->check_out->format('D, d M Y').self::timeSuffix('by', app(\App\Services\SettingsService::class)->current()->checkout_time);
    }

    protected static function timeSuffix(string $word, ?string $time): string
    {
        if (! filled($time)) {
            return '';
        }

        try {
            return ", {$word} ".\Illuminate\Support\Carbon::parse($time)->format('g:i A');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * A signed, expiring "view my booking" link for the guest's emails —
     * the confirmation code alone never opens the booking page.
     */
    public function viewUrl(): string
    {
        return BookingAccess::viewUrl($this);
    }

    /**
     * A signed, expiring link to this booking's invoice PDF, for the guest's
     * confirmation email. Valid until 60 days after check-out.
     */
    public function invoiceUrl(): string
    {
        return URL::temporarySignedRoute(
            'booking.invoice.guest',
            $this->check_out->copy()->endOfDay()->addDays(60),
            ['booking' => $this->confirmation_code]
        );
    }

    /**
     * Bookings that occupy $roomId — as their primary room or as one of the
     * additional rooms of a multi-room booking.
     */
    public function scopeOccupyingRoom(Builder $query, int $roomId): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('room_id', $roomId)
            ->orWhereHas('additionalRooms', fn (Builder $r) => $r->where('room_id', $roomId)));
    }

    public function scopeOverlapping(Builder $query, int $roomId, string $checkIn, string $checkOut): Builder
    {
        return $query->occupyingRoom($roomId)
            ->where(function (Builder $q) {
                $q->whereIn('status', self::AVAILABILITY_BLOCKING_STATUSES)
                    ->orWhere(function (Builder $pending) {
                        $pending->where('status', self::STATUS_PENDING)
                            ->where(function (Builder $held) {
                                $held->where('created_at', '>=', now()->subMinutes(self::PENDING_HOLD_MINUTES))
                                    // A guest who chose Bank Transfer or Cash gets the longer offline-payment hold.
                                    ->orWhereHas('payments', fn (Builder $p) => $p->activeOfflineHold());
                            });
                    });
            })
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn);
    }
}
