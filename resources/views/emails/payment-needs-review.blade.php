<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #2a1215;">
    <h2>Payment needs staff review</h2>
    <p>{{ $payment->review_reason }}</p>
    <table cellpadding="6">
        @if($booking)
        <tr><td><strong>Booking</strong></td><td>{{ $booking->confirmation_code }} ({{ ucfirst(str_replace('_', ' ', $booking->status)) }})</td></tr>
        <tr><td><strong>Guest</strong></td><td>{{ $booking->customer_name }} ({{ $booking->email }}, {{ $booking->phone }})</td></tr>
        <tr><td><strong>Stay</strong></td><td>{{ $booking->check_in->format('D, d M Y') }} – {{ $booking->check_out->format('D, d M Y') }}</td></tr>
        @endif
        <tr><td><strong>Payment</strong></td><td>{{ payment_provider_label($payment->provider) }} — {{ npr($payment->amount) }} — {{ ucfirst($payment->status) }}</td></tr>
        <tr><td><strong>Our reference</strong></td><td>{{ $payment->transaction_uuid }}</td></tr>
        @if($payment->reference_id)
        <tr><td><strong>Gateway reference</strong></td><td>{{ $payment->reference_id }}</td></tr>
        @endif
    </table>
    @if($booking)
    <p><a href="{{ route('admin.bookings.show', $booking) }}">Open the booking in the admin panel</a> — mark the review resolved there once it has been handled (refund, re-confirmation or rebooking).</p>
    @endif
</body>
</html>
