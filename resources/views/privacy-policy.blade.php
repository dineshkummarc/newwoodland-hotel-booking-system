@extends('layouts.app')

@section('title', 'Privacy Policy | New Woodland Hotel')
@section('meta_description', 'Learn how New Woodland Hotel collects, uses, and protects your personal information.')
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
        </div>
    </div>
</section>

<section class="privacy-section py-5 bg-white">
    <div class="container">
        <div class="privacy-card p-4 bg-light rounded-4 shadow-sm border mb-4">
            <h2 class="font-heading text-emerald h4 mb-3">1. Information Collection</h2>
            <p class="text-muted">We collect only the information needed to process bookings, answer inquiries, and provide hotel services.</p>
        </div>
        <div class="privacy-card p-4 bg-light roiiunded-4 shadow-sm border mb-4">
            <h2 class="font-heading text-emerald h4 mb-3">2. Use of Information</h2>
            <p class="text-muted">Personal information is used to manage bookings, improve services, and respond to guest requests.</p>
        </div>
        <div class="privacy-card p-4 bg-light rounded-4 shadow-sm border mb-4">
            <h2 class="font-heading text-emerald h4 mb-3">3. Data Security</h2>
            <p class="text-muted">We implement reasonable measures to protect your information from unauthorized access.</p>
        </div>
    </div>
</section>
@endsection
