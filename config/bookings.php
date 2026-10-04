<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Check-in Reminder Email
    |--------------------------------------------------------------------------
    |
    | bookings:send-checkin-reminders (scheduled hourly in routes/console.php)
    | emails confirmed guests whose check-in date is within `days_before`
    | days. Each booking is reminded at most once (bookings.reminder_sent_at).
    | Bookings made less than `skip_if_booked_within_hours` ago are skipped —
    | they have only just received their confirmation email.
    |
    */

    'checkin_reminder' => [
        'enabled' => (bool) env('CHECKIN_REMINDER_ENABLED', true),
        'days_before' => (int) env('CHECKIN_REMINDER_DAYS_BEFORE', 1),
        'skip_if_booked_within_hours' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest Photo ID
    |--------------------------------------------------------------------------
    |
    | Hotel policy (client-confirmed): every guest must present a photo ID
    | at check-in — Citizenship, Student ID or Office/Company ID (see
    | App\Models\GuestDocument::TYPES).
    |
    | `checkin_requires_photo_id`: staff must confirm the ID was presented
    | (or have recorded one) before a booking can be checked in.
    | `guest_documents.enabled`: staff can record the ID type/number/country
    | on the booking page (number encrypted at rest). `allow_upload` would
    | let them attach a scan — hotel policy is that ID scans are NOT stored,
    | so it is deliberately not read from .env and stays off.
    |
    */

    'checkin_requires_photo_id' => (bool) env('CHECKIN_REQUIRES_PHOTO_ID', true),

    'guest_documents' => [
        'enabled' => (bool) env('GUEST_DOCUMENTS_ENABLED', true),
        'allow_upload' => false,
        'max_upload_kb' => 5120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cancellation After Arrival
    |--------------------------------------------------------------------------
    |
    | Hotel policy (client-confirmed): a guest who has already arrived
    | (checked in) and then cancels the stay pays a cancellation charge of
    | this % of the booking amount; extra charges already incurred remain
    | payable. Cancellations BEFORE arrival are unchanged and use the
    | "Cancellation deduction" in Admin → Settings.
    |
    */

    'after_arrival_cancellation_percent' => 50,

    /*
    |--------------------------------------------------------------------------
    | Online Payment Reconciliation
    |--------------------------------------------------------------------------
    |
    | See App\Services\Payments\PaymentReconciler and `payments:reconcile`.
    | `online_payment_grace_minutes`: an unpaid booking is not expired while
    | an eSewa/Fonepay attempt younger than this exists (the guest may be on
    | the gateway's page). `online_payment_max_unresolved_hours`: after this,
    | an attempt the gateway still can't settle is flagged for staff and the
    | booking released. `online_payment_reconcile_days`: still-pending
    | attempts older than this are flagged for manual settlement.
    |
    */

    'online_payment_grace_minutes' => 15,
    'online_payment_max_unresolved_hours' => 24,
    'online_payment_reconcile_days' => 3,

    /*
    |--------------------------------------------------------------------------
    | Unpaid Hold Limits (abuse prevention)
    |--------------------------------------------------------------------------
    |
    | A guest (same email, phone or account) may have at most this many ROOMS
    | held by UNPAID bookings at once on the public site. The default equals
    | the largest single booking (StoreBookingRequest::MAX_ROOMS = 5), so a
    | family or group can always book in one go; it only stops one person
    | stacking unpaid holds across the hotel's 9 rooms. A guest with no
    | current holds is never refused. Staff (walk-in) bookings are not limited.
    |
    */

    'max_unpaid_held_rooms_per_guest' => (int) env('BOOKING_MAX_UNPAID_HELD_ROOMS_PER_GUEST', 5),

    /*
    |--------------------------------------------------------------------------
    | Booking Date Limits
    |--------------------------------------------------------------------------
    |
    | Technical safety limits on what the booking form accepts (they also
    | keep totals well inside the database's money columns). Not a hotel
    | policy yet — CLIENT DECISION REQUIRED: confirm how far ahead guests may
    | book and the longest stay bookable online, then set these in .env.
    |
    */

    'max_advance_days' => (int) env('BOOKING_MAX_ADVANCE_DAYS', 730),
    'max_nights' => (int) env('BOOKING_MAX_NIGHTS', 90),

];
