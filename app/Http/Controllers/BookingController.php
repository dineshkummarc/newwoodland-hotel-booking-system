<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use App\Services\SettingsService;

class BookingController extends Controller
{
    public function create()
    {
        $rooms = Room::active()->orderBy('name')->get();

        return view('booking', compact('rooms'));
    }

    public function store(StoreBookingRequest $request, BookingService $bookingService, SettingsService $settingsService)
    {
        $room = $request->room();

        $booking = $bookingService->create($room, [
            'check_in' => $request->validated('check_in'),
            'check_out' => $request->validated('check_out'),
            'guests_label' => $request->validated('guests_label'),
            'customer_name' => $request->validated('customer_name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'country' => $request->validated('country'),
            'special_requests' => $request->validated('special_requests'),
        ]);

        $bookingService->sendNotifications($booking, $settingsService->current()->site_email);

        return redirect()
            ->route('booking.confirmation', $booking->confirmation_code)
            ->with('success', 'Your booking has been submitted successfully.');
    }

    public function confirmation(Booking $booking)
    {
        $booking->load('room');

        return view('booking-confirmation', compact('booking'));
    }
}
