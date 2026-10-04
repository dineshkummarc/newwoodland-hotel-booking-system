<?php

namespace App\Services;

use App\Mail\BookingCancelledAdminMail;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmationMail;
use App\Mail\NewBookingNotificationMail;
use App\Mail\PaymentNeedsReviewMail;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\BookingRoom;
use App\Models\Payment;
use App\Models\Room;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookingService
{
    use DetectsConcurrencyErrors;

    /**
     * How many times an inventory transaction is attempted when MySQL
     * reports a deadlock or lock-wait timeout (another request held the
     * same rooms). Each attempt re-runs the whole transaction, including
     * the availability check, so a retry can never double-book.
     */
    public const LOCK_ATTEMPTS = 3;

    /**
     * Statuses staff may move a booking between with the manual status
     * override (Admin → Booking → Status). Anything past confirmed goes
     * through the dedicated check-in/check-out/no-show/complete actions.
     */
    public const STAFF_OVERRIDABLE_STATUSES = [
        Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED,
    ];

    public function __construct(
        protected SettingsService $settingsService,
        protected RoomPricingService $pricing,
        protected AuditLogService $auditLog,
    ) {}

    /**
     * Run $callback in a transaction that changes room inventory.
     *
     * Every such transaction starts by locking the affected ROOM rows
     * (lockRooms()), which serializes all bookings of the same room no
     * matter whether any booking rows exist yet — locking only existing
     * overlapping bookings locks nothing for a free room, which is exactly
     * when two guests can race. If MySQL still reports a deadlock or a
     * lock-wait timeout, the whole transaction is retried; once attempts are
     * used up the guest gets a clear "try again" validation error instead
     * of a raw 500 page. Nothing is left behind: a failed attempt is rolled
     * back completely (no partial booking, room line or payment row).
     *
     * A retry is only possible at the outermost transaction level — after a
     * deadlock MySQL has already rolled back the enclosing transaction, so a
     * nested call reports the friendly error immediately.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws ValidationException when the rooms stay contended
     */
    public function inventoryTransaction(Closure $callback, string $errorField = 'check_in'): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            $outermost = DB::transactionLevel() === static::$baseTransactionLevel;

            try {
                return DB::transaction($callback);
            } catch (Throwable $e) {
                // QueryException at the outermost level; Laravel rethrows a
                // DeadlockException from a nested transaction. Anything that
                // isn't a lock conflict is not ours to handle.
                if (! $this->causedByConcurrencyError($e)) {
                    throw $e;
                }

                Log::warning('Booking transaction hit a lock conflict.', [
                    'attempt' => $attempt,
                    'retrying' => $outermost && $attempt < self::LOCK_ATTEMPTS,
                ]);

                if (! $outermost || $attempt >= self::LOCK_ATTEMPTS) {
                    throw ValidationException::withMessages([
                        $errorField => 'Another guest was booking the same room at the same moment. Please try again — if the room is still free your booking will go through.',
                    ]);
                }

                usleep(random_int(20_000, 120_000));
            }
        }
    }

    /**
     * The transaction depth at which inventoryTransaction() is "outermost".
     * Always 0 in the application; the test suite raises it to 1 when each
     * test runs inside its own wrapping transaction (RefreshDatabase).
     */
    public static int $baseTransactionLevel = 0;

    /**
     * Lock the given rooms' rows (SELECT … FOR UPDATE) in ascending id order.
     * Must be the FIRST lock an inventory transaction takes, so every
     * booking/confirmation/reassignment of a room queues behind the same
     * row lock in the same order — which both prevents double booking and
     * keeps deadlocks between those transactions rare.
     *
     * Must be called inside a transaction (see inventoryTransaction()).
     *
     * @param  iterable<int|string>  $roomIds
     */
    public function lockRooms(iterable $roomIds): void
    {
        $ids = collect($roomIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

        if ($ids !== []) {
            Room::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('id');
        }
    }

    /**
     * Rooms of $booking that some OTHER booking currently holds for an
     * overlapping stay (confirmed, checked in, a fresh pending hold, or an
     * active bank-transfer hold). Call after lockRooms() for a reliable answer.
     *
     * @return list<int>
     */
    public function conflictingRoomIds(Booking $booking): array
    {
        return collect($booking->roomIds())
            ->filter(fn (int $roomId) => Booking::query()
                ->overlapping($roomId, $booking->check_in->toDateString(), $booking->check_out->toDateString())
                ->whereKeyNot($booking->id)
                ->lockForUpdate()
                ->exists())
            ->values()
            ->all();
    }

    /**
     * Put a payment on the staff review queue and tell staff — once per
     * flag, however many times reconciliation sees the same situation.
     */
    public function flagPaymentForReview(Payment $payment, string $reason): void
    {
        if (! $payment->flagForReview($reason)) {
            return;
        }

        Log::warning('Payment flagged for staff review.', [
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'reason' => $reason,
        ]);

        $this->auditLog->log('payment.review_flagged', 'booking', $payment->booking_id, $reason, [
            'payment_id' => $payment->id,
            'provider' => $payment->provider,
            'amount' => (string) $payment->amount,
            'payment_status' => $payment->status,
        ]);

        if ($adminEmail = $this->settingsService->current()->site_email) {
            Mail::to($adminEmail)->send(new PaymentNeedsReviewMail($payment));
        }
    }

    /**
     * The hotel's minimum cancellation-notice policy, in hours, as configured
     * in Settings. Hotel policy is free cancellation before arrival, so the
     * fallback (no settings row yet) is 0: guests may cancel online until
     * the end of the check-in day (Booking::isCancellable()).
     */
    public function cancellationWindowHours(): int
    {
        return (int) ($this->settingsService->current()->cancellation_window_hours ?? 0);
    }

    /**
     * First-booking discount (%), from Settings. 0 = no discount.
     */
    public function firstBookingDiscountPercent(): float
    {
        return (float) ($this->settingsService->current()->first_booking_discount_percent ?? 0);
    }

    /**
     * Share of the booking total (%) the hotel keeps when a booking is
     * cancelled BEFORE arrival, from Settings. Hotel policy: 0 — cancellation
     * before arrival is free. (After check-in a fixed charge applies instead:
     * config bookings.after_arrival_cancellation_percent, frozen on the
     * booking by cancelAfterArrival().) Refunds themselves are made manually.
     */
    public function cancellationDeductionPercent(): int
    {
        return (int) ($this->settingsService->current()->cancellation_deduction_percent ?? 0);
    }

    /**
     * A first-time guest: no earlier real booking under this email, phone
     * number or account. Bookings that were abandoned before any payment
     * (auto-cancelled or cancelled unpaid) don't count — they never stayed.
     *
     * Emails aren't verified, so email alone let a returning guest claim
     * the discount again with a fresh address; the phone (stored in E.164
     * since the booking form normalizes it) closes the easy path without
     * collecting anything new. It is not watertight — a guest can still
     * use another phone too — see the client-decision notes.
     */
    public function isFirstBooking(string $email, ?int $userId = null, ?string $phone = null): bool
    {
        return ! Booking::query()
            ->where(function ($q) use ($email, $userId, $phone) {
                $q->whereRaw('LOWER(email) = ?', [strtolower(trim($email))]);
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
                if (filled($phone)) {
                    $q->orWhere('phone', $phone);
                }
            })
            ->where(function ($q) {
                $q->where('status', '!=', Booking::STATUS_CANCELLED)
                    ->orWhereHas('payments', fn ($p) => $p->where('status', Payment::STATUS_COMPLETED));
            })
            ->exists();
    }

    /**
     * Abuse guard for the public booking form: one guest — matched by email,
     * phone OR account, so changing one of them isn't enough — may have at
     * most config('bookings.max_unpaid_held_rooms_per_guest') ROOMS held by
     * unpaid bookings at once (within the short hold, or an active
     * bank-transfer hold), including the rooms now being requested. The
     * default equals the largest single booking (StoreBookingRequest::
     * MAX_ROOMS), so a family or group can always make one booking of any
     * allowed size — but nobody can stack unpaid holds over the hotel's
     * inventory. Paid, expired and cancelled bookings don't count.
     *
     * @throws ValidationException when the guest would hold too many rooms
     */
    public function assertWithinUnpaidHoldLimit(string $email, ?string $phone, ?int $userId, int $requestedRooms = 1): void
    {
        $limit = (int) config('bookings.max_unpaid_held_rooms_per_guest', 5);

        if ($limit <= 0) {
            return;
        }

        $held = Booking::query()
            ->withCount('additionalRooms')
            ->where('status', Booking::STATUS_PENDING)
            ->where(function ($q) use ($email, $phone, $userId) {
                $q->whereRaw('LOWER(email) = ?', [strtolower(trim($email))]);
                if (filled($phone)) {
                    $q->orWhere('phone', $phone);
                }
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
            })
            ->where(fn ($q) => $q->where('created_at', '>=', now()->subMinutes(Booking::PENDING_HOLD_MINUTES))
                ->orWhereHas('payments', fn ($p) => $p->activeOfflineHold()))
            ->whereDoesntHave('payments', fn ($p) => $p->where('status', Payment::STATUS_COMPLETED))
            ->get();

        $heldRooms = $held->sum(fn (Booking $booking) => 1 + $booking->additional_rooms_count);

        if ($held->isNotEmpty() && $heldRooms + $requestedRooms > $limit) {
            throw ValidationException::withMessages([
                'email' => 'You already have '.$held->count().' unpaid '.Str::plural('booking', $held->count())." holding {$heldRooms} ".Str::plural('room', $heldRooms).'. Please complete payment for '.($held->count() === 1 ? 'it' : 'them').' (see the link in your email), or wait for the hold to expire, before booking more rooms. Need help with a larger group? Please contact the hotel.',
            ]);
        }
    }

    /**
     * Why a guest may not start an ONLINE payment for this booking right
     * now, or null if they may. A booking whose short hold has lapsed and
     * whose room has since been taken by someone else can't be paid for
     * (the guest is asked to book again) — no money is taken for a room we
     * could not confirm. If the room is still free the payment may go ahead;
     * confirmAfterVerifiedPayment() re-checks under a room lock anyway.
     */
    public function onlinePaymentBlocker(Booking $booking): ?string
    {
        if ($booking->status === Booking::STATUS_PENDING
            && ! $booking->holdsRoomsNow()
            && ! $booking->payments()->activeOfflineHold()->exists()
            && $this->conflictingRoomIds($booking->loadMissing('additionalRooms')) !== []) {
            return 'Sorry — the hold on this booking has expired and the room has since been booked by another guest. Please make a new booking.';
        }

        return null;
    }

    /**
     * Determine whether a room is free for the given date range.
     * Only pending/confirmed bookings block availability.
     */
    public function isAvailable(int $roomId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): bool
    {
        $query = Booking::query()->overlapping($roomId, $checkIn, $checkOut);

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return ! $query->exists();
    }


    /**
     * Create a booking with server-side price calculation and a
     * transaction-safe availability re-check to prevent race conditions.
     *
     * $room is the primary room (bookings.room_id, with $data['guests_label']).
     * $additionalRooms makes it a multi-room booking: a list of
     * ['room' => Room, 'guests_label' => string], each stored as a
     * BookingRoom. Every room is checked and locked in the same transaction,
     * so either all rooms are booked or none is.
     *
     * @param  list<array{room: Room, guests_label: string}>  $additionalRooms
     *
     * @throws ValidationException if a room is unpriced, too small, or no longer available
     */
    public function create(Room $room, array $data, array $additionalRooms = []): Booking
    {
        $lines = array_merge([['room' => $room, 'guests_label' => $data['guests_label']]], array_values($additionalRooms));
        $this->guardRoomLines($lines);

        return $this->inventoryTransaction(function () use ($lines, $data) {
            // Lock the ROOM rows first (sorted, see lockRooms()): a concurrent
            // booking of any of these rooms now waits here until we commit,
            // even when no booking for the room exists yet. Only then is the
            // final availability check made — inside this transaction, with
            // locking reads that always see the latest committed bookings.
            $this->lockRooms(array_map(fn ($line) => $line['room']->id, $lines));

            foreach (collect($lines)->sortBy(fn ($line) => $line['room']->id) as $index => $line) {
                $hasOverlap = Booking::query()
                    ->overlapping($line['room']->id, $data['check_in'], $data['check_out'])
                    ->lockForUpdate()
                    ->exists();

                if ($hasOverlap) {
                    throw ValidationException::withMessages($index === 0
                        ? ['check_in' => 'This room is no longer available for the selected dates.']
                        : ['additional_rooms' => $line['room']->label().' is no longer available for the selected dates.']);
                }
            }

            $checkIn = Carbon::parse($data['check_in']);
            $checkOut = Carbon::parse($data['check_out']);
            $nights = $checkIn->diffInDays($checkOut);

            // Base rate per room: per-room rate, or rate × guests for a room
            // priced per person. Weekend/season/holiday rules are applied per
            // night and the nightly rates frozen on the booking (price_breakdown).
            $priced = array_map(function (array $line) use ($checkIn, $checkOut) {
                $rate = $line['room']->nightlyRateFor($line['guests_label']);

                return $line + [
                    'nightly_rate' => $rate,
                    'quote' => $this->pricing->quote($line['room'], $line['guests_label'], $checkIn->toDateString(), $checkOut->toDateString(), $rate),
                ];
            }, $lines);

            $subtotal = round(array_sum(array_map(fn ($line) => $line['quote']['subtotal'], $priced)), 2);

            // First-time guests get the hotel's first-booking discount.
            $discountPercent = $this->isFirstBooking($data['email'], $data['user_id'] ?? null, $data['phone'] ?? null)
                ? $this->firstBookingDiscountPercent()
                : 0.0;
            $discount = round($subtotal * $discountPercent / 100, 2);
            $total = round($subtotal - $discount, 2);

            $primary = $priced[0];

            $booking = Booking::create([
                'confirmation_code' => $this->generateConfirmationCode(),
                'user_id' => $data['user_id'] ?? null,
                'room_id' => $primary['room']->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'nights' => $nights,
                'guests_label' => $primary['guests_label'],
                'customer_name' => $data['customer_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'country' => $data['country'] ?? null,
                'special_requests' => $data['special_requests'] ?? null,
                'nightly_rate' => $primary['nightly_rate'],
                'price_breakdown' => $primary['quote']['varies'] ? $primary['quote']['nightly'] : null,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discount,
                'total_price' => $total,
                'status' => Booking::STATUS_PENDING,
            ]);

            foreach (array_slice($priced, 1) as $line) {
                $booking->additionalRooms()->create([
                    'room_id' => $line['room']->id,
                    'guests_label' => $line['guests_label'],
                    'nightly_rate' => $line['nightly_rate'],
                    'price_breakdown' => $line['quote']['varies'] ? $line['quote']['nightly'] : null,
                    'subtotal' => $line['quote']['subtotal'],
                ]);
            }

            return $booking;
        });
    }

    /**
     * Every room must be priced and hold its party; additional rooms must be
     * distinct, bookable rooms. Errors for the primary room keep their
     * original field keys (room_id / guests_label).
     *
     * @param  list<array{room: Room, guests_label: string}>  $lines
     */
    protected function guardRoomLines(array $lines): void
    {
        $ids = array_map(fn ($line) => $line['room']->id, $lines);

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['additional_rooms' => 'Each room can only be added to a booking once.']);
        }

        foreach ($lines as $index => $line) {
            /** @var Room $lineRoom */
            $lineRoom = $line['room'];

            // A room listed before its rate is confirmed cannot be charged for.
            if (! $lineRoom->hasPrice()) {
                throw ValidationException::withMessages($index === 0
                    ? ['room_id' => 'This room is not open for booking yet. Please contact the hotel.']
                    : ['additional_rooms' => $lineRoom->label().' is not open for booking yet.']);
            }

            if ($index > 0 && ! $lineRoom->isBookable()) {
                throw ValidationException::withMessages(['additional_rooms' => $lineRoom->label().' is not currently available for booking.']);
            }

            if ($error = $lineRoom->partySizeErrorFor($line['guests_label'])) {
                throw ValidationException::withMessages($index === 0
                    ? ['guests_label' => $error]
                    : ['additional_rooms' => $lineRoom->label().': '.$error]);
            }
        }
    }

    /**
     * Public booking of a room TYPE: try the chosen room first, then any other
     * bookable room of the same type (Room::scopeSameTypeAs), so a guest who
     * picks "Single Room (VIP)" gets whichever VIP room is free. Each attempt
     * is a full create() — same row locks and availability re-check.
     *
     * With $roomsCount > 1 the guest books that many rooms of the type in one
     * multi-room booking, each for the same party ($data['guests_label'] is
     * per room).
     *
     * @throws ValidationException if not enough rooms of this type are free
     */
    public function createForRoomType(Room $room, array $data, int $roomsCount = 1): Booking
    {
        $candidates = collect([$room])->merge(
            Room::bookable()->sameTypeAs($room)->orderBy('id')->get()
        );

        if ($roomsCount > 1) {
            $free = $candidates
                ->filter(fn (Room $candidate) => $candidate->isBookable() && $this->isAvailable($candidate->id, $data['check_in'], $data['check_out']))
                ->values();

            if ($free->count() < $roomsCount) {
                throw ValidationException::withMessages([
                    'rooms_count' => $free->isEmpty()
                        ? 'No rooms of this type are available for the selected dates.'
                        : "Only {$free->count()} room(s) of this type are available for the selected dates.",
                ]);
            }

            $chosen = $free->take($roomsCount);

            return $this->create($chosen->first(), $data, $chosen->slice(1)
                ->map(fn (Room $extra) => ['room' => $extra, 'guests_label' => $data['guests_label']])
                ->values()->all());
        }

        foreach ($candidates as $candidate) {
            try {
                return $this->create($candidate, $data);
            } catch (ValidationException $e) {
                $lastError = $e;
            }
        }

        throw $lastError;
    }


    /**
     * How many bookable rooms of $room's type (including $room) are free.
     */
    public function freeRoomsOfType(Room $room, string $checkIn, string $checkOut): int
    {
        return collect([$room])->merge(Room::bookable()->sameTypeAs($room)->get())
            ->filter(fn (Room $candidate) => $candidate->isBookable() && $this->isAvailable($candidate->id, $checkIn, $checkOut))
            ->count();
    }

    /**
     * Whether $room or any other bookable room of the same type is free.
     */
    public function isTypeAvailable(Room $room, string $checkIn, string $checkOut): bool
    {
        return ($room->isBookable() && $this->isAvailable($room->id, $checkIn, $checkOut))
            || Room::bookable()->sameTypeAs($room)->get()
                ->contains(fn (Room $sibling) => $this->isAvailable($sibling->id, $checkIn, $checkOut));
    }

    public function sendNotifications(Booking $booking, ?string $adminEmail): void
    {
        Mail::to($booking->email)->send(new BookingConfirmationMail($booking));

        if ($adminEmail) {
            Mail::to($adminEmail)->send(new NewBookingNotificationMail($booking));
        }
    }

    /**
     * Cancel a booking on the guest's behalf.
     *
     * This only ever changes the booking's own status — it never touches a
     * Payment record. There is no refund integration with eSewa, so a
     * booking that was already paid for stays recorded as paid; refunding
     * captured money (if any) is a manual staff process outside this
     * application. See EsewaPaymentService for the payment flow this
     * intentionally does not reach into.
     *
     * @throws ValidationException if the booking is not currently cancellable
     */
    public function cancel(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            /** @var Booking $booking */
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->status === Booking::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'booking' => 'This booking has already been cancelled.',
                ]);
            }

            $windowHours = $this->cancellationWindowHours();

            if (! $booking->isCancellable($windowHours)) {
                throw ValidationException::withMessages([
                    'booking' => $booking->isCancellable(0)
                        ? "This booking must be cancelled at least {$windowHours} hours before check-in. Please contact the hotel directly."
                        : 'This booking can no longer be cancelled online because its check-in date has passed. Please contact the hotel directly.',
                ]);
            }

            $booking->status = Booking::STATUS_CANCELLED;
            $booking->cancelled_at = now();
            $booking->save();

            return $booking;
        });
    }

    /**
     * The guest has already arrived (checked in) and cancels the rest of the
     * stay. Hotel policy: a fixed cancellation charge
     * (config bookings.after_arrival_cancellation_percent, 50%) of the booking
     * amount applies, frozen on the booking so later config changes never
     * alter it; extra charges already incurred stay payable. The rooms are
     * released. Pre-arrival cancellation (cancel()) is unchanged.
     *
     * @throws ValidationException if the guest is not currently checked in
     */
    public function cancelAfterArrival(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== Booking::STATUS_CHECKED_IN || ! $booking->canTransitionTo(Booking::STATUS_CANCELLED)) {
                throw ValidationException::withMessages([
                    'booking' => 'Only a checked-in stay can be cancelled after arrival. Use the normal cancellation for bookings before arrival.',
                ]);
            }

            $booking->status = Booking::STATUS_CANCELLED;
            $booking->cancelled_at = now();
            $booking->cancellation_charge_percent = (int) config('bookings.after_arrival_cancellation_percent', 50);
            $booking->save();

            return $booking;
        });
    }

    public function sendCancellationNotifications(Booking $booking, ?string $adminEmail): void
    {
        Mail::to($booking->email)->send(new BookingCancelledMail($booking));

        if ($adminEmail) {
            Mail::to($adminEmail)->send(new BookingCancelledAdminMail($booking));
        }
    }

    /**
     * Confirm a booking now that its payment has been independently verified
     * — but only if the room is still actually available for those dates.
     * Shared by every payment gateway's callback handling (eSewa, and any
     * future real gateway): a gateway service verifies the payment itself
     * is genuine, then hands off here to decide the booking's fate.
     *
     * It is possible (though rare, given the booking-creation-time lock)
     * for an abandoned pending booking to age out of its availability hold
     * and lose the room to another guest before its payment is eventually
     * completed. In that case we do NOT double-book the room: the payment
     * stays completed (the money was captured) but the booking stays
     * pending, flagged for manual staff resolution (refund or reassignment).
     */
    public function confirmAfterVerifiedPayment(Payment $payment): bool
    {
        return $this->inventoryTransaction(function () use ($payment) {
            $unlocked = Booking::with('additionalRooms')->find($payment->booking_id);

            if (! $unlocked) {
                return false;
            }

            // Same lock order as booking creation: rooms first, then the booking.
            $this->lockRooms($unlocked->roomIds());
            $booking = Booking::with('additionalRooms')->whereKey($unlocked->id)->lockForUpdate()->first();

            if ($booking->status !== Booking::STATUS_PENDING) {
                // Late payment rule: a booking that was cancelled (expired
                // hold, guest or staff cancellation) is NEVER re-confirmed
                // automatically — its room may have been sold again. Staff
                // decide: re-confirm (the status override re-checks the
                // room) or refund.
                if ($booking->status === Booking::STATUS_CANCELLED && ! $booking->wasCancelledAfterArrival()) {
                    $this->flagPaymentForReview($payment, 'Payment received after the booking was cancelled or its hold expired. The booking was NOT re-confirmed automatically: re-confirm it if the room is still free, otherwise refund the guest.');
                } else {
                    $this->flagIfOverpaid($payment, $booking);
                }

                return $booking->status === Booking::STATUS_CONFIRMED;
            }

            // Every room must be free of any OTHER booking that holds it —
            // confirmed/checked-in stays, but also another guest's fresh
            // pending hold: a guest whose own hold lapsed and who pays late
            // must not take the room from the guest who holds it now.
            if ($this->conflictingRoomIds($booking) !== []) {
                $this->flagPaymentForReview($payment, 'Payment received, but the room is no longer available for these dates (the hold had lapsed and the room was taken). The booking was NOT confirmed: offer another room or refund the guest.');

                return false;
            }

            $booking->status = Booking::STATUS_CONFIRMED;
            $booking->save();

            $this->flagIfOverpaid($payment, $booking);

            return true;
        });
    }

    /**
     * More money collected than the booking costs — e.g. the guest paid in
     * two browser tabs. Refunds are manual, so staff are asked to review.
     */
    protected function flagIfOverpaid(Payment $payment, Booking $booking): void
    {
        if ($booking->outstandingBalance() < -0.009) {
            $this->flagPaymentForReview($payment, 'This booking has been paid more than once (overpaid by '.npr(-$booking->outstandingBalance()).'). Refund the extra amount to the guest.');
        }
    }

    /**
     * Front-desk check-in. Only a confirmed booking may check in — this is
     * the same rule Booking::TRANSITIONS encodes, enforced here with a row
     * lock so a double-click can't check the same booking in twice.
     *
     * @throws ValidationException if the booking is not currently confirmed
     */
    public function checkIn(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $booking->canTransitionTo(Booking::STATUS_CHECKED_IN)) {
                throw ValidationException::withMessages([
                    'booking' => 'Only a confirmed booking can be checked in.',
                ]);
            }

            $booking->status = Booking::STATUS_CHECKED_IN;
            $booking->checked_in_at = now();
            $booking->save();

            return $booking;
        });
    }

    /**
     * Front-desk check-out. Only a checked-in booking may check out.
     *
     * @throws ValidationException if the booking is not currently checked in
     */
    public function checkOut(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $booking->canTransitionTo(Booking::STATUS_CHECKED_OUT)) {
                throw ValidationException::withMessages([
                    'booking' => 'Only a checked-in booking can be checked out.',
                ]);
            }

            $booking->status = Booking::STATUS_CHECKED_OUT;
            $booking->checked_out_at = now();
            $booking->save();

            return $booking;
        });
    }

    /**
     * Mark a confirmed booking as a no-show (guest never arrived). Only
     * reachable from confirmed — a booking that already checked in is by
     * definition not a no-show.
     *
     * @throws ValidationException if the booking is not currently confirmed
     */
    public function markNoShow(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $booking->canTransitionTo(Booking::STATUS_NO_SHOW)) {
                throw ValidationException::withMessages([
                    'booking' => 'Only a confirmed booking can be marked as a no-show.',
                ]);
            }

            $booking->status = Booking::STATUS_NO_SHOW;
            $booking->save();

            return $booking;
        });
    }

    /**
     * Close out a checked-out stay as completed (folio settled, nothing
     * further to do). Only reachable from checked_out.
     *
     * @throws ValidationException if the booking is not currently checked out
     */
    public function markCompleted(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $booking->canTransitionTo(Booking::STATUS_COMPLETED)) {
                throw ValidationException::withMessages([
                    'booking' => 'Only a checked-out booking can be marked completed.',
                ]);
            }

            $booking->status = Booking::STATUS_COMPLETED;
            $booking->save();

            return $booking;
        });
    }

    /**
     * Staff status override (pending / confirmed / cancelled — including
     * reversing an accidental cancellation). Moving a booking to pending or
     * confirmed puts it back on the room, so the room rows are locked and
     * availability is re-checked inside the transaction, excluding the
     * booking itself: if another booking holds any of its rooms for
     * overlapping dates the change is refused and the booking keeps its
     * current status. Every entry point (admin UI, any future API) must go
     * through here — see also Booking's own model-level guard.
     *
     * @return array{0: Booking, 1: string} the booking and its previous status
     *
     * @throws ValidationException if the change is not allowed or would double-book
     */
    public function changeStatusByStaff(Booking $booking, string $status): array
    {
        if (! in_array($status, self::STAFF_OVERRIDABLE_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'That status cannot be set here.']);
        }

        return $this->inventoryTransaction(function () use ($booking, $status) {
            $this->lockRooms(Booking::with('additionalRooms')->findOrFail($booking->id)->roomIds());
            $booking = Booking::with('additionalRooms')->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $previous = $booking->status;

            if (! in_array($previous, self::STAFF_OVERRIDABLE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => 'This booking is already '.str_replace('_', ' ', $previous).
                        ' and can no longer be changed here. Use the check-in/check-out/no-show actions instead.',
                ]);
            }

            if ($status !== $previous && $status !== Booking::STATUS_CANCELLED && ($conflicts = $this->conflictingRoomIds($booking)) !== []) {
                $names = Room::whereIn('id', $conflicts)->get()->map->label()->implode(', ');

                throw ValidationException::withMessages([
                    'status' => "Cannot mark this booking as {$status}: {$names} is already held by another booking for overlapping dates. Reassign the room or change the dates first.",
                ]);
            }

            $booking->status = $status;
            // Keep cancelled_at consistent with status whichever side changes it.
            $booking->cancelled_at = $status === Booking::STATUS_CANCELLED ? ($booking->cancelled_at ?? now()) : null;
            $booking->save();

            return [$booking, $previous];
        }, 'status');
    }

    /**
     * Record a payment collected outside any online gateway (cash at the
     * front desk today; any other offline method later reuses this same
     * path). Deliberately mirrors every online gateway's own rule: the
     * amount is always the booking's own server-calculated outstanding balance,
     * never an arbitrary staff-entered figure, and confirmation goes
     * through the exact same confirmAfterVerifiedPayment() availability
     * re-check a verified eSewa/Khalti payment uses — a cash booking is
     * confirmed through an identical path, not a special case.
     *
     * @throws ValidationException if the booking is cancelled or already paid
     */
    public function recordManualPayment(Booking $booking, string $provider = 'cash', ?string $reference = null, ?float $amount = null): Payment
    {
        return $this->inventoryTransaction(function () use ($booking, $provider, $reference, $amount) {
            $this->lockRooms(Booking::with('additionalRooms')->findOrFail($booking->id)->roomIds());
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            // A stay cancelled after arrival can still settle what it owes
            // (the cancellation charge plus extras); other cancellations can't.
            if ($booking->status === Booking::STATUS_CANCELLED && ! $booking->wasCancelledAfterArrival()) {
                throw ValidationException::withMessages([
                    'payment' => 'This booking is cancelled and cannot be marked as paid.',
                ]);
            }

            // Part-paid bookings (e.g. 50% paid online, 50% due at the hotel)
            // can still take the remaining balance here.
            $balance = $booking->outstandingBalance();

            if ($balance <= 0) {
                throw ValidationException::withMessages([
                    'payment' => 'This booking has already been paid for.',
                ]);
            }

            // In person (cash / QR at the desk) staff record the ACTUAL amount
            // received — part by QR, part in cash, one entry each — never
            // more than is owed. A bank transfer is confirmed for the amount
            // the guest was asked to send; a typed amount is not used there.
            if ($provider === 'bank_transfer') {
                $amount = null;
            }

            if ($amount !== null) {
                $amount = round($amount, 2);

                if ($amount <= 0 || $amount > $balance + 0.004) {
                    throw ValidationException::withMessages([
                        'amount' => 'The amount must be more than zero and no more than the outstanding balance ('.npr($balance).').',
                    ]);
                }
            }

            // A guest who chose Bank Transfer or Cash at checkout already has a
            // pending payment for this booking: staff confirming receipt
            // completes that same row (for the amount the guest was asked to
            // pay, e.g. a 50% deposit) rather than adding a second one.
            $payment = array_key_exists($provider, Payment::offlineHoldHours())
                ? Payment::query()->where('booking_id', $booking->id)->where('provider', $provider)
                    ->where('status', Payment::STATUS_PENDING)->latest('id')->lockForUpdate()->first()
                : null;

            if ($payment) {
                $payment->update([
                    'reference_id' => $reference,
                    'amount' => $amount ?? min((float) $payment->amount ?: $balance, $balance),
                    'status' => Payment::STATUS_COMPLETED,
                    'paid_at' => now(),
                ]);
            } else {
                $payment = Payment::create([
                    'booking_id' => $booking->id,
                    'provider' => $provider,
                    'transaction_uuid' => $booking->confirmation_code.'-'.strtoupper($provider).'-'.Str::upper(Str::random(6)),
                    'reference_id' => $reference,
                    'amount' => $amount ?? $balance,
                    'currency' => 'NPR',
                    'status' => Payment::STATUS_COMPLETED,
                    'paid_at' => now(),
                ]);
            }

            $this->confirmAfterVerifiedPayment($payment);

            return $payment;
        });
    }

    /**
     * Cancel a single pending booking that has aged past its availability
     * hold window (Booking::PENDING_HOLD_MINUTES) and was never paid.
     * Re-checks status under a row lock immediately before cancelling so a
     * payment verification that is mid-flight for this exact booking (see
     * confirmAfterVerifiedPayment) can never be raced — if it already
     * confirmed by the time this acquires the lock, it is left alone.
     *
     * Deliberately refuses to touch a pending booking that already has a
     * completed payment — the rare case where money was captured but the
     * room lost its hold before confirmAfterVerifiedPayment() could
     * confirm it (see that method's own docblock). That booking needs a
     * human, not an automatic cancellation that would bury a paid guest.
     *
     * @return bool whether the booking was actually expired
     */
    public function expireIfAbandoned(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if (! $booking || $booking->status !== Booking::STATUS_PENDING) {
                return false;
            }

            if ($booking->created_at->gt(now()->subMinutes(Booking::PENDING_HOLD_MINUTES))) {
                return false;
            }

            // Awaiting a bank transfer / cash confirmation: held for the (longer) offline window.
            if ($booking->payments()->activeOfflineHold()->exists()) {
                return false;
            }

            if ($booking->isPaid()) {
                Log::warning('Pending booking has a completed payment but was not auto-expired — requires manual staff resolution.', [
                    'booking_id' => $booking->id,
                ]);

                return false;
            }

            $booking->status = Booking::STATUS_CANCELLED;
            $booking->cancelled_at = now();
            $booking->save();

            return true;
        });
    }

    /**
     * Reassign an existing booking to a different physical room, without
     * touching its price. Re-checks the new room's availability for the
     * booking's exact dates under a row lock, exactly like Booking creation
     * does — two staff members reassigning the same room to conflicting
     * bookings at once cannot both succeed.
     *
     * On a multi-room booking, pass $line to move one of the additional rooms
     * instead of the primary room; the party size checked is that room's own.
     *
     * @throws ValidationException if the booking cannot be reassigned right now,
     *                              or the target room isn't actually available
     */
    public function reassignRoom(Booking $booking, Room $newRoom, ?BookingRoom $line = null): Booking
    {
        return $this->inventoryTransaction(function () use ($booking, $newRoom, $line) {
            $this->lockRooms(array_merge(Booking::with('additionalRooms')->findOrFail($booking->id)->roomIds(), [$newRoom->id]));
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($line && $line->booking_id !== $booking->id) {
                throw ValidationException::withMessages(['room' => 'That room is not part of this booking.']);
            }

            if (! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN], true)) {
                throw ValidationException::withMessages([
                    'room' => 'Only a pending, confirmed, or checked-in booking can be reassigned to a different room.',
                ]);
            }

            if ($newRoom->id === ($line ? $line->room_id : $booking->room_id)) {
                throw ValidationException::withMessages([
                    'room' => 'This booking is already assigned to that room.',
                ]);
            }

            if (in_array($newRoom->id, $booking->roomIds(), true)) {
                throw ValidationException::withMessages([
                    'room' => 'That room is already part of this booking.',
                ]);
            }

            $partyLabel = $line ? $line->guests_label : $booking->guests_label;

            if (! $newRoom->isBookable()) {
                throw ValidationException::withMessages([
                    'room' => 'The selected room is not currently active/available and cannot receive a reassigned booking.',
                ]);
            }

            if ($newRoom->exceedsCapacityFor($partyLabel)) {
                throw ValidationException::withMessages([
                    'room' => "The selected room accommodates a maximum of {$newRoom->max_guests} guest(s), which is fewer than this booking's party size.",
                ]);
            }

            if ($error = $newRoom->partySizeErrorFor($partyLabel)) {
                throw ValidationException::withMessages(['room' => $error]);
            }

            $hasOverlap = Booking::query()
                ->overlapping($newRoom->id, $booking->check_in->toDateString(), $booking->check_out->toDateString())
                ->where('id', '!=', $booking->id)
                ->lockForUpdate()
                ->exists();

            if ($hasOverlap) {
                throw ValidationException::withMessages([
                    'room' => 'The selected room is no longer available for these dates.',
                ]);
            }

            if ($line) {
                $line->update(['room_id' => $newRoom->id]);
            } else {
                $booking->room_id = $newRoom->id;
                $booking->save();
            }

            return $booking;
        });
    }

    /**
     * Modify an existing booking's dates and/or guest information.
     * Deliberately does not touch room_id — see reassignRoom() for that.
     * Price is always recalculated server-side from the booking's own
     * (unchanged) nightly_rate × the new number of nights — never trusts
     * a client-submitted total. Existing payment records are never
     * touched here; a modification that raises or lowers the total simply
     * changes Booking::outstandingBalance()'s result, surfaced to staff —
     * no refund is ever invented automatically.
     *
     * @param  array{check_in:string,check_out:string,guests_label:string,customer_name:string,email:string,phone:string,country?:?string,special_requests?:?string}  $data
     *
     * @throws ValidationException if the booking cannot be modified right now,
     *                              the new dates are unavailable, or the new
     *                              guest count exceeds the room's capacity
     */
    public function modify(Booking $booking, array $data): Booking
    {
        return $this->inventoryTransaction(function () use ($booking, $data) {
            $this->lockRooms(Booking::with('additionalRooms')->findOrFail($booking->id)->roomIds());
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED], true)) {
                throw ValidationException::withMessages([
                    'booking' => 'Only a pending or confirmed booking can be modified.',
                ]);
            }

            $room = $booking->room()->firstOrFail();

            if ($room->exceedsCapacityFor($data['guests_label'])) {
                throw ValidationException::withMessages([
                    'guests_label' => "This room accommodates a maximum of {$room->max_guests} guest(s). Please choose a smaller party size or reassign the room first.",
                ]);
            }

            if ($error = $room->partySizeErrorFor($data['guests_label'])) {
                throw ValidationException::withMessages(['guests_label' => $error]);
            }

            // A room priced per person re-prices when the party size changes;
            // a per-room rate stays whatever was agreed at booking time.
            $previousRate = (float) $booking->nightly_rate;
            if ($room->price_per_person && ($rate = $room->nightlyRateFor($data['guests_label'])) !== null) {
                $booking->nightly_rate = $rate;
            }
            $rateChanged = round((float) $booking->nightly_rate, 2) !== round($previousRate, 2);

            $checkIn = Carbon::parse($data['check_in']);
            $checkOut = Carbon::parse($data['check_out']);

            $datesChanged = $checkIn->toDateString() !== $booking->check_in->toDateString()
                || $checkOut->toDateString() !== $booking->check_out->toDateString();

            if ($datesChanged) {
                // Every room of a multi-room booking must be free for the new dates.
                foreach ($booking->roomIds() as $roomId) {
                    $hasOverlap = Booking::query()
                        ->overlapping($roomId, $checkIn->toDateString(), $checkOut->toDateString())
                        ->where('id', '!=', $booking->id)
                        ->lockForUpdate()
                        ->exists();

                    if ($hasOverlap) {
                        throw ValidationException::withMessages([
                            'check_in' => $roomId === (int) $booking->room_id
                                ? 'This room is not available for the new dates.'
                                : 'One of the other rooms on this booking is not available for the new dates.',
                        ]);
                    }
                }
            }

            $nights = $checkIn->diffInDays($checkOut);

            $booking->check_in = $checkIn->toDateString();
            $booking->check_out = $checkOut->toDateString();
            $booking->nights = $nights;
            // Re-price, keeping the discount the booking was made with.
            if (! $datesChanged && ! $rateChanged) {
                // Same stay, same rate: keep exactly what was agreed. A booking
                // priced with weekend/season/holiday rates keeps its frozen
                // per-night rates even if the rules have changed since.
                $subtotal = $booking->price_breakdown
                    ? round(array_sum(array_column($booking->price_breakdown, 'rate')), 2)
                    : round((float) $booking->nightly_rate * $nights, 2);
            } else {
                // New dates (or a new per-person rate): the agreed base rate,
                // with today's rules for the new nights.
                $quote = $this->pricing->quote($room, $data['guests_label'], $checkIn->toDateString(), $checkOut->toDateString(), (float) $booking->nightly_rate);
                $subtotal = $quote['subtotal'];
                $booking->price_breakdown = $quote['varies'] ? $quote['nightly'] : null;
            }

            // Additional rooms of a multi-room booking: same rules — repriced
            // from their own agreed rate only when the dates change.
            foreach ($booking->additionalRooms()->with('room')->get() as $line) {
                if ($datesChanged) {
                    $lineQuote = $this->pricing->quote($line->room, $line->guests_label, $checkIn->toDateString(), $checkOut->toDateString(), (float) $line->nightly_rate);
                    $line->update([
                        'subtotal' => $lineQuote['subtotal'],
                        'price_breakdown' => $lineQuote['varies'] ? $lineQuote['nightly'] : null,
                    ]);
                }
                $subtotal += (float) $line->subtotal;
            }
            $subtotal = round($subtotal, 2);
            $booking->discount_amount = round($subtotal * (float) $booking->discount_percent / 100, 2);
            $booking->total_price = round($subtotal - (float) $booking->discount_amount, 2);
            $booking->guests_label = $data['guests_label'];
            $booking->customer_name = $data['customer_name'];
            $booking->email = $data['email'];
            $booking->phone = $data['phone'];
            $booking->country = $data['country'] ?? null;
            $booking->special_requests = $data['special_requests'] ?? null;
            $booking->save();

            return $booking;
        });
    }

    /**
     * Add an incidental charge (restaurant, laundry, minibar…) to a stay.
     * The amount is always unit_price × quantity, computed here. Only a
     * checked-in or checked-out booking accepts charges, so cancellation
     * and refund calculations (which only apply before arrival) are never
     * affected. The charge raises outstandingBalance(), which staff then
     * collect through the normal Record Payment action.
     *
     * @param  array{category:string,description:string,unit_price:float|string,quantity:int,notes?:?string,charged_at?:?string}  $data
     *
     * @throws ValidationException if the booking does not accept charges
     */
    public function addCharge(Booking $booking, array $data, ?int $adminId): BookingCharge
    {
        return DB::transaction(function () use ($booking, $data, $adminId) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $booking->acceptsCharges()) {
                throw ValidationException::withMessages([
                    'charge' => 'Charges can only be added to a checked-in or checked-out stay.',
                ]);
            }

            [$unitPrice, $quantity, $amount] = $this->chargeFigures($data['unit_price'], $data['quantity']);

            return $booking->charges()->create([
                'category' => $data['category'],
                'description' => $data['description'],
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'amount' => $amount,
                'charged_at' => $data['charged_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'added_by' => $adminId,
            ]);
        });
    }

    /**
     * Correct a charge entered with the wrong item, price or quantity while
     * the folio is still open. The amount is recomputed here, never taken
     * from input. Voided charges cannot be edited.
     *
     * @param  array{category:string,description:string,unit_price:float|string,quantity:int,notes?:?string}  $data
     *
     * @throws ValidationException
     */
    public function updateCharge(BookingCharge $charge, array $data): BookingCharge
    {
        return DB::transaction(function () use ($charge, $data) {
            $booking = Booking::query()->whereKey($charge->booking_id)->lockForUpdate()->firstOrFail();
            $charge = BookingCharge::query()->whereKey($charge->id)->lockForUpdate()->firstOrFail();

            if ($charge->isVoided()) {
                throw ValidationException::withMessages(['charge' => 'A voided charge cannot be edited.']);
            }

            if (! $booking->acceptsCharges()) {
                throw ValidationException::withMessages(['charge' => 'Charges on a completed or cancelled stay can no longer be changed.']);
            }

            [$unitPrice, $quantity, $amount] = $this->chargeFigures($data['unit_price'], $data['quantity']);

            $charge->update([
                'category' => $data['category'],
                'description' => $data['description'],
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'amount' => $amount,
                'notes' => $data['notes'] ?? null,
            ]);

            return $charge;
        });
    }

    /**
     * Decimal-safe charge maths: work in whole paisa (integers) so that
     * unit price × quantity never picks up floating-point error, then
     * return 2-decimal strings for the decimal(10,2) columns.
     *
     * @return array{0:string, 1:int, 2:string} unit price, quantity, amount
     */
    protected function chargeFigures(float|int|string $unitPrice, int|string $quantity): array
    {
        $unitPaisa = (int) round((float) $unitPrice * 100);
        $quantity = (int) $quantity;
        $amountPaisa = $unitPaisa * $quantity;

        return [
            number_format($unitPaisa / 100, 2, '.', ''),
            $quantity,
            intdiv($amountPaisa, 100).'.'.str_pad((string) ($amountPaisa % 100), 2, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * Void (never delete) a charge entered by mistake. Refused once the
     * booking is completed — a closed folio is final.
     *
     * @throws ValidationException
     */
    public function voidCharge(BookingCharge $charge, string $reason, ?int $adminId): BookingCharge
    {
        return DB::transaction(function () use ($charge, $reason, $adminId) {
            $booking = Booking::query()->whereKey($charge->booking_id)->lockForUpdate()->firstOrFail();
            $charge = BookingCharge::query()->whereKey($charge->id)->lockForUpdate()->firstOrFail();

            if ($charge->isVoided()) {
                throw ValidationException::withMessages(['charge' => 'This charge has already been voided.']);
            }

            if (! $booking->acceptsCharges()) {
                throw ValidationException::withMessages(['charge' => 'Charges on a completed stay can no longer be changed.']);
            }

            $charge->update([
                'voided_at' => now(),
                'voided_by' => $adminId,
                'void_reason' => $reason,
            ]);

            return $charge;
        });
    }

    protected function generateConfirmationCode(): string
    {
        $year = now()->format('Y');

        do {
            $code = sprintf('NW-%s-%s', $year, Str::upper(Str::random(6)));
        } while (Booking::where('confirmation_code', $code)->exists());

        return $code;
    }
}
