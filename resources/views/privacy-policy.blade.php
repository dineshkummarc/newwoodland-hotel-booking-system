@extends('layouts.app')

@section('title', 'Privacy Policy | Newwoodland Hotel & Lounge')
@section('meta_description', 'Learn how Newwoodland Hotel & Lounge collects, uses, shares, and protects your personal information.')
@section('canonical', route('privacy'))
@section('meta_robots', 'noindex, follow')

{{-- Keep this page accurate to what the website actually does. If a new
     service is added (analytics, marketing emails, a new payment provider),
     update the relevant section and the "Last updated" date. --}}
@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Privacy Policy</span>
            <h1 class="font-heading">Your Privacy Matters</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                How Newwoodland Hotel & Lounge collects, uses, and protects your personal information.
            </p>
        </div>
    </div>
</section>

<section class="privacy-section py-5 bg-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
                <div class="terms-card terms-document p-4 p-md-5 bg-light rounded-4 shadow-sm border">
                    <p class="text-muted small mb-4"><strong>Last updated:</strong> September 30, 2026</p>

                    <h2 class="font-heading text-emerald h5">1. Information We Collect</h2>
                    <ul class="terms-list">
                        <li>Name, email address, phone number, and booking details provided at the time of reservation.</li>
                        <li>Payment records, including method, amount, and transaction reference.</li>
                        <li>If you verify a phone number in your account, that number and a record of its verification. One-time verification codes are sent by SMS; they are stored only in a non-reversible (hashed) form and expire after a few minutes.</li>
                        <li>At check-in, staff may record the type and number of the photo ID you present (Citizenship, Student ID or Office/Company ID). The ID number is stored encrypted and is visible only to authorised hotel staff. Copies or scans of ID documents are not kept.</li>
                        <li>Card numbers, wallet PINs, and banking passwords are never collected or stored.</li>
                    </ul>

                    <h2 class="font-heading text-emerald h5">2. Use of Information</h2>
                    <ul class="terms-list">
                        <li>To process reservations and payments, and to communicate regarding your stay.</li>
                        <li>A verified phone number is used to send verification and password-recovery codes by SMS.</li>
                        <li>To respond to enquiries and to meet legal and record-keeping obligations.</li>
                    </ul>

                    <h2 class="font-heading text-emerald h5">3. Sharing of Information</h2>
                    <ul class="terms-list">
                        <li>Information is shared only with service providers necessary to operate our services, such as payment, email, and SMS providers, or where required by law.</li>
                        <li>Personal information is never sold or rented.</li>
                    </ul>

                    <h2 class="font-heading text-emerald h5">4. Cookies</h2>
                    <ul class="terms-list">
                        <li>Only essential cookies are used, to maintain sessions and secure forms. No advertising or tracking cookies are used.</li>
                    </ul>

                    <h2 class="font-heading text-emerald h5">5. Security &amp; Your Rights</h2>
                    <ul class="terms-list">
                        <li>Reasonable measures are taken to protect personal information, which is retained only as long as necessary.</li>
                        <li>You may request access to, correction of, or deletion of your information, subject to legal requirements.</li>
                    </ul>

                    <h2 class="font-heading text-emerald h5">6. Contact</h2>
                    <ul class="terms-list mb-0">
                        <li>Email: <a href="mailto:{{ $hotelEmail ?? 'woodlandhotel146@gmail.com' }}" class="text-emerald">{{ $hotelEmail ?? 'woodlandhotel146@gmail.com' }}</a></li>
                        @if($hotelPhone ?? null)
                            <li>Phone: <a href="tel:{{ $hotelPhoneRaw ?? '' }}" class="text-emerald">{{ $hotelPhone }}</a></li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
