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
            <h1 class="font-heading">Hotel Policies</h1>
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
                    <p class="text-muted">Booking requests are processed promptly. Payment terms, cancellation policies, and room rates are subject to hotel policy.</p>
                </div>
                <div class="terms-card p-4 bg-light rounded-4 shadow-sm border mb-4">
                    <h2 class="font-heading text-emerald h4 mb-3">2. Guest Conduct</h2>
                    <p class="text-muted">Guests must respect hotel property, staff, and other guests. Failure to comply may result in denied service or removal from the property.</p>
                </div>
                <div class="terms-card p-4 bg-light rounded-4 shadow-sm border mb-4">
                    <h2 class="font-heading text-emerald h4 mb-3">3. Liability</h2>
                    <p class="text-muted">The hotel is not responsible for loss or damage to personal items. Guests should use hotel safes and follow safety guidelines.</p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
