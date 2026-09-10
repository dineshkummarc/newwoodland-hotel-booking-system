<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with(['room', 'payments'])->latest();

        $this->applyFilters($query, $request);

        $bookings = $query->paginate(15)->withQueryString();

        return view('admin.bookings.index', compact('bookings'));
    }

    public function show(Booking $booking)
    {
        $booking->load(['room', 'payments']);

        return view('admin.bookings.show', compact('booking'));
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $validated = Validator::make($request->all(), [
            'status' => ['required', 'in:'.implode(',', [
                Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED,
            ])],
        ])->validate();

        // Keep cancelled_at consistent with status regardless of which side
        // (guest self-service or admin override) changes it, so the two
        // fields never drift apart (e.g. a re-confirmed booking still
        // showing a stale cancellation timestamp).
        $booking->update([
            'status' => $validated['status'],
            'cancelled_at' => $validated['status'] === Booking::STATUS_CANCELLED
                ? ($booking->cancelled_at ?? now())
                : null,
        ]);

        return back()->with('success', 'Booking status updated to '.ucfirst($validated['status']).'.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Booking::with(['room', 'payments'])->latest();
        $this->applyFilters($query, $request);
        $bookings = $query->get();

        $filename = 'bookings-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($bookings) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Confirmation Code', 'Room', 'Guest', 'Email', 'Phone', 'Check-in', 'Check-out', 'Nights', 'Nightly Rate', 'Total', 'Status', 'Payment Status', 'Payment Ref', 'Created At']);

            foreach ($bookings as $booking) {
                $payment = $booking->payments->sortByDesc('created_at')->first();

                fputcsv($out, [
                    $booking->confirmation_code,
                    $booking->room->name ?? '—',
                    $booking->customer_name,
                    $booking->email,
                    $booking->phone,
                    $booking->check_in->toDateString(),
                    $booking->check_out->toDateString(),
                    $booking->nights,
                    $booking->nightly_rate,
                    $booking->total_price,
                    $booking->status,
                    $payment->status ?? '—',
                    $payment->reference_id ?? '—',
                    $booking->created_at->toDateTimeString(),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function applyFilters($query, Request $request): void
    {
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($from = $request->string('from')->toString()) {
            $query->whereDate('check_in', '>=', $from);
        }

        if ($to = $request->string('to')->toString()) {
            $query->whereDate('check_out', '<=', $to);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('confirmation_code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
    }
}
