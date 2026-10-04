@extends('layouts.app')

@section('title', 'Facilities & Services | Newwoodland Hotel & Lounge')
@section('meta_description', 'Facilities and services at Newwoodland Hotel & Lounge, Bhadrapur-8, Jhapa — swimming pools, restaurant and bar, free Wi-Fi and parking, and 24-hour guest services.')
@section('canonical', route('facilities'))

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Facilities &amp; Services</span>
            <h1 class="font-heading">Hotel Facilities</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                The services and amenities available to guests of Newwoodland Hotel & Lounge.
            </p>
        </div>
    </div>
</section>

<section id="facilities" class="py-5 bg-white">
    <div class="container">
        <div class="row g-4">
            @forelse($facilities as $facility)
            <div class="col-lg-4 col-md-6">
                @include('partials.facility-card', ['facility' => $facility])
            </div>
            @empty
            <div class="col-12 text-center text-muted">No facilities are listed currently.</div>
            @endforelse
        </div>

        <p class="text-center text-muted small mt-5 mb-0">
            For details on any facility, please <a href="{{ route('contact') }}" class="text-emerald">contact the hotel</a>.
            Pets and smoking are subject to our <a href="{{ route('terms') }}" class="text-emerald">Terms &amp; Conditions</a>.
        </p>
    </div>
</section>

{{-- Event types the hotel hosts (client-confirmed). Only the types are
     listed — no prices, capacities, packages or venues have been confirmed. --}}
@php
    $events = [
        ['bi-house-heart-fill', 'Family Gatherings'],
        ['bi-briefcase-fill', 'Business Meetings'],
        ['bi-balloon-fill', 'Birthday Celebrations'],
        ['bi-stars', 'Pasni Celebrations'],
        ['bi-people-fill', 'Get-Togethers'],
        ['bi-heart-fill', 'Wedding Ceremonies'],
    ];
@endphp
<section id="events" class="py-5 bg-light">
    <div class="container text-center">
        <span class="section-subtitle">Events &amp; Celebrations</span>
        <h2 class="section-title">Host Your Event With Us</h2>
        <p class="text-muted max-w-700 mx-auto mb-4">We host the following occasions. Please contact the hotel to discuss your event.</p>
        <div class="row g-3 justify-content-center">
            @foreach($events as [$icon, $event])
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="h-100 p-3 bg-white rounded-4 shadow-sm border">
                        <i class="bi {{ $icon }} fs-3 text-gold d-block mb-2" aria-hidden="true"></i>
                        <span class="fw-semibold small">{{ $event }}</span>
                    </div>
                </div>
            @endforeach
        </div>
        <a href="{{ route('contact') }}" class="btn-gold py-3 px-5 mt-4">Enquire About an Event</a>
    </div>
</section>
@endsection
