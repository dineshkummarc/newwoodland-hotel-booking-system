@extends('layouts.app')

@section('title', 'Terms & Conditions | New Woodland Hotel')
@section('meta_description', 'Read the terms that govern reservations, guest stays, and hotel services at New Woodland Hotel.')
@section('canonical', route('terms'))
@section('meta_robots', 'noindex, follow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Terms & Conditions</span>
            <h1 class="font-heading">Booking Terms & Conditions</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Read the terms that govern reservations, guest stays, and hotel services at New Woodland Hotel.
            </p>
        </div>
    </div>
</section>

<section class="terms-section py-5 bg-white">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-12">
                <div class="terms-card p-4 bg-light rounded-4 shadow-sm border mb-4">
                    <h2 class="font-heading text-emerald h4 mb-3">1. Booking & Payment</h2>
                    <p class="text-muted mb-0">
                        Bookings made through this website are reservation requests and are confirmed by the hotel
                        via email with a confirmation code. Rates are quoted per night in
                        <strong>Nepalese Rupees (NPR)</strong>. Payment is generally made at check-in unless
                        otherwise agreed.
                    </p>
                </div>

                <div class="terms-card p-4 bg-light rounded-4 shadow-sm border mb-4">
                    <h2 class="font-heading text-emerald h4 mb-3">2. Check-in & Check-out</h2>
                    <p class="text-muted mb-2">
                        <strong>Check-in:</strong> {{ $checkinTime ? \Carbon\Carbon::parse($checkinTime)->format('g:i A') : '12:00 PM' }}<br>
                        <strong>Check-out:</strong> {{ $checkoutTime ? \Carbon\Carbon::parse($checkoutTime)->format('g:i A') : '10:00 AM' }}
                    </p>
                    <p class="text-muted mb-0">
                        Early check-in and late check-out are subject to availability and prior arrangement.
                    </p>
                </div>

                <div class="terms-card p-4 bg-light rounded-4 shadow-sm border mb-4">
                    <h2 class="font-heading text-emerald h4 mb-3">3. Cancellation</h2>
                    <p class="text-muted mb-0">
                        Cancellations made at least <strong>48 hours before check-in</strong> are eligible for a
                        full refund of any advance payment. Cancellations within 48 hours, early departures, or
                        no-shows may incur a charge of the first night's stay.
                    </p>
                </div>

                <div class="terms-card p-4 bg-light rounded-4 shadow-sm border mb-4">
                    <h2 class="font-heading text-emerald h4 mb-3">4. Guest Conduct</h2>
                    <p class="text-muted mb-0">
                        Guests are expected to respect hotel property, staff, and other guests. The hotel reserves
                        the right to take appropriate action for serious misconduct.
                    </p>
                </div>

                <div class="terms-card p-4 bg-light rounded-4 shadow-sm border mb-4">
                    <h2 class="font-heading text-emerald h4 mb-3">5. Liability</h2>
                    <p class="text-muted mb-0">
                        The hotel is not responsible for loss or damage to guests' personal belongings except
                        where required by applicable law. Guests are advised to use available safety facilities.
                    </p>
                </div>

                <div class="terms-card p-4 bg-light rounded-4 shadow-sm border mb-0">
                    <h2 class="font-heading text-emerald h4 mb-3">6. Contact</h2>
                    <p class="text-muted mb-0">
                        For questions regarding these terms, contact
                        <a href="mailto:{{ $hotelEmail ?? 'info@newwoodlandhotel.com' }}" class="text-emerald">{{ $hotelEmail ?? 'info@newwoodlandhotel.com' }}</a>{{ $hotelPhone ? ' or '.$hotelPhone : '' }}.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
