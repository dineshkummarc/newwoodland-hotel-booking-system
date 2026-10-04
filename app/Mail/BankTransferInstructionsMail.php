<?php

namespace App\Mail;

use App\Mail\Concerns\QueuedDelivery;
use App\Models\Payment;
use App\Services\BankTransferPaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the guest when they choose Bank Transfer: their booking
 * reference (to write in the transfer remarks), the amount, the account
 * details, how long the room is held, and their personal booking link —
 * so nothing depends on them keeping the browser page open.
 */
class BankTransferInstructionsMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable, QueuedDelivery, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function build(): self
    {
        $booking = $this->payment->booking;

        return $this
            ->subject('Bank transfer details for booking '.$booking->confirmation_code.' (not yet confirmed)')
            ->view('emails.bank-transfer-instructions', [
                'booking' => $booking,
                'account' => app(BankTransferPaymentService::class)->accountDetails(),
                'holdUntil' => $this->payment->created_at->copy()->addHours((int) config('bank_transfer.hold_hours', 24)),
            ]);
    }
}
