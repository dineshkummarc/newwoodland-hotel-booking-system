<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingService;
use App\Services\SettingsService;
use App\Support\BookingAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Self-service cancellation for a booking made without an account. The
 * link is a Laravel signed URL (HMAC'd with APP_KEY, expiring at the
 * booking's check-in date) — it cannot be forged or guessed from the
 * confirmation code alone, unlike the guest-facing confirmation page URL.
 * Both routes reuse the exact same signed query string (see the "signed"
 * middleware in routes/web.php) and funnel into the SAME BookingService::
 * cancel() the authenticated account-holder flow already uses, so the
 * cancellation window / already-cancelled / past-check-in rules never
 * diverge between the two paths.
 */
class GuestCancellationController extends Controller
{
    public function show(Request $request, Booking $booking)
    {
        // Account bookings are cancelled from My Bookings, never by link.
        abort_unless($booking->isGuestBooking(), 403);

        // The holder of this signed link is the guest; let them see the booking too.
        BookingAccess::grant($request, $booking);

        return view('booking-cancel-guest', compact('booking'));
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookingService, SettingsService $settingsService)
    {
        abort_unless($booking->isGuestBooking(), 403);

        BookingAccess::grant($request, $booking);

        try {
            $booking = $bookingService->cancel($booking);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $bookingService->sendCancellationNotifications($booking, $settingsService->current()->site_email);

        return redirect()
            ->route('booking.confirmation', $booking->confirmation_code)
            ->with('success', 'Your booking '.$booking->confirmation_code.' has been cancelled.');
    }
}
