<?php

namespace Tests\Concerns;

use App\Models\Booking;
use App\Support\BookingAccess;

/**
 * For tests about checkout/payment BEHAVIOUR (not about access control):
 * every booking created during the test is treated as made in the test's
 * own browser session — exactly what BookingController::store() grants a
 * real guest (BookingAccess::grant()). Access control itself is covered,
 * without this trait, by BookingAccessTest.
 */
trait ActsAsBookingGuest
{
    protected function setUpActsAsBookingGuest(): void
    {
        Booking::created(function (Booking $booking) {
            // Account bookings are reached by signing in as the owner.
            if ($booking->user_id !== null) {
                return;
            }

            $session = $this->app['session']->driver();
            $ids = (array) $session->get(BookingAccess::SESSION_KEY, []);
            $ids[] = $booking->id;
            $session->put(BookingAccess::SESSION_KEY, $ids);
        });
    }
}
