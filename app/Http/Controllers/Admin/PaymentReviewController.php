<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The staff side of the payment review queue (Payment::flagForReview()):
 * once a flagged payment has been dealt with (refunded, booking
 * re-confirmed, guest rebooked), staff record what was done and close it.
 */
class PaymentReviewController extends Controller
{
    public function resolve(Request $request, Payment $payment, AuditLogService $auditLog)
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:500']]);

        if (! $payment->needsReview()) {
            return back()->with('error', 'This payment is not awaiting review.');
        }

        $payment->forceFill([
            'review_resolved_at' => now(),
            'review_resolved_by' => Auth::guard('admin')->id(),
        ])->save();

        $auditLog->log('payment.review_resolved', 'booking', $payment->booking_id, 'Resolved payment review: '.$validated['note'], [
            'payment_id' => $payment->id,
            'reason' => $payment->review_reason,
            'note' => $validated['note'],
        ]);

        return back()->with('success', 'Payment review marked as resolved.');
    }
}
