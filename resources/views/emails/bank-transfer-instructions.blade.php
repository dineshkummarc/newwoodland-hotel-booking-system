<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #2a1215;">
    <p>Dear {{ $booking->customer_name }},</p>
    <p>Thank you for choosing {{ config('app.name') }}. Your booking is <strong>not confirmed yet</strong>: it is held for you until
        <strong>{{ $holdUntil->timezone(config('app.timezone'))->format('D, d M Y g:i A') }} (Nepal time)</strong> while we wait for your bank transfer.
        If we have not received it by then, the room is released.</p>
    <table cellpadding="6">
        <tr><td><strong>Booking reference</strong></td><td><strong>{{ $booking->confirmation_code }}</strong> — write this in the transfer remarks</td></tr>
        <tr><td><strong>Amount to transfer</strong></td><td>{{ npr($payment->amount) }}</td></tr>
        @if($account['bank_name'])<tr><td><strong>Bank</strong></td><td>{{ $account['bank_name'] }}</td></tr>@endif
        @if($account['account_name'])<tr><td><strong>Account name</strong></td><td>{{ $account['account_name'] }}</td></tr>@endif
        <tr><td><strong>Account number</strong></td><td>{{ $account['account_number'] ?: 'Our team will send it to you shortly' }}</td></tr>
        @if($account['branch'])<tr><td><strong>Branch</strong></td><td>{{ $account['branch'] }}</td></tr>@endif
        <tr><td><strong>Room</strong></td><td>{{ $booking->roomNames() }}</td></tr>
        <tr><td><strong>Stay</strong></td><td>{{ $booking->check_in->format('D, d M Y') }} – {{ $booking->check_out->format('D, d M Y') }}</td></tr>
    </table>
    @if($account['instructions'])
    <p>{{ $account['instructions'] }}</p>
    @endif
    <p>We will email you as soon as your transfer is received and the booking is confirmed.</p>
    <p>View your booking (personal link — please don't share it): <a href="{{ $booking->viewUrl() }}">View my booking</a></p>
</body>
</html>
