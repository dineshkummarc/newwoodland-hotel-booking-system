<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #2a1215;">
    <h2>Booking Cancelled by Guest</h2>
    <p><strong>Confirmation code:</strong> {{ $booking->confirmation_code }}</p>
    <table cellpadding="6">
        <tr><td><strong>Guest</strong></td><td>{{ $booking->customer_name }} ({{ $booking->email }}, {{ $booking->phone }})</td></tr>
        <tr><td><strong>Room</strong></td><td>{{ $booking->room->name }}</td></tr>
        <tr><td><strong>Check-in</strong></td><td>{{ $booking->check_in->format('D, d M Y') }}</td></tr>
        <tr><td><strong>Check-out</strong></td><td>{{ $booking->check_out->format('D, d M Y') }}</td></tr>
        <tr><td><strong>Total</strong></td><td>{{ npr($booking->total_price) }}</td></tr>
        <tr><td><strong>Cancelled At</strong></td><td>{{ $booking->cancelled_at?->format('D, d M Y g:i A') }}</td></tr>
        @if($payment ?? null)
        <tr><td><strong>Payment</strong></td><td>{{ ucfirst($payment->status) }} — Ref {{ $payment->reference_id ?? '—' }} — {{ npr($payment->amount) }}</td></tr>
        @if($payment->status === 'completed')
        <tr><td colspan="2" style="color:#a02a2a;"><strong>Action needed:</strong> this booking was paid — process a manual refund if applicable.</td></tr>
        @endif
        @endif
    </table>
</body>
</html>
