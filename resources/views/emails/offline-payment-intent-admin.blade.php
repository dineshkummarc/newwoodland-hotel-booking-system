<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #2a1215;">
    <h2>Booking awaiting {{ payment_provider_label($payment->provider) }}</h2>
    <p>The room is held until <strong>{{ $holdUntil->timezone(config('app.timezone'))->format('D, d M Y g:i A') }}</strong>.
        When the money arrives, confirm it on the booking page ("Confirm Bank Transfer Received"). If the booking looks bogus, cancel it to release the room.</p>
    <table cellpadding="6">
        <tr><td><strong>Reference</strong></td><td>{{ $booking->confirmation_code }}</td></tr>
        <tr><td><strong>Guest</strong></td><td>{{ $booking->customer_name }} ({{ $booking->email }}, {{ $booking->phone }})</td></tr>
        <tr><td><strong>Room</strong></td><td>{{ $booking->roomNames() }}</td></tr>
        <tr><td><strong>Stay</strong></td><td>{{ $booking->check_in->format('D, d M Y') }} – {{ $booking->check_out->format('D, d M Y') }}</td></tr>
        <tr><td><strong>Expected amount</strong></td><td>{{ npr($payment->amount) }}</td></tr>
    </table>
    <p><a href="{{ route('admin.bookings.show', $booking) }}">Open the booking in the admin panel</a></p>
</body>
</html>
