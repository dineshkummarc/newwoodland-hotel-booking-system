<?php

namespace App\Mail;

use App\Mail\Concerns\QueuedDelivery;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingCancelledAdminMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable, QueuedDelivery, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function build(): self
    {
        return $this
            ->subject('Booking Cancelled by Guest - '.$this->booking->confirmation_code)
            ->view('emails.booking-cancelled-admin', [
                'payment' => $this->booking->latestPayment(),
            ]);
    }
}
