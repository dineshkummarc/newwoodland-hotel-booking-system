<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Refund;
use App\Services\AuditLogService;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin/super_admin only (route group): recording a refund means money
 * leaves the hotel.
 */
class RefundController extends Controller
{
    public function store(Request $request, Booking $booking, RefundService $refunds, AuditLogService $auditLog)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(array_keys(Refund::METHODS))],
            'payment_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'completed' => ['nullable', 'boolean'],
        ]);
        $validated['completed'] = $request->boolean('completed');

        try {
            $refund = $refunds->record($booking, $validated, $request->user('admin')->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors(), 'refund')->withInput();
        }

        $auditLog->log('booking.refund_recorded', 'booking', $booking->id,
            ($refund->status === Refund::STATUS_COMPLETED ? 'Recorded a completed refund' : 'Recorded a pending refund')
            ." of ".npr($refund->amount)." ({$refund->methodLabel()}) for booking {$booking->confirmation_code}.", [
                'refund_id' => $refund->id,
                'amount' => (string) $refund->amount,
                'method' => $refund->method,
                'status' => $refund->status,
                'reference' => $refund->reference,
            ]);

        return back()->with('success', 'Refund recorded.');
    }

    public function complete(Request $request, Booking $booking, Refund $refund, RefundService $refunds, AuditLogService $auditLog)
    {
        abort_unless($refund->booking_id === $booking->id, 404);
        $validated = $request->validate(['reference' => ['nullable', 'string', 'max:255']]);

        try {
            $refund = $refunds->complete($refund, $validated['reference'] ?? null, $request->user('admin')->id);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $auditLog->log('booking.refund_completed', 'booking', $booking->id, 'Marked refund of '.npr($refund->amount)." as paid for booking {$booking->confirmation_code}.", [
            'refund_id' => $refund->id,
            'reference' => $refund->reference,
        ]);

        return back()->with('success', 'Refund marked as paid.');
    }

    public function cancel(Request $request, Booking $booking, Refund $refund, RefundService $refunds, AuditLogService $auditLog)
    {
        abort_unless($refund->booking_id === $booking->id, 404);

        try {
            $refund = $refunds->cancel($refund, $request->user('admin')->id);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $auditLog->log('booking.refund_cancelled', 'booking', $booking->id, 'Cancelled pending refund of '.npr($refund->amount)." for booking {$booking->confirmation_code}.", [
            'refund_id' => $refund->id,
        ]);

        return back()->with('success', 'Pending refund cancelled.');
    }
}
