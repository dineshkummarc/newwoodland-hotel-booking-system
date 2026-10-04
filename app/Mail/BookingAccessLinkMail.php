<?php

namespace App\Mail;

use App\Mail\Concerns\QueuedDelivery;
use App\Models\Booking;
use App\Support\BookingAccess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "Email me my booking link": the signed link to the booking's own page,
 * sent only to the address stored on the booking.
 */
class BookingAccessLinkMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable, QueuedDelivery, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function build(): self
    {
        return $this
            ->subject('Your booking link - '.$this->booking->confirmation_code)
            ->view('emails.booking-access-link', [
                'viewUrl' => BookingAccess::viewUrl($this->booking),
            ]);
    }
}
