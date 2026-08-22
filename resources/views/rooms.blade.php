@extends('layouts.app')

@section('title', 'Rooms & Suites | New Woodland Hotel')
@section('meta_description', 'Explore elegant rooms and premium suites at New Woodland Hotel in Bhadrapur, Jhapa, Nepal.')
@section('canonical', route('rooms.index'))

@section('content')
<section id="rooms-hero" class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Luxury Accommodation</span>
            <h1 class="font-heading">Rooms & Suites</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Explore elegant rooms and premium suites designed for comfort and relaxation in Bhadrapur.
            </p>
        </div>
    </div>
</section>

<section id="rooms" class="py-5 bg-white">
    <div class="container">
        <div class="section-heading text-center mb-5">
            <span class="section-subtitle">Featured Inventory</span>
            <h2 class="section-title">Choose Your Perfect Stay</h2>
        </div>
        <div class="row g-4">
            @forelse($rooms as $room)
            <div class="col-lg-4 col-md-6">
                <article class="room-card p-3 bg-light rounded-4 border shadow-sm h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="position-relative overflow-hidden rounded-3 mb-3">
                            @if($room->badge_label)<span class="room-tag">{{ $room->badge_label }}</span>@endif
                            <img src="{{ $room->primaryImage() ? asset('storage/'.$room->primaryImage()->path) : 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=800&q=80' }}" alt="{{ $room->name }}" class="img-fluid w-100" style="height: 240px; object-fit: cover;">
                        </div>
                        <h3 class="font-heading text-emerald h4">{{ $room->name }}</h3>
                        <p class="text-muted small mb-3">{{ $room->short_description ?? Str::limit($room->description, 120) }}</p>
                        <ul class="list-unstyled text-muted small mb-4 lh-lg">
                            <li><i class="bi bi-people-fill me-1"></i> {{ $room->max_guests }} Guests</li>
                            <li><i class="bi bi-lamp-fill me-1"></i> {{ $room->bed_type ?? 'King Bed' }}</li>
                            @if($room->size_sqm)<li><i class="bi bi-aspect-ratio me-1"></i> {{ $room->size_sqm }} m²</li>@endif
                            <li><i class="bi bi-wifi me-1"></i> Free Wi-Fi</li>
                        </ul>
                    </div>
                    <div class="border-top pt-3 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fs-4 font-heading fw-bold text-emerald">{{ npr($room->price_per_night) }}</span>
                            <small class="text-muted">/ Night</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('rooms.show', $room->slug) }}" class="btn btn-outline-dark w-50 py-2 fs-6">View</a>
                            <a href="{{ route('booking') }}" class="btn-gold w-50 py-2 fs-6">Book</a>
                        </div>
                    </div>
                </article>
            </div>
            @empty
            <div class="col-12">
                <p class="text-center text-muted">No rooms available right now.</p>
            </div>
            @endforelse
        </div>
    </div>
</section>
@endsection
