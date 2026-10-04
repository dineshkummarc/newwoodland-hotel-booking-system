<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoiceNumber }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 20px; margin: 0 0 2px; color: #1f4d3a; }
        h2 { font-size: 12px; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: .5px; color: #1f4d3a; }
        .muted { color: #666; }
        .header td { vertical-align: top; }
        table { width: 100%; border-collapse: collapse; }
        .lines th { text-align: left; background: #f1f4f2; padding: 6px; font-size: 10.5px; text-transform: uppercase; }
        .lines td { padding: 6px; border-bottom: 1px solid #e5e5e5; }
        .num { text-align: right; white-space: nowrap; }
        .totals td { padding: 4px 6px; }
        .totals .grand td { font-size: 14px; font-weight: bold; border-top: 2px solid #1f4d3a; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 3px; background: #eef3f0; font-weight: bold; text-transform: capitalize; }
        .footer { margin-top: 26px; font-size: 10px; color: #666; border-top: 1px solid #e5e5e5; padding-top: 8px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width:60%">
                <h1>{{ $hotel->hotel_name ?: config('app.name') }}</h1>
                @if($hotel->site_address)<div>{{ $hotel->site_address }}</div>@endif
                @if($hotel->site_phone)<div>Phone: {{ $hotel->site_phone }}</div>@endif
                @if($hotel->site_email)<div>Email: {{ $hotel->site_email }}</div>@endif
                @if($hotel->pan_vat_number)<div>PAN/VAT No.: <strong>{{ $hotel->pan_vat_number }}</strong></div>@endif
            </td>
            <td class="num">
                <div style="font-size:16px;font-weight:bold">{{ $booking->amountPaid() > 0 ? 'INVOICE / RECEIPT' : 'BOOKING STATEMENT' }}</div>
                <div>No. <strong>{{ $invoiceNumber }}</strong></div>
                <div>Date: {{ $issuedAt->format('d M Y') }}</div>
                <div>Booking: <strong>{{ $booking->confirmation_code }}</strong></div>
                <div style="margin-top:4px"><span class="status">{{ str_replace('_', ' ', $booking->status) }}</span></div>
            </td>
        </tr>
    </table>

    <table style="margin-top:16px">
        <tr>
            <td style="width:50%;vertical-align:top">
                <h2>Guest</h2>
                <div><strong>{{ $booking->customer_name }}</strong></div>
                <div>{{ $booking->email }}</div>
                <div>{{ $booking->phone }}</div>
                @if($booking->country)<div>{{ $booking->country }}</div>@endif
            </td>
            <td style="vertical-align:top">
                <h2>Stay</h2>
                @foreach($booking->roomLines() as $roomLine)
                <div>Room: <strong>{{ $roomLine['room']?->name ?? '—' }}</strong>@if($roomLine['room']?->room_number) (Room {{ $roomLine['room']->room_number }})@endif · {{ $roomLine['guests_label'] }}</div>
                @endforeach
                <div>Check-in: {{ $booking->checkInLabel() }}</div>
                <div>Check-out: {{ $booking->checkOutLabel() }}</div>
                <div>Nights: {{ $booking->nights }}@unless($booking->isMultiRoom()) · Guests: {{ $booking->guests_label }}@endunless</div>
            </td>
        </tr>
    </table>

    <h2>Charges</h2>
    <table class="lines">
        <thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @foreach($booking->rateLines() as $line)
            <tr>
                <td>Room — {{ $booking->room?->name ?? 'Accommodation' }}@if($booking->room?->room_number) ({{ $booking->room->room_number }})@endif @if($booking->hasVariableRates()) <span class="muted">({{ $line['label'] }})</span>@endif</td>
                <td class="num">{{ $line['nights'] }} night(s)</td>
                <td class="num">{{ npr($line['rate']) }}</td>
                <td class="num">{{ npr($line['amount']) }}</td>
            </tr>
            @endforeach
            @foreach($booking->additionalRooms as $extra)
            <tr>
                <td>Room — {{ $extra->room?->name ?? 'Accommodation' }}@if($extra->room?->room_number) ({{ $extra->room->room_number }})@endif <span class="muted">({{ $extra->guests_label }})</span></td>
                <td class="num">{{ $booking->nights }} night(s)</td>
                <td class="num">{{ $extra->price_breakdown ? 'varies' : npr($extra->nightly_rate) }}</td>
                <td class="num">{{ npr($extra->subtotal) }}</td>
            </tr>
            @endforeach
            @if($booking->hasDiscount())
            <tr>
                <td>First-booking discount ({{ percent($booking->discount_percent) }})</td>
                <td></td><td></td>
                <td class="num">− {{ npr($booking->discount_amount) }}</td>
            </tr>
            @endif
            @foreach($booking->charges as $charge)
            <tr>
                <td>{{ $charge->description }} <span class="muted">({{ $charge->categoryLabel() }}, {{ $charge->charged_at->format('d M') }})</span></td>
                <td class="num">{{ $charge->quantity }}</td>
                <td class="num">{{ npr($charge->unit_price) }}</td>
                <td class="num">{{ npr($charge->amount) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="width:55%;margin-left:45%;margin-top:8px">
        <tr><td>Room total</td><td class="num">{{ npr($booking->total_price) }}</td></tr>
        @if($booking->charges->isNotEmpty())
        <tr><td>Extra charges</td><td class="num">{{ npr($booking->chargesTotal()) }}</td></tr>
        @endif
        <tr class="grand"><td>Total</td><td class="num">{{ npr($booking->grandTotal()) }}</td></tr>
        <tr><td>Paid</td><td class="num">{{ npr($booking->amountPaid()) }}</td></tr>
        @if($booking->refundedAmount() > 0)
        <tr><td>Refunded</td><td class="num">− {{ npr($booking->refundedAmount()) }}</td></tr>
        @endif
        @if($booking->status !== \App\Models\Booking::STATUS_CANCELLED)
        <tr><td><strong>Balance due</strong></td><td class="num"><strong>{{ npr(max(0, $booking->outstandingBalance())) }}</strong></td></tr>
        @endif
        @if($booking->deposit_percent)
        <tr><td colspan="2" class="muted">Guest chose to pay {{ $booking->deposit_percent }}% in advance; balance — Pay at Hotel.</td></tr>
        @endif
    </table>

    @if($booking->payments->isNotEmpty())
    <h2>Payments Received</h2>
    <table class="lines">
        <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @foreach($booking->payments as $payment)
            <tr>
                <td>{{ $payment->paid_at?->format('d M Y, g:i A') ?? '—' }}</td>
                <td>{{ payment_provider_label($payment->provider) }}</td>
                <td>{{ $payment->gatewayTransactionId() ?? $payment->reference_id ?? '—' }}</td>
                <td class="num">{{ npr($payment->amount) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if($booking->refunds->isNotEmpty())
    <h2>Refunds</h2>
    <table class="lines">
        <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @foreach($booking->refunds as $refund)
            <tr>
                <td>{{ $refund->refunded_at?->format('d M Y') ?? '—' }}</td>
                <td>{{ $refund->methodLabel() }}</td>
                <td>{{ $refund->reference ?: '—' }}</td>
                <td class="num">{{ npr($refund->amount) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        Amounts in Nepalese Rupees (NPR). This document is generated from the hotel's booking records.
        {{-- The PAN/VAT number (Admin → Settings) is printed in the header once the hotel provides it. No tax breakdown is calculated: rates/treatment are not confirmed. --}}
        Thank you for staying with {{ $hotel->hotel_name ?: config('app.name') }}.
    </div>
</body>
</html>
