@extends('layouts.app')

@section('title', 'Booking Confirmation | Newwoodland Hotel and Lounge')
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
                    @php $latestPayment = $booking->latestPayment(); @endphp
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
                        @else
                            <div class="alert alert-warning">Payment required — this booking is held but not yet confirmed.</div>
                        @endif
                        <div class="text-center mb-4 d-print-none">
                            <a href="{{ route('booking.pay', $booking->confirmation_code) }}" class="btn-gold">
                                <i class="bi bi-shield-lock"></i> Complete Payment
                            </a>
                        </div>
                    @endif

                    <table class="table">
                        <tr><td class="text-muted">Room</td><td class="text-end fw-semibold">{{ $booking->room->name }}</td></tr>
                        <tr><td class="text-muted">Check-in</td><td class="text-end fw-semibold">{{ $booking->check_in->format('D, d M Y') }}</td></tr>
                        <tr><td class="text-muted">Check-out</td><td class="text-end fw-semibold">{{ $booking->check_out->format('D, d M Y') }}</td></tr>
                        <tr><td class="text-muted">Nights</td><td class="text-end fw-semibold">{{ $booking->nights }}</td></tr>
                        <tr><td class="text-muted">Guests</td><td class="text-end fw-semibold">{{ $booking->guests_label }}</td></tr>
                        <tr><td class="text-muted">Nightly Rate</td><td class="text-end fw-semibold">{{ npr($booking->nightly_rate) }}</td></tr>
                        <tr><td class="text-muted fs-5">Total Price</td><td class="text-end fw-bold fs-5 text-emerald">{{ npr($booking->total_price) }}</td></tr>
                        @if($latestPayment && $latestPayment->status === 'completed')
                        <tr><td class="text-muted">Payment Status</td><td class="text-end fw-semibold text-success">Paid via {{ payment_provider_label($latestPayment->provider) }}</td></tr>
                        <tr><td class="text-muted">Transaction Ref</td><td class="text-end fw-semibold text-break">{{ $latestPayment->reference_id }}</td></tr>
                        <tr><td class="text-muted">Paid At</td><td class="text-end fw-semibold">{{ $latestPayment->paid_at?->format('D, d M Y g:i A') }}</td></tr>
                        @endif
                    </table>

                    @if($booking->status === 'confirmed')
                    <p class="text-muted small text-center mt-4 mb-0">
                        A confirmation email has been sent to {{ $booking->email }}. You can revisit this page anytime by reloading this URL — your booking reference is saved in our system.
                    </p>
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
