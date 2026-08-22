<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1c2a24; background: #faf8f2; padding: 24px; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid #eee; }
        .header { background: #0f6b45; color: #fff; padding: 24px; text-align: center; }
        .code { display: inline-block; background: #d4af37; color: #201a06; font-weight: bold; padding: 6px 16px; border-radius: 20px; margin-top: 8px; }
        .body { padding: 24px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        td.label { color: #6b7a74; width: 40%; }
        td.value { font-weight: 600; text-align: right; }
        .total { font-size: 1.2em; color: #0f6b45; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h2 style="margin:0;">New Woodland Hotel</h2>
            <p style="margin:4px 0 0;">Your booking is confirmed as pending review</p>
            <div class="code">{{ $booking->confirmation_code }}</div>
        </div>
        <div class="body">
            <p>Dear {{ $booking->customer_name }},</p>
            <p>Thank you for booking with New Woodland Hotel. Here are your reservation details:</p>
            <table>
                <tr><td class="label">Room</td><td class="value">{{ $booking->room->name }}</td></tr>
                <tr><td class="label">Check-in</td><td class="value">{{ $booking->check_in->format('D, d M Y') }}</td></tr>
                <tr><td class="label">Check-out</td><td class="value">{{ $booking->check_out->format('D, d M Y') }}</td></tr>
                <tr><td class="label">Nights</td><td class="value">{{ $booking->nights }}</td></tr>
                <tr><td class="label">Guests</td><td class="value">{{ $booking->guests_label }}</td></tr>
                <tr><td class="label">Nightly Rate</td><td class="value">{{ npr($booking->nightly_rate) }}</td></tr>
                <tr><td class="label total">Total</td><td class="value total">{{ npr($booking->total_price) }}</td></tr>
                <tr><td class="label">Status</td><td class="value">{{ ucfirst($booking->status) }}</td></tr>
            </table>
            <p style="margin-top:20px;">You can view your booking anytime using your confirmation code at:<br>
            <a href="{{ route('booking.confirmation', $booking->confirmation_code) }}">{{ route('booking.confirmation', $booking->confirmation_code) }}</a></p>
            <p>We look forward to welcoming you to New Woodland Hotel.</p>
        </div>
    </div>
</body>
</html>
