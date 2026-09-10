<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function create()
    {
        $rooms = Room::active()->orderBy('name')->get();

        return view('booking', compact('rooms'));
    }

    public function store(StoreBookingRequest $request, BookingService $bookingService)
    {
        $room = $request->room();

        $booking = $bookingService->create($room, [
            'user_id' => Auth::id(),
            'check_in' => $request->validated('check_in'),
            'check_out' => $request->validated('check_out'),
            'guests_label' => $request->validated('guests_label'),
            'customer_name' => $request->validated('customer_name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'country' => $request->validated('country'),
            'special_requests' => $request->validated('special_requests'),
        ]);

        // The booking is held (pending) but not confirmed until payment is
        // completed and verified server-side — see PaymentController.
        return redirect()
            ->route('booking.pay', $booking->confirmation_code)
            ->with('success', 'Your room is held — please complete payment to confirm your booking.');
    }

    public function confirmation(Booking $booking)
    {
        $booking->load(['room', 'payments' => fn ($q) => $q->latest()]);

        return view('booking-confirmation', compact('booking'));
    }
}
