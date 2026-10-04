<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #2a1215;">
    <p>Dear {{ $booking->customer_name }},</p>
    <p>You asked for a link to your booking <strong>{{ $booking->confirmation_code }}</strong> at {{ config('app.name') }}.</p>
    <p><a href="{{ $viewUrl }}">View my booking</a></p>
    <p>This link is personal to you — please don't share it. If you did not ask for it, you can ignore this email; nobody can see your booking without it.</p>
</body>
</html>
