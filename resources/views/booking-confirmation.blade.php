@extends('layouts.app')

@section('title', 'Booking Confirmation | Newwoodland Hotel & Lounge')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Booking Confirmation</span>
            <h1 class="font-heading">Thank You, {{ $booking->customer_name }}!</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">Your reservation reference is below. Please save it for your records.</p>
        </div>
    </div>
</section>

<section class="py-5 bg-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="p-4 p-md-5 bg-light rounded-4 shadow-sm border">
                    @php
                        $latestPayment = $booking->latestPayment();
                        $awaitingOffline = $latestPayment && $latestPayment->status === 'pending' && in_array($latestPayment->provider, ['cash', 'bank_transfer'], true);
                    @endphp
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif
                    <div class="text-center mb-4">
                        <span class="section-subtitle">Confirmation Code</span>
                        <h2 class="font-heading text-emerald">{{ $booking->confirmation_code }}</h2>
                        <span class="status {{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                    </div>

                    @if($booking->status === 'cancelled')
                        <div class="alert alert-secondary">
                            This booking was cancelled{{ $booking->cancelled_at ? ' on '.$booking->cancelled_at->format('D, d M Y g:i A') : '' }}.
                            @if($latestPayment && $latestPayment->status === 'completed')
                                A payment was received for this booking — our team will contact you regarding a refund.
                            @endif
                        </div>
                    @elseif($booking->status !== 'confirmed')
                        @if($latestPayment && $latestPayment->status === 'failed')
                            <div class="alert alert-danger">Your last payment attempt was not completed. Your booking has not been confirmed yet.</div>
                        @elseif($latestPayment && $latestPayment->status === 'cancelled')
                            <div class="alert alert-warning">Your last payment was cancelled. Your booking has not been confirmed yet.</div>
                        @elseif($latestPayment && $latestPayment->provider === 'khalti' && $latestPayment->status === 'pending' && in_array($latestPayment->gateway_response['lookup']['status'] ?? null, ['Pending', 'Partially Refunded'], true))
                            <div class="alert alert-info">
                                <i class="bi bi-hourglass-split me-1"></i>
                                Khalti is still processing your payment. Please do not pay again — this booking will be confirmed automatically once Khalti completes it. Refresh this page to check.
                            </div>
                        @elseif($awaitingOffline && $latestPayment->provider === 'bank_transfer')
                            <div class="alert alert-info">
                                Awaiting your bank transfer — your booking will be confirmed once our team receives it.
                                <a href="{{ route('booking.pay.bank.show', $booking->confirmation_code) }}">View transfer details</a>
                            </div>
                        @elseif($awaitingOffline && $latestPayment->provider === 'cash')
                            <div class="alert alert-info">
                                <i class="bi bi-cash-coin me-1"></i>
                                You have chosen to <strong>pay at the hotel</strong>. Your booking is held while our team confirms it with you — please keep your booking reference handy.
                            </div>
                        @else
                            <div class="alert alert-warning">Payment required — this booking is held but not yet confirmed.</div>
                        @endif
                        <div class="text-center mb-4 d-print-none">
                            @if($awaitingOffline)
                                <a href="{{ route('booking.pay', $booking->confirmation_code) }}" class="small text-muted">Prefer to pay another way? Choose a different payment method</a>
                            @else
                                <a href="{{ route('booking.pay', $booking->confirmation_code) }}" class="btn-gold">
                                    <i class="bi bi-shield-lock"></i> Complete Payment
                                </a>
                            @endif
                        </div>
                    @endif

                    <table class="table">
                        <tr><td class="text-muted">Room</td><td class="text-end fw-semibold">{{ $booking->roomNames() }}</td></tr>
                        <tr><td class="text-muted">Check-in</td><td class="text-end fw-semibold">{{ $booking->checkInLabel() }}</td></tr>
                        <tr><td class="text-muted">Check-out</td><td class="text-end fw-semibold">{{ $booking->checkOutLabel() }}</td></tr>
                        <tr><td class="text-muted">Nights</td><td class="text-end fw-semibold">{{ $booking->nights }}</td></tr>
                        <tr><td class="text-muted">Guests</td><td class="text-end fw-semibold">{{ $booking->guests_label }}</td></tr>
                        @if($booking->hasVariableRates())
                            @foreach($booking->rateLines() as $line)
                            <tr><td class="text-muted">{{ $line['label'] }}</td><td class="text-end fw-semibold">{{ $line['nights'] }} × {{ npr($line['rate']) }}</td></tr>
                            @endforeach
                        @else
                        <tr><td class="text-muted">Nightly Rate</td><td class="text-end fw-semibold">{{ npr($booking->nightly_rate) }}</td></tr>
                        @endif
                        @if($booking->hasDiscount())
                        <tr><td class="text-success">First-booking discount ({{ percent($booking->discount_percent) }})</td><td class="text-end fw-semibold text-success">− {{ npr($booking->discount_amount) }}</td></tr>
                        @endif
                        <tr><td class="text-muted fs-5">Total Price</td><td class="text-end fw-bold fs-5 text-emerald">{{ npr($booking->total_price) }}</td></tr>
                        @if($awaitingOffline && $latestPayment->provider === 'cash')
                        <tr><td class="text-muted">Payment</td><td class="text-end fw-semibold">Pay at Hotel</td></tr>
                        @endif
                        @if($latestPayment && $latestPayment->status === 'completed')
                        <tr><td class="text-muted">Payment Status</td><td class="text-end fw-semibold text-success">Paid via {{ payment_provider_label($latestPayment->provider) }}</td></tr>
                        <tr><td class="text-muted">Transaction Ref</td><td class="text-end fw-semibold text-break">{{ $latestPayment->reference_id }}</td></tr>
                        @if($latestPayment->gatewayTransactionId())
                        <tr><td class="text-muted">{{ payment_provider_label($latestPayment->provider) }} Transaction ID</td><td class="text-end fw-semibold text-break">{{ $latestPayment->gatewayTransactionId() }}</td></tr>
                        @endif
                        <tr><td class="text-muted">Amount Paid</td><td class="text-end fw-semibold">{{ npr($booking->amountPaid()) }}</td></tr>
                        @if($booking->outstandingBalance() > 0)
                        <tr><td class="text-muted">Balance — Pay at Hotel</td><td class="text-end fw-semibold">{{ npr($booking->outstandingBalance()) }}</td></tr>
                        @endif
                        <tr><td class="text-muted">Paid At</td><td class="text-end fw-semibold">{{ $latestPayment->paid_at?->format('D, d M Y g:i A') }}</td></tr>
                        @endif
                    </table>

                    @if($booking->status === 'confirmed')
                    <p class="text-muted small text-center mt-4 mb-0">
                        A confirmation email has been sent to {{ $booking->email }}. To view this booking again later, use the “View my booking” link in that email.
                    </p>
                    @endif

                    @include('partials.stay-policies', ['class' => 'mt-4 mb-0'])

                    @if($booking->isGuestBooking() && $booking->isCancellable(0))
                    <div class="text-center mt-4 d-print-none">
                        <a href="{{ $booking->guestCancellationUrl() }}" class="text-danger small">Need to cancel this booking?</a>
                    </div>
                    @endif

                    <div class="text-center mt-4 d-print-none">
                        <a href="{{ route('home') }}" class="btn-gold">Return to Homepage</a>
                        <button type="button" onclick="window.print()" class="btn btn-outline-dark ms-2">
                            <i class="bi bi-printer"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
