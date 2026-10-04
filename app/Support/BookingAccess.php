<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Who may see or act on a booking's guest-facing pages (confirmation,
 * payment, bank-transfer details).
 *
 * The confirmation code is a REFERENCE, not a credential: it is short,
 * printed on invoices and read out over the phone, so knowing it grants
 * nothing. Access is granted to:
 *  - the signed-in account that owns the booking;
 *  - the browser session that created the booking, or that came back from
 *    a payment gateway with a high-entropy transaction reference for it
 *    (grant());
 *  - anyone holding a signed, expiring link for that exact page — the
 *    "view your booking" link in the guest's emails (viewUrl()). Opening
 *    such a link also grants the session, so the guest can go on to pay.
 *
 * Everyone else gets the same "open the link from your email" page, whether
 * or not the code exists (see deniedResponse()).
 */
final class BookingAccess
{
    public const SESSION_KEY = 'booking_access';

    /** Bookings remembered per browser session (most recent kept). */
    public const MAX_GRANTS = 20;

    public static function grant(Request $request, Booking $booking): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $ids = array_values(array_diff((array) $request->session()->get(self::SESSION_KEY, []), [$booking->id]));
        $ids[] = $booking->id;

        $request->session()->put(self::SESSION_KEY, array_slice($ids, -self::MAX_GRANTS));
    }

    public static function allows(Request $request, Booking $booking): bool
    {
        if ($booking->user_id !== null && $request->user()?->getAuthIdentifier() === $booking->user_id) {
            return true;
        }

        if ($request->hasSession() && in_array($booking->id, (array) $request->session()->get(self::SESSION_KEY, []), true)) {
            return true;
        }

        // A signature is only valid for the exact URL (route + booking) it was made for.
        if ($request->query->has('signature') && $request->hasValidSignature()) {
            self::grant($request, $booking);

            return true;
        }

        return false;
    }

    /**
     * Signed, expiring link to the booking's confirmation page, for the
     * guest's emails. Valid until 60 days after check-out (like the invoice link).
     */
    public static function viewUrl(Booking $booking): string
    {
        return URL::temporarySignedRoute(
            'booking.confirmation',
            $booking->check_out->copy()->endOfDay()->addDays(60),
            ['booking' => $booking->confirmation_code]
        );
    }

    /**
     * The page shown when access is not granted. Identical for an unknown
     * code, a guest booking and an account booking (it offers both sign-in
     * and "email me the link"), so codes cannot be probed for existence.
     */
    public static function deniedResponse(Request $request)
    {
        if ($request->isMethod('GET') && ! $request->user()) {
            // Signing in returns the account holder straight to this page.
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return response()->view('booking-access', [], 403);
    }
}
