<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalRooms = Room::count();
        $activeRooms = Room::where('is_active', true)->count();
        $totalBookings = Booking::count();
        $pendingBookings = Booking::where('status', Booking::STATUS_PENDING)->count();
        $unreadMessages = ContactMessage::where('is_read', false)->count();

        $cancelledBookingsThisMonth = Booking::where('status', Booking::STATUS_CANCELLED)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Front-desk snapshot for today.
        $todaysArrivals = Booking::where('status', Booking::STATUS_CONFIRMED)->whereDate('check_in', today())->count();
        $todaysDepartures = Booking::where('status', Booking::STATUS_CHECKED_IN)->whereDate('check_out', today())->count();
        $upcomingArrivals = Booking::where('status', Booking::STATUS_CONFIRMED)->where('check_in', '>', today())->count();

        // Room inventory snapshot right now: physically occupied (checked
        // in) rooms, rooms pulled out of service, and whatever's left over.
        // Every room of a checked-in booking, including multi-room bookings' extras.
        $occupiedRooms = Booking::where('status', Booking::STATUS_CHECKED_IN)->with('additionalRooms')->get()
            ->flatMap(fn (Booking $booking) => $booking->roomIds())
            ->unique()
            ->count();
        $maintenanceRooms = Room::where('availability_status', '!=', Room::AVAILABILITY_AVAILABLE)->count();
        $availableRooms = max(0, $activeRooms - $occupiedRooms - $maintenanceRooms);
        $occupancyPercentage = $activeRooms > 0 ? round(($occupiedRooms / $activeRooms) * 100, 1) : 0.0;

        // Revenue is read from actual completed Payment records, not
        // inferred from booking totals — this is what was really collected.
        $monthlyRevenue = Payment::where('status', Payment::STATUS_COMPLETED)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $revenueByProvider = Payment::where('status', Payment::STATUS_COMPLETED)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->select('provider', DB::raw('SUM(amount) as total'))
            ->groupBy('provider')
            ->pluck('total', 'provider');

        // Amount still owed on active (non-cancelled/completed) bookings
        // that have no completed payment on record yet.
        $pendingPaymentAmount = Booking::whereIn('status', [
            Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT,
        ])
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', Payment::STATUS_COMPLETED))
            ->sum('total_price');

        // Money that needs a human: paid after expiry, room taken, paid twice,
        // or an online attempt the gateway could not settle.
        $paymentsNeedingReview = Payment::awaitingReview()->with('booking')->latest('review_flagged_at')->get();

        $recentBookings = Booking::with(['room', 'additionalRooms.room'])->latest()->take(5)->get();
        $recentMessages = ContactMessage::latest()->take(5)->get();

        $bookingsOverTime = Booking::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $chartLabels = [];
        $chartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = now()->subDays($i)->format('M j');
            $chartData[] = (int) ($bookingsOverTime[$date]->total ?? 0);
        }

        return view('admin.dashboard', compact(
            'totalRooms', 'activeRooms', 'totalBookings', 'pendingBookings', 'unreadMessages',
            'cancelledBookingsThisMonth', 'todaysArrivals', 'todaysDepartures', 'upcomingArrivals',
            'occupiedRooms', 'maintenanceRooms', 'availableRooms', 'occupancyPercentage',
            'monthlyRevenue', 'revenueByProvider', 'pendingPaymentAmount',
            'recentBookings', 'recentMessages', 'chartLabels', 'chartData', 'paymentsNeedingReview'
        ));
    }
}
