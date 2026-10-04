<?php

namespace App\Services\Concerns;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\BookingService;
use App\Services\Payments\HoldExpiredException;
use Illuminate\Support\Str;

/**
 * Shared by the offline methods (Bank Transfer, Cash): choosing one only
 * records the guest's intent as a pending Payment — which also extends the
 * booking's hold (Payment::scopeActiveOfflineHold) from the normal
 * Booking::PENDING_HOLD_MINUTES to the method's hold_hours. Nothing is ever
 * marked paid here; staff complete this same row from the admin through
 * BookingService::recordManualPayment().
 *
 * Abuse rules (a longer hold is a scarce thing to hand out):
 *  - a NEW offline hold can only start while the booking still holds its
 *    rooms (within its short hold, rooms not taken meanwhile) — an expired
 *    booking can never be revived by picking Bank Transfer;
 *  - choosing the same method again reuses the existing row, so the hold
 *    is never extended by re-selecting it;
 *  - switching to a different offline method after the short hold has
 *    lapsed is refused for the same reason.
 * Rooms are locked first (BookingService::lockRooms()), like every other
 * inventory change.
 */
trait RecordsOfflinePaymentIntent
{
    /**
     * Record (or reuse) a pending Payment for $provider on this booking.
     * The amount is the booking's server-calculated amount due now.
     *
     * @return array{payment: Payment, created: bool}
     *
     * @throws HoldExpiredException if the booking no longer holds its rooms
     * @throws \RuntimeException if the booking is already paid
     */
    protected function recordPendingIntent(Booking $booking, string $provider, string $referenceTag): array
    {
        $bookings = app(BookingService::class);

        return $bookings->inventoryTransaction(function () use ($bookings, $booking, $provider, $referenceTag) {
            $bookings->lockRooms(Booking::with('additionalRooms')->findOrFail($booking->id)->roomIds());
            $booking = Booking::with('additionalRooms')->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->isPaid()) {
                throw new \RuntimeException('This booking has already been paid for.');
            }

            $payment = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('provider', $provider)
                ->where('status', Payment::STATUS_PENDING)
                ->latest('id')
                ->first();

            // Re-selecting the same method: same row, same hold — the hold is
            // measured from the row's created_at, which never changes.
            if ($payment) {
                $payment->update(['amount' => $booking->amountDueNow()]);

                return ['payment' => $payment, 'created' => false];
            }

            if (! $booking->holdsRoomsNow() || $bookings->conflictingRoomIds($booking) !== []) {
                throw new HoldExpiredException('This booking\'s room hold has expired.');
            }

            return ['payment' => Payment::create([
                'booking_id' => $booking->id,
                'provider' => $provider,
                'transaction_uuid' => $booking->confirmation_code.'-'.$referenceTag.'-'.Str::upper(Str::random(6)),
                'amount' => $booking->amountDueNow(),
                'currency' => 'NPR',
                'status' => Payment::STATUS_PENDING,
            ]), 'created' => true];
        }, 'payment');
    }
}
