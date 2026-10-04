@extends('layouts.app')

@section('title', 'Bank Transfer | Newwoodland Hotel & Lounge')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">One Step Left</span>
            <h1 class="font-heading">Pay by Bank Transfer</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Your room is held for {{ $booking->customer_name }} — transfer the amount below and our team will confirm your reservation once it arrives.
            </p>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="p-4 p-md-5 bg-white rounded-4 shadow-sm border">
                    <div class="text-center mb-4">
                        <span class="section-subtitle">Booking Reference</span>
                        <h2 class="font-heading text-emerald">{{ $booking->confirmation_code }}</h2>
                        <span class="status {{ $booking->status }}">{{ ucfirst($booking->status) }} — awaiting bank transfer</span>
                    </div>

                    <div class="alert alert-warning">
                        Your booking is <strong>not confirmed yet</strong>. It is held until
                        <strong>{{ $holdUntil->format('D, d M Y g:i A') }}</strong>; if we have not received your transfer by then, the room is released.
                    </div>

                    @unless($account['account_number'])
                        <div class="alert alert-info">
                            <i class="bi bi-bank me-1"></i>
                            Our team will send you the {{ $account['bank_name'] ?: 'bank' }} account details for this transfer shortly.
                            @if($hotelPhone ?? null)
                                You can also call us on <a href="tel:{{ $hotelPhoneRaw }}">{{ $hotelPhone }}</a>.
                            @endif
                        </div>
                    @endunless

                    <span class="section-subtitle d-block mb-2">Transfer To</span>
                    <table class="table">
                        @if($account['bank_name'])
                            <tr><td class="text-muted">Bank</td><td class="text-end fw-semibold">{{ $account['bank_name'] }}</td></tr>
                        @endif
                        @if($account['account_name'])
                            <tr><td class="text-muted">Account Name</td><td class="text-end fw-semibold">{{ $account['account_name'] }}</td></tr>
                        @endif
                        <tr><td class="text-muted">Account Number</td><td class="text-end fw-semibold text-break">{{ $account['account_number'] ?: 'Sent to you by the hotel' }}</td></tr>
                        @if($account['branch'])
                            <tr><td class="text-muted">Branch</td><td class="text-end fw-semibold">{{ $account['branch'] }}</td></tr>
                        @endif
                        <tr><td class="text-muted fs-5">Amount to Transfer</td><td class="text-end fw-bold fs-5 text-emerald">{{ npr($payment->amount) }}</td></tr>
                        @if((float) $payment->amount < (float) $booking->total_price)
                        <tr><td class="text-muted">Balance — Pay at Hotel</td><td class="text-end fw-semibold">{{ npr($booking->total_price - $payment->amount) }}</td></tr>
                        @endif
                        <tr><td class="text-muted">Payment Reference / Remarks</td><td class="text-end fw-bold text-break">{{ $booking->confirmation_code }}</td></tr>
                    </table>
                    <p>
                        Please write <strong>{{ $booking->confirmation_code }}</strong> in the transfer remarks so we can match your payment, and transfer the exact amount.
                    </p>
                    @if($account['instructions'])
                        <p>{{ $account['instructions'] }}</p>
                    @endif
                    @if($account['qr_url'])
                        <div class="text-center my-4">
                            <span class="section-subtitle d-block mb-2">Or Scan to Pay</span>
                            <img src="{{ $account['qr_url'] }}" alt="{{ $account['bank_name'] ?: 'Bank' }} payment QR code" class="img-fluid rounded-3 border p-2 bg-white" style="max-width: 240px;">
                        </div>
                    @endif

                    <table class="table mt-4">
                        <tr><td class="text-muted">Room</td><td class="text-end fw-semibold">{{ $booking->roomNames() }}</td></tr>
                        <tr><td class="text-muted">Check-in</td><td class="text-end fw-semibold">{{ $booking->checkInLabel() }}</td></tr>
                        <tr><td class="text-muted">Check-out</td><td class="text-end fw-semibold">{{ $booking->checkOutLabel() }}</td></tr>
                        <tr><td class="text-muted">Nights</td><td class="text-end fw-semibold">{{ $booking->nights }}</td></tr>
                        <tr><td class="text-muted">Guests</td><td class="text-end fw-semibold">{{ $booking->guests_label }}</td></tr>
                    </table>

                    <div class="text-center mt-4 d-print-none">
                        <a href="{{ route('booking.confirmation', $booking->confirmation_code) }}" class="btn-gold">
                            <i class="bi bi-receipt"></i> View My Booking
                        </a>
                        <div class="mt-3">
                            <a href="{{ route('booking.pay', $booking->confirmation_code) }}" class="small text-muted">Prefer to pay another way? Choose a different payment method</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
