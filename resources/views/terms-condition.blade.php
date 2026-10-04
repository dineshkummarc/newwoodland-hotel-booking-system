@extends('layouts.app')

@section('title', 'Terms & Conditions | Newwoodland Hotel & Lounge')
@section('meta_description', 'Read the terms that govern reservations, guest stays, and hotel services at Newwoodland Hotel & Lounge.')
@section('canonical', route('terms'))
@section('meta_robots', 'noindex, follow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Terms & Conditions</span>
            <h1 class="font-heading">Booking Terms & Conditions</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Read the terms that govern reservations, guest stays, and hotel services at Newwoodland Hotel & Lounge.
            </p>
        </div>
    </div>
</section>

<section class="terms-section py-5 bg-white">
    <div class="container">
        @php
            $splitPercent = (int) config('payments.split_payment.percent');
            $time = fn ($value, $fallback) => $value ? \Carbon\Carbon::parse($value)->format('g:i A') : $fallback;
        @endphp
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
                <div class="terms-card terms-document p-4 p-md-5 bg-light rounded-4 shadow-sm border">
                    <p class="text-muted small mb-4">
                        By making a booking with Newwoodland Hotel & Lounge, you agree to the following terms.
                    </p>

                    <h2 class="font-heading text-emerald h5">1. Booking &amp; Payment</h2>
                    <ul class="terms-list">
                        <li>All rates are quoted per night in Nepalese Rupees (NPR).</li>
                        <li><strong>Full payment:</strong> 100% of the booking amount paid in advance by QR / online payment (eSewa, Fonepay or bank transfer).</li>
                        @if(config('payments.split_payment.enabled'))
                            <li><strong>Half payment:</strong> {{ $splitPercent }}% paid in advance by QR / online payment, and the remaining {{ 100 - $splitPercent }}% paid at the hotel (Pay at Hotel).</li>
                        @endif
                        <li>A booking is confirmed only once the advance payment is received or the hotel confirms it.</li>
                        <li>Unconfirmed bookings are held for a limited time, after which the room may be released.</li>
                        @if($firstBookingDiscount)
                            <li><strong>First-booking offer:</strong> first-time guests booking through this website receive a {{ percent($firstBookingDiscount) }} discount, applied automatically.</li>
                        @endif
                    </ul>

                    <h2 class="font-heading text-emerald h5">2. Check-in &amp; Check-out</h2>
                    <ul class="terms-list">
                        <li>Check-in from <strong>{{ $time($checkinTime, '12:00 PM') }}</strong>; check-out by <strong>{{ $time($checkoutTime, '10:00 AM') }}</strong>.</li>
                        <li>Every guest must present a valid <strong>photo ID</strong> at check-in: {{ collect(\App\Models\GuestDocument::TYPES)->values()->join(', ', ' or ') }}.</li>
                        <li>Staff may record the ID type and number (stored encrypted). Copies or scans of ID documents are not kept.</li>
                        <li>Early check-in and late check-out are subject to availability and prior arrangement.</li>
                    </ul>

                    <h2 class="font-heading text-emerald h5">3. Cancellation</h2>
                    <ul class="terms-list">
                        @if($cancellationDeductionPercent)
                            <li>Cancellation before arrival: <strong>{{ $cancellationDeductionPercent }}% of the booking amount is deducted</strong>.</li>
                        @else
                            <li><strong>Cancellation before arrival is free</strong> — nothing is deducted.</li>
                        @endif
                        <li>Cancellation after check-in: <strong>{{ config('bookings.after_arrival_cancellation_percent') }}% of the booking amount is charged</strong>. Extra charges already incurred during the stay remain payable.</li>
                        <li>Any amount paid beyond what is owed is refunded by the hotel. Refunds are arranged by our team and are not processed automatically.</li>
                    </ul>

                    <h2 class="font-heading text-emerald h5">4. Guest Conduct</h2>
                    <ul class="terms-list">
                        <li>Guests must follow the rules and regulations of the hotel.</li>
                        <li>Respect hotel property, staff, and other guests at all times.</li>
                        <li>Spitting on the walls or anywhere inside the rooms or the building is strictly prohibited.</li>
                        <li>The hotel may take appropriate action for misconduct or damage to hotel property.</li>
                    </ul>

                    <h2 class="font-heading text-emerald h5">5. Pets</h2>
                    <ul class="terms-list">
                        <li>Pets are welcome in the outdoor areas of the property.</li>
                        <li>Pets are <strong>not allowed inside the building</strong>, including guest rooms.</li>
                        <li>Please inform the hotel in advance if you are travelling with a pet.</li>
                    </ul>

                    <h2 class="font-heading text-emerald h5">6. Liability</h2>
                    <ul class="terms-list">
                        <li>The hotel is not responsible for loss of or damage to guests' personal belongings, except where required by law.</li>
                        <li>Guests are advised to keep valuables secure.</li>
                    </ul>

                    <h2 class="font-heading text-emerald h5">7. Contact</h2>
                    <ul class="terms-list mb-0">
                        <li>Email: <a href="mailto:{{ $hotelEmail ?? 'woodlandhotel146@gmail.com' }}" class="text-emerald">{{ $hotelEmail ?? 'woodlandhotel146@gmail.com' }}</a></li>
                        @if($hotelPhone)
                            <li>Phone: <a href="tel:{{ $hotelPhoneRaw }}" class="text-emerald">{{ $hotelPhone }}</a></li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
