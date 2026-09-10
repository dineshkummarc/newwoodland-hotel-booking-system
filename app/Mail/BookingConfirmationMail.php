<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function build(): self
    {
        return $this
            ->subject('Booking Confirmation - '.$this->booking->confirmation_code)
            ->view('emails.booking-confirmation', [
                'payment' => $this->booking->latestPayment(),
            ]);
    }
}
