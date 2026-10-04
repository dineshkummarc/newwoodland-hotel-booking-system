<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Booking;
use App\Services\Concerns\RecordsOfflinePaymentIntent;
use Illuminate\Support\Facades\Storage;

/**
 * Bank Transfer: the manual, offline payment method. There is no gateway to
 * verify with, so a payment is never marked paid here — initiate() only
 * records the guest's intent (a pending Payment, which also extends the
 * booking's hold, see Payment::scopeActiveOfflineHold) and hands back
 * the hotel's account details. Staff confirm receipt from the admin, which
 * completes this same Payment through BookingService::recordManualPayment().
 *
 * The account details themselves (bank name, account name, account number,
 * branch, QR image) are NOT environment configuration — they are ordinary,
 * admin-editable hotel data that already lives on the `settings` table (see
 * Admin → Settings → "Bank Transfer Details") and is read the same way every
 * other Setting is read elsewhere in the app: through SettingsService, which
 * is what the admin Settings screen invalidates on save. Only the things
 * that genuinely are deployment configuration — whether this method is
 * offered at all, and how long a booking is held for it — come from .env
 * (see config/bank_transfer.php).
 */
class BankTransferPaymentService implements PaymentGatewayInterface
{
    use RecordsOfflinePaymentIntent;

    public function __construct(protected SettingsService $settings) {}

    /**
     * Offered whenever the method is switched on — even before the hotel has
     * entered its account number. Until then the guest is told the hotel
     * will send the account details (see hasAccountDetails()), and staff
     * follow up; once the number is in Settings it is shown automatically.
     */
    public function isAvailable(): bool
    {
        return (bool) config('payments.methods.bank_transfer.enabled');
    }

    /**
     * Whether there is an account number the guest can transfer to right
     * now. Every other detail (account name, branch, QR) is optional.
     */
    public function hasAccountDetails(): bool
    {
        return filled($this->settings->current()->bank_account_number);
    }

    /**
     * @return array{payment: \App\Models\Payment, created: bool}
     *
     * @throws \App\Services\Payments\HoldExpiredException if the booking's hold has lapsed
     * @throws \RuntimeException if the booking is already paid
     */
    public function initiate(Booking $booking): array
    {
        return $this->recordPendingIntent($booking, 'bank_transfer', 'BANK');
    }

    /**
     * The hotel's own account details shown to the guest, straight from the
     * same admin-editable Settings record every other page reads. Public
     * information by nature (it is what the guest transfers to), not a
     * secret. "instructions" is the one piece here that genuinely has no
     * Settings column — optional free text configured via .env instead.
     *
     * @return array<string, ?string>
     */
    public function accountDetails(): array
    {
        $setting = $this->settings->current();

        return [
            'bank_name' => $setting->bank_name,
            'account_name' => $setting->bank_account_name,
            'account_number' => $setting->bank_account_number,
            'branch' => $setting->bank_branch,
            'qr_url' => $setting->bank_qr_path ? Storage::disk('public')->url($setting->bank_qr_path) : null,
            'instructions' => config('bank_transfer.instructions'),
        ];
    }
}
