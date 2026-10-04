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
    </style>
</head>
<body>
    @php $hotel = app(\App\Services\SettingsService::class)->current(); @endphp
    <div class="card">
        <div class="header">
            <h2 style="margin:0;">{{ $hotel->hotel_name ?: 'Newwoodland Hotel & Lounge' }}</h2>
            <p style="margin:4px 0 0;">We look forward to welcoming you</p>
            <div class="code">{{ $booking->confirmation_code }}</div>
        </div>
        <div class="body">
            <p>Dear {{ $booking->customer_name }},</p>
            <p>This is a friendly reminder of your upcoming stay with us.</p>
            <table>
                <tr><td class="label">Room</td><td class="value">{{ $booking->roomNames() }}</td></tr>
                <tr><td class="label">Check-in</td><td class="value">{{ $booking->check_in->format('D, d M Y') }}@if($hotel->checkin_time) from {{ $hotel->checkin_time }}@endif</td></tr>
                <tr><td class="label">Check-out</td><td class="value">{{ $booking->check_out->format('D, d M Y') }}@if($hotel->checkout_time) by {{ $hotel->checkout_time }}@endif</td></tr>
                <tr><td class="label">Guests</td><td class="value">{{ $booking->guests_label }}</td></tr>
                @if($booking->outstandingBalance() > 0)
                <tr><td class="label">Balance — Pay at Hotel</td><td class="value">{{ npr($booking->outstandingBalance()) }}</td></tr>
                @endif
            </table>
            @if($hotel->site_address)
            <p style="margin-top:20px;"><strong>Address:</strong> {{ $hotel->site_address }}</p>
            @endif
            @if($hotel->site_phone)
            <p>Questions or running late? Call us on {{ $hotel->site_phone }}.</p>
            @endif
            <p>Your booking details: <a href="{{ $booking->viewUrl() }}">View my booking</a></p>
        </div>
    </div>
</body>
</html>
