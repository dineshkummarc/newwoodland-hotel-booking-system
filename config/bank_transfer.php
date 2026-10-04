<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bank Transfer (manual payment) Configuration
    |--------------------------------------------------------------------------
    |
    | Unlike eSewa/Khalti/Fonepay there is no gateway to verify with: the guest
    | transfers money to the hotel's own bank account and staff confirm receipt
    | in the admin (Booking → Record Bank Transfer).
    |
    | The account details themselves (bank name, account name, account
    | number, branch) are NOT here — they are ordinary hotel data, edited by
    | an admin on the existing Admin → Settings screen and stored on the
    | `settings` table (see App\Services\BankTransferPaymentService, which
    | reads them via SettingsService, same as every other Setting). Only
    | true deployment configuration lives in this file/.env: whether the
    | method is offered at all, and how long a booking is held for it.
    | Bank Transfer is only offered at checkout when BANK_TRANSFER_ENABLED=true
    | AND the admin has actually filled in an account name and number.
    |
    */

    // Optional free-text shown to the guest under the account details. Has
    // no Settings-table equivalent, so it stays here rather than in .env
    // duplicating a field that doesn't exist.
    'instructions' => env('BANK_TRANSFER_INSTRUCTIONS'),

    // How long a booking stays held for a guest who chose Bank Transfer,
    // instead of the 30-minute hold that applies to online gateways (a bank
    // transfer can't be completed in one sitting). After this the booking is
    // released like any other abandoned one.
    'hold_hours' => (int) env('BANK_TRANSFER_HOLD_HOURS', 24),

];
