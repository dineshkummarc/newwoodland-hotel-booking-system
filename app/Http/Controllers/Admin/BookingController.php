<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Room;
use App\Services\AuditLogService;
use App\Services\BookingService;
use App\Services\InvoiceService;
use App\Services\SettingsService;
use App\Support\SafeCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with(['room', 'additionalRooms.room', 'payments'])->latest();

        $this->applyFilters($query, $request);

        $bookings = $query->paginate(15)->withQueryString();
        $rooms = Room::orderBy('name')->orderBy('room_number')->get(['id', 'name', 'room_number']);

        return view('admin.bookings.index', compact('bookings', 'rooms'));
    }

    /**
     * Walk-in / phone booking. Reuses the exact same validation and the
     * exact same BookingService::create() the public booking form uses
     * (transaction-safe availability re-check, server-side pricing) —
     * only the entry point differs. Created with no user_id, same as any
     * other guest checkout without an account.
     */
    public function create()
    {
        $rooms = Room::bookable()->orderBy('name')->get();

        return view('admin.bookings.create', compact('rooms'));
    }

    public function store(StoreBookingRequest $request, BookingService $bookingService, AuditLogService $auditLog)
    {
        $room = $request->room();

        $booking = $bookingService->create($room, [
            'user_id' => null,
            'check_in' => $request->validated('check_in'),
            'check_out' => $request->validated('check_out'),
            'guests_label' => $request->validated('guests_label'),
            'customer_name' => $request->validated('customer_name'),
            'email' => $request->validated('email'),
            'phone' => $request->e164Phone(),
            'country' => $request->validated('country'),
            'special_requests' => $request->validated('special_requests'),
        ], $request->additionalRooms());

        $booking->load('additionalRooms.room');
        $auditLog->log('booking.created', 'booking', $booking->id, "Created walk-in/phone booking {$booking->confirmation_code} for {$booking->customer_name} in {$booking->roomsSummary()}.", [
            'room_ids' => $booking->roomIds(),
        ]);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'Booking created (held as pending). Record a payment or confirm it below.');
    }

    public function show(Booking $booking)
    {
        $booking->load(['room', 'additionalRooms.room', 'payments', 'charges.addedBy', 'charges.voidedBy', 'refunds.recordedBy', 'refunds.processedBy', 'guestDocuments.recordedBy']);

        $activity = \App\Models\AuditLog::with('admin')
            ->where('target_type', 'booking')
            ->where('target_id', $booking->id)
            ->latest()
            ->get();

        $guestProfile = \App\Models\GuestProfile::forEmail($booking->email);

        return view('admin.bookings.show', compact('booking', 'activity', 'guestProfile'));
    }

    /**
     * Modify an existing booking's dates and/or guest information. Room
     * changes go through the separate reassignRoom() action below, kept
     * distinct so the two very different re-check rules (date overlap vs.
     * room capacity/availability) never get tangled into one form.
     */
    public function edit(Booking $booking)
    {
        $booking->load('room');

        return view('admin.bookings.edit', compact('booking'));
    }

    public function update(UpdateBookingRequest $request, Booking $booking, BookingService $bookingService, AuditLogService $auditLog)
    {
        $before = $booking->only(['check_in', 'check_out', 'guests_label', 'customer_name', 'email', 'phone', 'total_price']);

        try {
            $booking = $bookingService->modify($booking, $request->bookingData());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $auditLog->log('booking.modified', 'booking', $booking->id, "Modified booking {$booking->confirmation_code}.", [
            'before' => $before,
            'after' => $booking->only(['check_in', 'check_out', 'guests_label', 'customer_name', 'email', 'phone', 'total_price']),
        ]);

        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Booking updated.');
    }

    /**
     * Rooms genuinely available for THIS booking's exact stay period right
     * now: bookable (active, not under maintenance), capable of holding
     * this booking's party size, and not already overlapping another
     * confirmed/checked-in/fresh-pending booking. The current room is
     * excluded — reassigning to the room it's already in is a no-op the
     * service layer would reject anyway.
     */
    public function reassignRoom(Request $request, Booking $booking, BookingService $bookingService, AuditLogService $auditLog)
    {
        $validated = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            // Set to move one of the additional rooms of a multi-room booking.
            'booking_room_id' => ['nullable', 'integer'],
        ]);

        $newRoom = Room::findOrFail($validated['room_id']);
        $line = null;
        if (! empty($validated['booking_room_id'])) {
            $line = $booking->additionalRooms()->with('room')->findOrFail($validated['booking_room_id']);
        }
        $oldRoom = $line ? $line->room : $booking->room;

        try {
            $booking = $bookingService->reassignRoom($booking, $newRoom, $line);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $auditLog->log('booking.room_reassigned', 'booking', $booking->id, "Reassigned booking {$booking->confirmation_code} from {$oldRoom?->name} to {$newRoom->name}.", [
            'old_room_id' => $oldRoom?->id,
            'old_room_name' => $oldRoom?->name,
            'new_room_id' => $newRoom->id,
            'new_room_name' => $newRoom->name,
        ]);

        return back()->with('success', "Booking reassigned to {$newRoom->name}.");
    }

    /**
     * Record a payment collected outside any online gateway (cash at the
     * front desk). Amount is never taken from the request — always the
     * booking's own total_price, same rule as every online gateway.
     */
    public function recordPayment(Request $request, Booking $booking, BookingService $bookingService, AuditLogService $auditLog, SettingsService $settingsService)
    {
        $validated = $request->validate([
            // hotel_qr: guest scanned the hotel's eSewa/Fonepay QR at the front desk.
            'provider' => ['required', 'in:cash,bank_transfer,hotel_qr'],
            'reference' => ['nullable', 'string', 'max:255'],
            // Actual amount received; omitted = the whole outstanding balance.
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
        ]);

        try {
            $payment = $bookingService->recordManualPayment(
                $booking,
                $validated['provider'],
                $validated['reference'] ?? null,
                isset($validated['amount']) ? (float) $validated['amount'] : null,
            );
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        // A bank-transfer guest isn't at the front desk: let them know their
        // booking is now confirmed (cash is handed over in person, so no email).
        if ($payment->provider === 'bank_transfer' && $booking->refresh()->status === Booking::STATUS_CONFIRMED) {
            $bookingService->sendNotifications($booking, $settingsService->current()->site_email);
        }

        $auditLog->log('booking.payment_recorded', 'booking', $booking->id, "Recorded {$validated['provider']} payment of ".npr($payment->amount)." for booking {$booking->confirmation_code}.", [
            'payment_id' => $payment->id,
            'amount' => (string) $payment->amount,
            'provider' => $payment->provider,
        ]);

        $booking->refresh();

        if ($payment->needsReview()) {
            return back()->with('error', 'Payment recorded, but it needs review: '.$payment->review_reason);
        }

        return back()->with('success', $booking->status === Booking::STATUS_CONFIRMED
            ? 'Payment recorded and booking confirmed.'
            : 'Payment recorded.');
    }

    /**
     * Manual status override between the pre-arrival statuses (pending /
     * confirmed / cancelled, including reversing an accidental
     * cancellation). Deliberately excludes checked_in/checked_out/completed/
     * no_show — those go through the dedicated actions. Moving a booking back
     * onto its room re-checks availability under a room lock in
     * BookingService::changeStatusByStaff(), so this can never double-book.
     */
    public function updateStatus(Request $request, Booking $booking, AuditLogService $auditLog, BookingService $bookingService)
    {
        $validated = Validator::make($request->all(), [
            'status' => ['required', 'in:'.implode(',', BookingService::STAFF_OVERRIDABLE_STATUSES)],
        ])->validate();

        try {
            [$booking, $previousStatus] = $bookingService->changeStatusByStaff($booking, $validated['status']);
        } catch (ValidationException $e) {
            $auditLog->log('booking.status_change_refused', 'booking', $booking->id, "Refused changing booking {$booking->confirmation_code} status from {$booking->status} to {$validated['status']}.", [
                'reason' => collect($e->errors())->flatten()->first(),
            ]);

            return back()->withErrors($e->errors());
        }

        $auditLog->log('booking.status_changed', 'booking', $booking->id, "Changed booking {$booking->confirmation_code} status from {$previousStatus} to {$validated['status']}.");

        return back()->with('success', 'Booking status updated to '.ucfirst($validated['status']).'.');
    }

    /**
     * Hotel policy: guests present an official photo ID at check-in. Staff
     * confirm that here (id_verified), unless an ID is already recorded on
     * the booking. The ID number itself is never required or logged here.
     */
    public function checkIn(Request $request, Booking $booking, BookingService $bookingService, AuditLogService $auditLog)
    {
        $hasRecordedId = $booking->guestDocuments()->exists();

        if (config('bookings.checkin_requires_photo_id') && ! $hasRecordedId && ! $request->boolean('id_verified')) {
            return back()->with('error', 'Please confirm the guest has presented an official photo ID (e.g. citizenship or an office/company ID card with photo) before checking in.');
        }

        try {
            $bookingService->checkIn($booking);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $auditLog->log('booking.checked_in', 'booking', $booking->id, "Checked in booking {$booking->confirmation_code}.", [
            'photo_id_confirmed' => $request->boolean('id_verified') || $hasRecordedId,
            'photo_id_recorded' => $hasRecordedId,
        ]);

        return back()->with('success', 'Guest checked in.');
    }

    /**
     * The guest has arrived and cancels the rest of the stay: the
     * after-arrival cancellation charge (50%) applies — see
     * BookingService::cancelAfterArrival().
     */
    public function cancelStay(Booking $booking, BookingService $bookingService, AuditLogService $auditLog, SettingsService $settingsService)
    {
        try {
            $booking = $bookingService->cancelAfterArrival($booking);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $auditLog->log('booking.cancelled_after_arrival', 'booking', $booking->id, "Cancelled stay {$booking->confirmation_code} after arrival ({$booking->cancellation_charge_percent}% cancellation charge).", [
            'cancellation_charge_percent' => $booking->cancellation_charge_percent,
            'cancellation_charge' => (string) $booking->cancellationCharge(0),
            'extra_charges' => (string) $booking->chargesTotal(),
            'amount_paid' => (string) $booking->amountPaid(),
        ]);

        $bookingService->sendCancellationNotifications($booking, $settingsService->current()->site_email);

        $balance = $booking->outstandingBalance();

        return back()->with('success', 'Stay cancelled. '.($balance > 0
            ? 'The guest owes '.npr($balance).' — record it under Payment.'
            : ($balance < 0 ? npr(-$balance).' is due back to the guest — record the refund under Payment.' : 'Nothing further is owed.')));
    }

    public function checkOut(Booking $booking, BookingService $bookingService, AuditLogService $auditLog)
    {
        try {
            $bookingService->checkOut($booking);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $auditLog->log('booking.checked_out', 'booking', $booking->id, "Checked out booking {$booking->confirmation_code}.");

        return back()->with('success', 'Guest checked out.');
    }

    public function markNoShow(Booking $booking, BookingService $bookingService, AuditLogService $auditLog)
    {
        try {
            $bookingService->markNoShow($booking);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $auditLog->log('booking.no_show', 'booking', $booking->id, "Marked booking {$booking->confirmation_code} as a no-show.");

        return back()->with('success', 'Booking marked as no-show.');
    }

    public function markCompleted(Booking $booking, BookingService $bookingService, AuditLogService $auditLog)
    {
        try {
            $bookingService->markCompleted($booking);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $auditLog->log('booking.completed', 'booking', $booking->id, "Marked booking {$booking->confirmation_code} as completed.");

        return back()->with('success', 'Booking marked as completed.');
    }

    public function invoice(Booking $booking, InvoiceService $invoices)
    {
        return $invoices->download($booking);
    }

    public function addCharge(Request $request, Booking $booking, BookingService $bookingService, AuditLogService $auditLog)
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(BookingCharge::CATEGORIES))],
            'description' => ['required', 'string', 'max:255'],
            'unit_price' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $charge = $bookingService->addCharge($booking, $validated, $request->user('admin')->id);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $auditLog->log('booking.charge_added', 'booking', $booking->id, "Added charge \"{$charge->description}\" of ".npr($charge->amount)." to booking {$booking->confirmation_code}.", [
            'charge_id' => $charge->id,
            'category' => $charge->category,
            'unit_price' => (string) $charge->unit_price,
            'quantity' => $charge->quantity,
            'amount' => (string) $charge->amount,
        ]);

        return back()->with('success', 'Charge added. The balance due has been updated.');
    }

    /**
     * Correct a charge (admin/super_admin only, like voiding: it can lower
     * what the guest owes). Audited with before/after values.
     */
    public function updateCharge(Request $request, Booking $booking, BookingCharge $charge, BookingService $bookingService, AuditLogService $auditLog)
    {
        abort_unless($charge->booking_id === $booking->id, 404);

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(BookingCharge::CATEGORIES))],
            'description' => ['required', 'string', 'max:255'],
            'unit_price' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $charge->getAttributes();

        try {
            $charge = $bookingService->updateCharge($charge, $validated);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        // Logged against the booking (so it shows in the booking's activity), with only the changed fields.
        $after = \Illuminate\Support\Arr::except($charge->getChanges(), ['created_at', 'updated_at']);
        if ($after) {
            $auditLog->log('booking.charge_updated', 'booking', $booking->id, "Corrected charge \"{$charge->description}\" on booking {$booking->confirmation_code}.", [
                'charge_id' => $charge->id,
                'before' => \Illuminate\Support\Arr::only($before, array_keys($after)),
                'after' => $after,
            ]);
        }

        return back()->with('success', 'Charge updated. The balance due has been recalculated.');
    }

    /**
     * Restricted to admin/super_admin by the route group: removing a charge
     * reduces what the guest owes, so it is not a front-desk action.
     */
    public function voidCharge(Request $request, Booking $booking, BookingCharge $charge, BookingService $bookingService, AuditLogService $auditLog)
    {
        abort_unless($charge->booking_id === $booking->id, 404);

        $validated = $request->validate(['void_reason' => ['required', 'string', 'max:255']]);

        try {
            $bookingService->voidCharge($charge, $validated['void_reason'], $request->user('admin')->id);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $auditLog->log('booking.charge_voided', 'booking', $booking->id, "Voided charge \"{$charge->description}\" (".npr($charge->amount).") on booking {$booking->confirmation_code}.", [
            'charge_id' => $charge->id,
            'amount' => (string) $charge->amount,
            'reason' => $validated['void_reason'],
        ]);

        return back()->with('success', 'Charge voided.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Booking::with(['room', 'additionalRooms.room', 'payments'])->latest();
        $this->applyFilters($query, $request);
        $bookings = $query->get();

        $filename = 'bookings-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($bookings) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Confirmation Code', 'Room', 'Guest', 'Email', 'Phone', 'Check-in', 'Check-out', 'Nights', 'Nightly Rate', 'Total', 'Status', 'Payment Status', 'Payment Ref', 'Created At']);

            foreach ($bookings as $booking) {
                $payment = $booking->payments->sortByDesc('created_at')->first();

                // Guest-typed values are neutralised against spreadsheet formula injection.
                SafeCsv::put($out, [
                    $booking->confirmation_code,
                    $booking->roomsSummary(),
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

        if ($roomId = $request->string('room_id')->toString()) {
            $query->occupyingRoom((int) $roomId);
        }

        if ($paymentStatus = $request->string('payment_status')->toString()) {
            if ($paymentStatus === 'none') {
                $query->whereDoesntHave('payments');
            } else {
                $query->whereHas('payments', fn ($q) => $q->where('status', $paymentStatus));
            }
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
