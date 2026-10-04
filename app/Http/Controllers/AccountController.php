<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function bookings(BookingService $bookingService)
    {
        $bookings = Auth::user()->bookings()->with(['room', 'additionalRooms.room', 'payments'])->latest()->paginate(10);
        $cancellationWindowHours = $bookingService->cancellationWindowHours();

        return view('account.bookings', compact('bookings', 'cancellationWindowHours'));
    }

    public function cancelBooking(
        Booking $booking,
        BookingService $bookingService,
        SettingsService $settingsService
    ) {
        abort_if($booking->user_id !== Auth::id(), 403);

        try {
            $booking = $bookingService->cancel($booking);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $bookingService->sendCancellationNotifications($booking, $settingsService->current()->site_email);

        return back()->with('success', 'Your booking '.$booking->confirmation_code.' has been cancelled.');
    }
}
