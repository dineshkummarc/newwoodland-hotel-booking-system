@extends('layouts.app')

@section('title', 'Privacy Policy | New Woodland Hotel')
@section('meta_description', 'Learn how New Woodland Hotel collects, uses, shares, and protects your personal information.')
@section('canonical', route('privacy'))
@section('meta_robots', 'noindex, follow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Privacy Policy</span>
            <h1 class="font-heading">Your Privacy Matters</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Learn how New Woodland Hotel collects, uses, and protects your personal information.
            </p>
            <p class="text-white-50 small mb-0">Last updated: {{ now()->format('F j, Y') }}</p>
        </div>
    </div>
</section>

<section class="privacy-section py-5 bg-white">
    <div class="container">
        <div class="privacy-card p-4 bg-light rounded-4 shadow-sm border mb-4">
            <p class="text-muted mb-0">
                At <strong>New Woodland Hotel</strong>, we respect your privacy and are committed to protecting the
                personal information you provide through our website.
            </p>
        </div>

        <div class="privacy-card p-4 bg-light rounded-4 shadow-sm border mb-4">
            <h2 class="font-heading text-emerald h4 mb-3">1. Information We Collect</h2>
            <p class="text-muted mb-2">
                When you make a booking, submit an inquiry, or contact us, we may collect your name, email address,
                phone number, country, booking details, special requests, and message content.
            </p>
            <p class="text-muted mb-0">
                We do not collect or store payment card information directly through this website.
            </p>
        </div>

        <div class="privacy-card p-4 bg-light rounded-4 shadow-sm border mb-4">
            <h2 class="font-heading text-emerald h4 mb-3">2. How We Use Your Information</h2>
            <p class="text-muted mb-2">
                We use your information to process and manage bookings, respond to inquiries, communicate with you
                regarding your stay, and provide our services.
            </p>
            <p class="text-muted mb-0">
                We do not sell or rent your personal information or use it for marketing without your consent
                where required by law.
            </p>
        </div>

        <div class="privacy-card p-4 bg-light rounded-4 shadow-sm border mb-4">
            <h2 class="font-heading text-emerald h4 mb-3">3. Sharing of Information</h2>
            <p class="text-muted mb-2">
                We may share necessary information with trusted service providers who help us operate our website
                and provide services, such as booking and email communication.
            </p>
            <p class="text-muted mb-0">
                We do not sell your information to advertisers.
            </p>
        </div>

        <div class="privacy-card p-4 bg-light rounded-4 shadow-sm border mb-4">
            <h2 class="font-heading text-emerald h4 mb-3">4. Data Security & Retention</h2>
            <p class="text-muted mb-0">
                We take reasonable measures to protect your personal information and retain it only for as long as
                necessary to provide our services, maintain business records, or meet legal obligations.
            </p>
        </div>

        <div class="privacy-card p-4 bg-light rounded-4 shadow-sm border mb-4">
            <h2 class="font-heading text-emerald h4 mb-3">5. Your Rights</h2>
            <p class="text-muted mb-0">
                Where applicable, you may request access to, correction of, or deletion of your personal
                information by contacting us.
            </p>
        </div>

        <div class="privacy-card p-4 bg-light rounded-4 shadow-sm border mb-4">
            <h2 class="font-heading text-emerald h4 mb-3">6. Contact Us</h2>
            <p class="text-muted mb-1">For questions or privacy-related requests, please contact:</p>
            <p class="text-muted mb-0">
                <strong class="d-block text-emerald">New Woodland Hotel</strong>
                Email: <a href="mailto:{{ $hotelEmail ?? 'info@newwoodlandhotel.com' }}" class="text-emerald">{{ $hotelEmail ?? 'info@newwoodlandhotel.com' }}</a><br>
                @if($hotelPhone ?? null)
                Phone: <a href="tel:{{ $hotelPhoneRaw ?? '' }}" class="text-emerald">{{ $hotelPhone }}</a>
                @endif
            </p>
        </div>

        <div class="privacy-card p-4 bg-light rounded-4 shadow-sm border mb-0">
            <h2 class="font-heading text-emerald h4 mb-3">7. Policy Updates</h2>
            <p class="text-muted mb-0">
                We may update this Privacy Policy from time to time. Any changes will be posted on this page with
                an updated "Last Updated" date.
            </p>
        </div>
    </div>
</section>
@endsection
