@extends('layouts.app')

@section('title', 'Facilities & Services | New Woodland Hotel')
@section('meta_description', 'Discover the amenities that make every stay at New Woodland Hotel memorable — pool, restaurant, Wi-Fi, parking, and more.')
@section('canonical', route('facilities'))

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Facilities & Services</span>
            <h1 class="font-heading">Comfort & Convenience</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Discover the amenities that make every stay at New Woodland Hotel memorable.
            </p>
        </div>
    </div>
</section>

<section id="facilities" class="py-5 bg-white">
    <div class="container">
        <div class="section-heading text-center mb-5">
            <span class="section-subtitle">Our Facilities</span>
            <h2 class="section-title">Designed to Delight</h2>
            <p class="text-muted">Enjoy premium guest services, leisure amenities, and thoughtful comforts throughout your stay.</p>
        </div>
        <div class="row g-4">
            @forelse($facilities as $facility)
            <div class="col-lg-4 col-md-6">
                <div class="facility-card p-4 bg-light rounded-4 shadow-sm h-100">
                    <div class="facility-icon mb-3"><i class="bi {{ $facility->icon ?: 'bi-star-fill' }}"></i></div>
                    <h4>{{ $facility->title }}</h4>
                    <p class="text-muted">{{ $facility->description }}</p>
                </div>
            </div>
            @empty
            <div class="col-12 text-center text-muted">No facilities are listed currently.</div>
            @endforelse
        </div>
    </div>
</section>
@endsection
