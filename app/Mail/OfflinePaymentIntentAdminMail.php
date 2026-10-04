<?php

namespace App\Mail;

use App\Mail\Concerns\QueuedDelivery;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Staff notice: a guest chose an offline payment method (bank transfer),
 * so a room is now held for up to the method's hold window while staff
 * watch for the money — or release the hold if the booking looks bogus.
 */
class OfflinePaymentIntentAdminMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable, QueuedDelivery, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function build(): self
    {
        $booking = $this->payment->booking;

        return $this
            ->subject('Awaiting '.payment_provider_label($this->payment->provider).': booking '.$booking->confirmation_code)
            ->view('emails.offline-payment-intent-admin', [
                'booking' => $booking,
                'holdUntil' => $this->payment->created_at->copy()->addHours((int) (Payment::offlineHoldHours()[$this->payment->provider] ?? 24)),
            ]);
    }
}
