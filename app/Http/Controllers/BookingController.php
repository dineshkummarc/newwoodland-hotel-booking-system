<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Mail\BookingAccessLinkMail;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use App\Support\BookingAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class BookingController extends Controller
{
    public function create()
    {
        $rooms = Room::onePerType(Room::bookable()->orderBy('name')->get());

        // "Book" links carry a specific room id; if it's a same-type room that
        // isn't the one listed in the dropdown, preselect its type instead.
        $requested = Room::find(request('room'));
        $selectedRoomId = old('room_id', $requested ? $rooms->firstWhere('name', $requested->name)?->id : null);

        return view('booking', compact('rooms', 'selectedRoomId'));
    }

    public function store(StoreBookingRequest $request, BookingService $bookingService)
    {
        $room = $request->room();

        $data = [
            'user_id' => Auth::id(),
            'check_in' => $request->validated('check_in'),
            'check_out' => $request->validated('check_out'),
            'guests_label' => $request->validated('guests_label'),
            'customer_name' => $request->validated('customer_name'),
            'email' => $request->validated('email'),
            'phone' => $request->e164Phone(),
            'country' => $request->validated('country'),
            'special_requests' => $request->validated('special_requests'),
        ];

        $roomsCount = (int) ($request->validated('rooms_count') ?? 1);

        // One guest can't tie up the hotel's rooms with a pile of unpaid holds.
        $bookingService->assertWithinUnpaidHoldLimit($data['email'], $data['phone'], $data['user_id'], $roomsCount);

        $booking = $bookingService->createForRoomType($room, $data, $roomsCount);

        // This browser created the booking, so it may see and pay for it.
        BookingAccess::grant($request, $booking);

        // The booking is held (pending) but not confirmed until the guest
        // chooses how to pay and that payment is confirmed — see PaymentController.
        return redirect()
            ->route('booking.pay', $booking->confirmation_code)
            ->with('success', 'Your room is held — please choose how you would like to pay.');
    }

    public function confirmation(Booking $booking)
    {
        $booking->load(['room', 'payments' => fn ($q) => $q->latest()]);

        return view('booking-confirmation', compact('booking'));
    }

    /**
     * "Email me my booking link". The signed link goes ONLY to the email
     * address stored on the booking, and the response is the same whether
     * or not the code/email pair matched, so this can't be used to discover
     * bookings or to learn anything about a guest.
     */
    public function sendAccessLink(Request $request)
    {
        $validated = $request->validate([
            'confirmation_code' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $booking = Booking::where('confirmation_code', strtoupper(trim($validated['confirmation_code'])))->first();

        if ($booking && strcasecmp(trim($booking->email), trim($validated['email'])) === 0) {
            // At most a few emails per booking per hour, whoever asks.
            $key = 'booking-access-link:'.$booking->id;

            if (! RateLimiter::tooManyAttempts($key, 3)) {
                RateLimiter::hit($key, 3600);
                Mail::to($booking->email)->send(new BookingAccessLinkMail($booking));
            }
        }

        return back()->with('success', 'If those details match a booking, we have emailed a secure link to view it to the address on the booking. Please check your inbox (and spam folder).');
    }
}
