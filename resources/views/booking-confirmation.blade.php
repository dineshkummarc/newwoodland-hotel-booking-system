@extends('layouts.app')

@section('title', 'Booking Confirmation | New Woodland Hotel')
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
                    <div class="text-center mb-4">
                        <span class="section-subtitle">Confirmation Code</span>
                        <h2 class="font-heading text-emerald">{{ $booking->confirmation_code }}</h2>
                        <span class="status {{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                    </div>
                    <table class="table">
                        <tr><td class="text-muted">Room</td><td class="text-end fw-semibold">{{ $booking->room->name }}</td></tr>
                        <tr><td class="text-muted">Check-in</td><td class="text-end fw-semibold">{{ $booking->check_in->format('D, d M Y') }}</td></tr>
                        <tr><td class="text-muted">Check-out</td><td class="text-end fw-semibold">{{ $booking->check_out->format('D, d M Y') }}</td></tr>
                        <tr><td class="text-muted">Nights</td><td class="text-end fw-semibold">{{ $booking->nights }}</td></tr>
                        <tr><td class="text-muted">Guests</td><td class="text-end fw-semibold">{{ $booking->guests_label }}</td></tr>
                        <tr><td class="text-muted">Nightly Rate</td><td class="text-end fw-semibold">{{ npr($booking->nightly_rate) }}</td></tr>
                        <tr><td class="text-muted fs-5">Total Price</td><td class="text-end fw-bold fs-5 text-emerald">{{ npr($booking->total_price) }}</td></tr>
                    </table>
                    <p class="text-muted small text-center mt-4 mb-0">
                        A confirmation email has been sent to {{ $booking->email }}. You can revisit this page anytime by reloading this URL — your booking reference is saved in our system.
                    </p>
                    <div class="text-center mt-4">
                        <a href="{{ route('home') }}" class="btn-gold">Return to Homepage</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
