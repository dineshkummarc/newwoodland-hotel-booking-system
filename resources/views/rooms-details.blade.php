@extends('layouts.app')

@section('title', $room->name . ' | New Woodland Hotel')
@section('meta_description', $room->short_description ?? Str::limit($room->description, 155))
@section('canonical', route('rooms.show', $room->slug))
@section('og_image', $room->primaryImage() ? asset('storage/'.$room->primaryImage()->path) : asset('assets/images/og-default.jpg'))

@push('structured_data')
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "Product",
    "name": "{{ $room->name }}",
    "description": {!! json_encode($room->short_description ?? Str::limit($room->description, 200)) !!},
    @if($room->primaryImage())"image": "{{ asset('storage/'.$room->primaryImage()->path) }}",@endif
    "offers": {
        "@@type": "Offer",
        "priceCurrency": "NPR",
        "price": "{{ (string) $room->price_per_night }}",
        "availability": "{{ $room->is_active ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' }}",
        "url": "{{ route('rooms.show', $room->slug) }}"
    }
}
</script>
@endpush

@section('content')
<section id="room-details-hero" class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Room Details</span>
            <h1 class="font-heading">{{ $room->name }}</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">{{ $room->short_description ?? Str::limit($room->description, 120) }}</p>
        </div>
    </div>
</section>

<section id="breadcrumb" class="py-3 bg-light border-bottom">
    <div class="container">
        <nav>
            <ol class="breadcrumb justify-content-center m-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('rooms.index') }}" class="text-decoration-none">Rooms</a></li>
                <li class="breadcrumb-item active">{{ $room->name }}</li>
            </ol>
        </nav>
    </div>
</section>

<section id="room-details-section" class="py-5 bg-white">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                <div class="room-gallery mb-5">
                    @if($room->images->isNotEmpty())
                        <img src="{{ asset('storage/'.$room->primaryImage()->path) }}" alt="{{ $room->name }}" class="img-fluid rounded-4 shadow-md w-100 mb-3" style="max-height: 450px; object-fit: cover;">
                        @if($room->images->count() > 1)
                        <div class="row g-2">
                            @foreach($room->images as $image)
                            <div class="col-3">
                                <img src="{{ asset('storage/'.$image->path) }}" alt="{{ $room->name }}" class="img-fluid rounded-3 w-100" style="height:80px; object-fit:cover;">
                            </div>
                            @endforeach
                        </div>
                        @endif
                    @else
                        <img src="https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=1200&q=80" alt="{{ $room->name }}" class="img-fluid rounded-4 shadow-md w-100 mb-3" style="max-height: 450px; object-fit: cover;">
                    @endif
                </div>
                <div class="room-description mb-5">
                    <h2 class="font-heading text-emerald mb-3">Room Overview</h2>
                    <p class="text-muted leading-relaxed">{{ $room->description }}</p>
                </div>
                @if($room->amenities->isNotEmpty())
                <div class="room-amenities mb-5 p-4 bg-light rounded-4 border">
                    <h3 class="font-heading text-emerald mb-4">Amenities</h3>
                    <div class="row g-4">
                        @foreach($room->amenities as $amenity)
                        <div class="col-md-4 col-6 d-flex align-items-center gap-3">
                            <i class="bi {{ $amenity->icon ?: 'bi-check-circle' }} text-warning fs-3"></i>
                            <span>{{ $amenity->label }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            <div class="col-lg-4">
                <div class="booking-sidebar p-4 bg-light rounded-4 shadow-sm border position-sticky" style="top: 100px;">
                    <h4 class="mb-3">Book This Room</h4>
                    <p class="text-muted mb-4">Starting at <strong>{{ npr($room->price_per_night) }}</strong> per night.</p>
                    <a href="{{ route('booking', ['room' => $room->id]) }}" class="btn-gold w-100 py-3">Book Now</a>
                    <ul class="list-unstyled mt-4 text-muted small">
                        <li class="mb-2"><strong>Capacity:</strong> {{ $room->max_guests }} Guests</li>
                        <li class="mb-2"><strong>Bed Type:</strong> {{ $room->bed_type ?? 'King Bed' }}</li>
                        @if($room->size_sqm)<li class="mb-2"><strong>Room Size:</strong> {{ $room->size_sqm }} m²</li>@endif
                        @if($room->rating > 0)<li class="mb-2"><strong>Rating:</strong> {{ $room->rating }}/5 ({{ $room->review_count }} reviews)</li>@endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
