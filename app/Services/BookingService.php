<?php

namespace App\Services;

use App\Mail\BookingCancelledAdminMail;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmationMail;
use App\Mail\NewBookingNotificationMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(protected SettingsService $settingsService) {}

    /**
     * The hotel's minimum cancellation-notice policy, in hours, as configured
     * in Settings → falls back to 24h if no settings row exists yet (e.g. a
     * fresh install before the admin has visited the Settings page).
     */
    public function cancellationWindowHours(): int
    {
        return (int) ($this->settingsService->current()->cancellation_window_hours ?? 24);
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
     * @throws ValidationException if the room becomes unavailable
     */
    public function create(Room $room, array $data): Booking
    {
        return DB::transaction(function () use ($room, $data) {
            // Lock any existing overlapping bookings for this room so a concurrent
            // request cannot slip a conflicting booking in between our check and insert.
            $hasOverlap = Booking::query()
                ->overlapping($room->id, $data['check_in'], $data['check_out'])
                ->lockForUpdate()
                ->exists();

            if ($hasOverlap) {
                throw ValidationException::withMessages([
                    'check_in' => 'This room is no longer available for the selected dates.',
                ]);
            }

            $checkIn = Carbon::parse($data['check_in']);
            $checkOut = Carbon::parse($data['check_out']);
            $nights = $checkIn->diffInDays($checkOut);
            $nightlyRate = $room->price_per_night;
            $total = round($nightlyRate * $nights, 2);

            $booking = Booking::create([
                'confirmation_code' => $this->generateConfirmationCode(),
                'user_id' => $data['user_id'] ?? null,
                'room_id' => $room->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'nights' => $nights,
                'guests_label' => $data['guests_label'],
                'customer_name' => $data['customer_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'country' => $data['country'] ?? null,
                'special_requests' => $data['special_requests'] ?? null,
                'nightly_rate' => $nightlyRate,
                'total_price' => $total,
                'status' => Booking::STATUS_PENDING,
            ]);

            return $booking;
        });
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
                        : 'This booking can no longer be cancelled online because its check-in date has already arrived. Please contact the hotel directly.',
                ]);
            }

            $booking->status = Booking::STATUS_CANCELLED;
            $booking->cancelled_at = now();
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
    public function confirmAfterVerifiedPayment(Payment $payment): void
    {
        $booking = Booking::where('id', $payment->booking_id)->lockForUpdate()->first();

        if (! $booking || $booking->status === Booking::STATUS_CANCELLED) {
            return;
        }

        $stillAvailable = ! Booking::query()
            ->overlapping($booking->room_id, $booking->check_in->toDateString(), $booking->check_out->toDateString())
            ->where('id', '!=', $booking->id)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->exists();

        if ($stillAvailable) {
            $booking->status = Booking::STATUS_CONFIRMED;
            $booking->save();
        } else {
            Log::error('Payment completed but room is no longer available — requires manual staff resolution.', [
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
            ]);
        }
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
