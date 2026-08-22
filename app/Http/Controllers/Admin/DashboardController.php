<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalRooms = Room::count();
        $totalBookings = Booking::count();
        $pendingBookings = Booking::where('status', Booking::STATUS_PENDING)->count();
        $unreadMessages = ContactMessage::where('is_read', false)->count();

        $monthlyRevenue = Booking::where('status', Booking::STATUS_CONFIRMED)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_price');

        $recentBookings = Booking::with('room')->latest()->take(5)->get();
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
            'totalRooms', 'totalBookings', 'pendingBookings', 'unreadMessages',
            'monthlyRevenue', 'recentBookings', 'recentMessages', 'chartLabels', 'chartData'
        ));
    }
}
