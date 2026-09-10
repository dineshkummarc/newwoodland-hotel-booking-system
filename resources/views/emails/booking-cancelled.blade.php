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
        .note { background: #fdf6e3; border: 1px solid #f0e1b0; border-radius: 8px; padding: 12px 16px; margin-top: 20px; font-size: 0.95em; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h2 style="margin:0;">Newwoodland Hotel and Lounge</h2>
            <p style="margin:4px 0 0;">Your booking has been cancelled</p>
            <div class="code">{{ $booking->confirmation_code }}</div>
        </div>
        <div class="body">
            <p>Dear {{ $booking->customer_name }},</p>
            <p>This confirms that your reservation below has been cancelled as requested.</p>
            <table>
                <tr><td class="label">Room</td><td class="value">{{ $booking->room->name }}</td></tr>
                <tr><td class="label">Check-in</td><td class="value">{{ $booking->check_in->format('D, d M Y') }}</td></tr>
                <tr><td class="label">Check-out</td><td class="value">{{ $booking->check_out->format('D, d M Y') }}</td></tr>
                <tr><td class="label">Total</td><td class="value">{{ npr($booking->total_price) }}</td></tr>
                <tr><td class="label">Cancelled At</td><td class="value">{{ $booking->cancelled_at?->format('D, d M Y g:i A') }}</td></tr>
            </table>

            @if($payment && $payment->status === 'completed')
            <div class="note">
                Our records show a payment of {{ npr($payment->amount) }} was received for this booking
                (ref {{ $payment->reference_id }}). Our team will contact you shortly regarding your refund —
                this is not processed automatically.
            </div>
            @endif

            <p style="margin-top:20px;">If you did not request this cancellation, please contact us immediately.</p>
        </div>
    </div>
</body>
</html>
