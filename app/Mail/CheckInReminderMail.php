<?php

namespace App\Mail;

use App\Mail\Concerns\QueuedDelivery;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CheckInReminderMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable, QueuedDelivery, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function build(): self
    {
        return $this
            ->subject('See you soon — your stay on '.$this->booking->check_in->format('D, d M').' ('.$this->booking->confirmation_code.')')
            ->view('emails.checkin-reminder');
    }
}
