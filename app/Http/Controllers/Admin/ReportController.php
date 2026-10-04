<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Room;
use App\Support\SafeCsv;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Statuses that represent a real (non-abandoned, non-cancelled) stay —
     * used consistently across every report below so booking counts,
     * revenue, occupancy and room performance all agree with each other.
     */
    protected const REALIZED_STATUSES = [
        Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN,
        Booking::STATUS_CHECKED_OUT, Booking::STATUS_COMPLETED,
    ];

    public function index()
    {
        return view('admin.reports.index');
    }

    public function bookings(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $query = $this->overlappingRange(Booking::with(['room', 'additionalRooms.room']), $from, $to);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($roomId = $request->string('room_id')->toString()) {
            $query->where('room_id', $roomId);
        }

        if ($guest = $request->string('guest')->toString()) {
            $query->where(function ($q) use ($guest) {
                $q->where('customer_name', 'like', "%{$guest}%")
                    ->orWhere('email', 'like', "%{$guest}%");
            });
        }

        $bookings = (clone $query)->latest('check_in')->paginate(20)->withQueryString();

        $statusCounts = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $rooms = Room::orderBy('name')->get(['id', 'name']);

        return view('admin.reports.bookings', compact('bookings', 'statusCounts', 'rooms', 'from', 'to'));
    }

    public function bookingsExport(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);

        $query = $this->overlappingRange(Booking::with(['room', 'additionalRooms.room']), $from, $to);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($roomId = $request->string('room_id')->toString()) {
            $query->where('room_id', $roomId);
        }

        $bookings = $query->orderBy('check_in')->get();

        return response()->streamDownload(function () use ($bookings) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Confirmation Code', 'Room', 'Guest', 'Check-in', 'Check-out', 'Nights', 'Total', 'Status']);
            foreach ($bookings as $booking) {
                SafeCsv::put($out, [
                    $booking->confirmation_code, $booking->roomsSummary(), $booking->customer_name,
                    $booking->check_in->toDateString(), $booking->check_out->toDateString(),
                    $booking->nights, $booking->total_price, $booking->status,
                ]);
            }
            fclose($out);
        }, 'bookings-report-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function revenue(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $payments = Payment::with('booking')
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->get();

        $totalRevenue = $payments->sum('amount');
        $byProvider = $payments->groupBy('provider')->map(fn ($group) => [
            'count' => $group->count(),
            'total' => $group->sum('amount'),
        ]);

        $cancelledCount = Booking::where('status', Booking::STATUS_CANCELLED)
            ->whereBetween('check_in', [$from->toDateString(), $to->toDateString()])
            ->count();

        // Extra charges billed in the period (voided ones excluded). These
        // are what was billed; what was collected is already in the payments above.
        $chargesByCategory = BookingCharge::active()
            ->whereBetween('charged_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('category, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // Money returned to guests in the period (completed refunds only).
        $refundsTotal = (float) Refund::where('status', Refund::STATUS_COMPLETED)
            ->whereBetween('refunded_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('amount');
        $netRevenue = round($totalRevenue - $refundsTotal, 2);

        return view('admin.reports.revenue', compact('payments', 'totalRevenue', 'byProvider', 'cancelledCount', 'chargesByCategory', 'refundsTotal', 'netRevenue', 'from', 'to'));
    }

    public function occupancy(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $totalRooms = Room::where('is_active', true)->count();
        $totalDays = $from->diffInDays($to) + 1;
        $availableRoomNights = $totalRooms * $totalDays;

        $bookings = Booking::whereIn('status', self::REALIZED_STATUSES)
            ->where('check_in', '<', $to->copy()->addDay()->toDateString())
            ->where('check_out', '>', $from->toDateString())
            ->withCount('additionalRooms')
            ->get(['id', 'check_in', 'check_out']);

        // A multi-room booking occupies each of its rooms for every night.
        $occupiedRoomNights = $bookings->sum(function (Booking $booking) use ($from, $to) {
            $start = $booking->check_in->max($from);
            $end = $booking->check_out->min($to->copy()->addDay());

            return max(0, $start->diffInDays($end)) * (1 + $booking->additional_rooms_count);
        });

        $occupancyPercentage = $availableRoomNights > 0
            ? round(($occupiedRoomNights / $availableRoomNights) * 100, 1)
            : 0.0;

        return view('admin.reports.occupancy', compact(
            'totalRooms', 'totalDays', 'availableRoomNights', 'occupiedRoomNights', 'occupancyPercentage', 'from', 'to'
        ));
    }

    public function roomPerformance(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $bookings = $this->overlappingRange(
            Booking::whereIn('status', self::REALIZED_STATUSES)->with('additionalRooms'), $from, $to
        )->get();

        // Per-room stats. A single-room booking credits its room with the
        // booking total; on a multi-room booking each additional room gets
        // its own subtotal (less the booking's discount) and the primary room
        // the remainder, so the rooms always add up to the booking total.
        $stats = [];
        $credit = function (int $roomId, int $nights, float $revenue) use (&$stats) {
            $stats[$roomId] ??= ['bookings' => 0, 'nights' => 0, 'revenue' => 0.0];
            $stats[$roomId]['bookings']++;
            $stats[$roomId]['nights'] += $nights;
            $stats[$roomId]['revenue'] += $revenue;
        };

        foreach ($bookings as $booking) {
            $factor = 1 - (float) $booking->discount_percent / 100;
            $extrasNet = 0.0;

            foreach ($booking->additionalRooms as $line) {
                $net = round((float) $line->subtotal * $factor, 2);
                $credit($line->room_id, (int) $booking->nights, $net);
                $extrasNet += $net;
            }

            $credit($booking->room_id, (int) $booking->nights, round((float) $booking->total_price - $extrasNet, 2));
        }

        $rooms = Room::orderBy('name')->get()
            ->each(function (Room $room) use ($stats) {
                $room->bookings_count = $stats[$room->id]['bookings'] ?? 0;
                $room->room_nights = $stats[$room->id]['nights'] ?? 0;
                $room->revenue = round($stats[$room->id]['revenue'] ?? 0, 2);
            })
            ->sortByDesc('revenue')
            ->values();

        return view('admin.reports.room-performance', compact('rooms', 'from', 'to'));
    }

    public function payments(Request $request)
    {
        $query = Payment::with('booking')->latest();

        if ($provider = $request->string('provider')->toString()) {
            $query->where('provider', $provider);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $payments = $query->paginate(20)->withQueryString();

        return view('admin.reports.payments', compact('payments'));
    }

    public function paymentsExport(Request $request): StreamedResponse
    {
        $query = Payment::with('booking')->latest();

        if ($provider = $request->string('provider')->toString()) {
            $query->where('provider', $provider);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $payments = $query->get();

        return response()->streamDownload(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Booking Code', 'Provider', 'Status', 'Amount', 'Currency', 'Transaction UUID', 'Reference ID', 'Paid At']);
            foreach ($payments as $payment) {
                SafeCsv::put($out, [
                    $payment->booking->confirmation_code ?? '—', $payment->provider, $payment->status,
                    $payment->amount, $payment->currency, $payment->transaction_uuid,
                    $payment->reference_id ?? '—', $payment->paid_at?->toDateTimeString() ?? '—',
                ]);
            }
            fclose($out);
        }, 'payments-report-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Filter a bookings query to every booking that overlaps ANY night of
     * [$from, $to], not just ones whose check_in happens to fall inside the
     * range. A booking that started before $from and is still ongoing
     * during the range must still count — the same overlap rule
     * Booking::scopeOverlapping() and the occupancy report already use.
     * Without this, a multi-night stay spanning a report boundary was
     * silently missed from the booking and room-performance reports.
     */
    protected function overlappingRange($query, Carbon $from, Carbon $to)
    {
        return $query
            ->where('check_in', '<', $to->copy()->addDay()->toDateString())
            ->where('check_out', '>', $from->toDateString());
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function dateRange(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : now()->endOfMonth();

        return [$from->startOfDay(), $to->startOfDay()];
    }
}
