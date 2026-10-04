<?php

namespace App\Http\Middleware;

use App\Models\Booking;
use App\Support\BookingAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every guest-facing booking page and payment action: the
 * confirmation code in the URL is never enough on its own — see
 * App\Support\BookingAccess for who is let in. Enforced server-side for
 * GET and POST alike (paying, choosing a method, viewing bank details).
 */
class EnsureBookingAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $booking = $request->route('booking');

        if (! $booking instanceof Booking || ! BookingAccess::allows($request, $booking)) {
            return BookingAccess::deniedResponse($request);
        }

        return $next($request);
    }
}
