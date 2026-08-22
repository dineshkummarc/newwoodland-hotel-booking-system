<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #1c2a24;">
    <h2>New Booking Received</h2>
    <p><strong>Confirmation code:</strong> {{ $booking->confirmation_code }}</p>
    <table cellpadding="6">
        <tr><td><strong>Guest</strong></td><td>{{ $booking->customer_name }} ({{ $booking->email }}, {{ $booking->phone }})</td></tr>
        <tr><td><strong>Room</strong></td><td>{{ $booking->room->name }}</td></tr>
        <tr><td><strong>Check-in</strong></td><td>{{ $booking->check_in->format('D, d M Y') }}</td></tr>
        <tr><td><strong>Check-out</strong></td><td>{{ $booking->check_out->format('D, d M Y') }}</td></tr>
        <tr><td><strong>Nights</strong></td><td>{{ $booking->nights }}</td></tr>
        <tr><td><strong>Guests</strong></td><td>{{ $booking->guests_label }}</td></tr>
        <tr><td><strong>Total</strong></td><td>{{ npr($booking->total_price) }}</td></tr>
        <tr><td><strong>Special requests</strong></td><td>{{ $booking->special_requests ?: '—' }}</td></tr>
        <tr><td><strong>Status</strong></td><td>{{ ucfirst($booking->status) }}</td></tr>
    </table>
</body>
</html>
