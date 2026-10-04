<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #2a1215; background: #faf5ee; padding: 24px; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid #eee; }
        .header { background: #6b1f2a; color: #fff; padding: 24px; text-align: center; }
        .code { display: inline-block; background: #d4af37; color: #201a06; font-weight: bold; padding: 6px 16px; border-radius: 20px; margin-top: 8px; }
        .body { padding: 24px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        td.label { color: #7a6763; width: 40%; }
        td.value { font-weight: 600; text-align: right; }
        .total { font-size: 1.2em; color: #6b1f2a; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h2 style="margin:0;">Newwoodland Hotel & Lounge</h2>
            <p style="margin:4px 0 0;">Your payment was received and your booking is confirmed</p>
            <div class="code">{{ $booking->confirmation_code }}</div>
        </div>
        <div class="body">
            <p>Dear {{ $booking->customer_name }},</p>
            <p>Thank you for booking with Newwoodland Hotel & Lounge. Here are your reservation details:</p>
            <table>
                <tr><td class="label">Room</td><td class="value">{{ $booking->roomNames() }}</td></tr>
                <tr><td class="label">Check-in</td><td class="value">{{ $booking->checkInLabel() }}</td></tr>
                <tr><td class="label">Check-out</td><td class="value">{{ $booking->checkOutLabel() }}</td></tr>
                <tr><td class="label">Nights</td><td class="value">{{ $booking->nights }}</td></tr>
                <tr><td class="label">Guests</td><td class="value">{{ $booking->guests_label }}</td></tr>
                <tr><td class="label">Nightly Rate</td><td class="value">{{ npr($booking->nightly_rate) }}</td></tr>
                @if($booking->hasDiscount())
                <tr><td class="label">First-booking Discount</td><td class="value">− {{ npr($booking->discount_amount) }}</td></tr>
                @endif
                <tr><td class="label">Total</td><td class="value">{{ npr($booking->total_price) }}</td></tr>
                <tr><td class="label total">Amount Paid</td><td class="value total">{{ npr($booking->amountPaid()) }}</td></tr>
                @if($booking->outstandingBalance() > 0)
                <tr><td class="label">Balance — Pay at Hotel</td><td class="value">{{ npr($booking->outstandingBalance()) }}</td></tr>
                @endif
                @if($payment ?? null)
                <tr><td class="label">Payment Method</td><td class="value">{{ payment_provider_label($payment->provider) }}</td></tr>
                <tr><td class="label">Transaction Ref</td><td class="value">{{ $payment->reference_id }}</td></tr>
                @if($payment->gatewayTransactionId())
                <tr><td class="label">{{ payment_provider_label($payment->provider) }} Transaction ID</td><td class="value">{{ $payment->gatewayTransactionId() }}</td></tr>
                @endif
                @endif
                <tr><td class="label">Status</td><td class="value">{{ ucfirst($booking->status) }}</td></tr>
            </table>
            <p style="margin-top:20px;">View your booking anytime with this personal link (please don't share it):<br>
            <a href="{{ $booking->viewUrl() }}">View my booking</a></p>
            <p>Download your invoice / receipt (PDF): <a href="{{ $booking->invoiceUrl() }}">Download invoice</a></p>
            <p>Please bring a valid photo ID for check-in: {{ collect(\App\Models\GuestDocument::TYPES)->values()->join(', ', ' or ') }}.</p>
            @php $beforeArrival = app(\App\Services\BookingService::class)->cancellationDeductionPercent(); @endphp
            <p>Cancellation policy: {{ $beforeArrival ? $beforeArrival.'% of the booking amount is deducted before arrival' : 'free before arrival' }};
            {{ config('bookings.after_arrival_cancellation_percent') }}% of the booking amount is charged if the stay is cancelled after check-in.</p>
            @if($booking->isGuestBooking())
            <p>Need to cancel? Use this secure link: <a href="{{ $booking->guestCancellationUrl() }}">Cancel my booking</a></p>
            @endif
            <p>We look forward to welcoming you to Newwoodland Hotel & Lounge.</p>
        </div>
    </div>
</body>
</html>
