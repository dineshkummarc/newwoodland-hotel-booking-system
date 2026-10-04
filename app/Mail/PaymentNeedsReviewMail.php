<?php

namespace App\Mail;

use App\Mail\Concerns\QueuedDelivery;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Staff alert: a payment needs a human decision (money received for a
 * booking that could not be confirmed, a duplicate payment, or an online
 * attempt the gateway cannot be asked about). Sent once per flag — see
 * Payment::flagForReview() and BookingService::flagPaymentForReview().
 */
class PaymentNeedsReviewMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable, QueuedDelivery, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function build(): self
    {
        $booking = $this->payment->booking;

        return $this
            ->subject('Action needed: payment review for booking '.($booking?->confirmation_code ?? '#'.$this->payment->booking_id))
            ->view('emails.payment-needs-review', [
                'payment' => $this->payment,
                'booking' => $booking,
            ]);
    }
}
