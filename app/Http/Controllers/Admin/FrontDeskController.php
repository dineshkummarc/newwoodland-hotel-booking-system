<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\GuestProfile;

class FrontDeskController extends Controller
{
    /**
     * The daily front-desk workflow: who is arriving today, who is due to
     * leave today, and who is currently in-house. All three lists are
     * backed by real booking state (never guessed from dates alone) so a
     * booking that never checked in doesn't quietly appear as "in-house".
     */
    public function index()
    {
        $todaysArrivals = Booking::with(['room', 'additionalRooms.room'])
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereDate('check_in', today())
            ->orderBy('customer_name')
            ->get();

        $todaysDepartures = Booking::with(['room', 'additionalRooms.room'])
            ->where('status', Booking::STATUS_CHECKED_IN)
            ->whereDate('check_out', today())
            ->orderBy('customer_name')
            ->get();

        $inHouseGuests = Booking::with(['room', 'additionalRooms.room'])
            ->where('status', Booking::STATUS_CHECKED_IN)
            ->orderBy('check_out')
            ->get();

        $upcomingArrivals = Booking::with(['room', 'additionalRooms.room'])
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereDate('check_in', '>', today())
            ->orderBy('check_in')
            ->take(10)
            ->get();

        $vipEmails = GuestProfile::forEmails(
            $todaysArrivals->concat($todaysDepartures)->concat($inHouseGuests)->concat($upcomingArrivals)->pluck('email')
        )->filter->is_vip->keys();

        return view('admin.front-desk.index', compact(
            'todaysArrivals', 'todaysDepartures', 'inHouseGuests', 'upcomingArrivals', 'vipEmails'
        ));
    }
}
