<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;

/**
 * Invoice PDF downloads. Three ways in, each with its own access rule:
 *  - account holders: only their own bookings (404 otherwise, so another
 *    customer's booking code is never confirmed to exist);
 *  - guests without an account: a signed, expiring link from their
 *    confirmation email (route 'signed' middleware);
 *  - staff: Admin\BookingController::invoice, behind the admin guard.
 */
class InvoiceController extends Controller
{
    public function account(Booking $booking, InvoiceService $invoices)
    {
        abort_unless($booking->user_id !== null && $booking->user_id === Auth::id(), 404);

        return $invoices->download($booking);
    }

    public function guest(Booking $booking, InvoiceService $invoices)
    {
        return $invoices->download($booking);
    }
}
