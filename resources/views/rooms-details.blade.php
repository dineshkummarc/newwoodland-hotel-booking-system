@extends('layouts.app')

@section('title', $room->name . ' | Newwoodland Hotel and Lounge')
@section('meta_description', $room->short_description ?? Str::limit($room->description, 155))
@section('canonical', route('rooms.show', $room->slug))
@section('og_image', $room->primaryImage() ? asset('storage/'.$room->primaryImage()->path) : asset('assets/images/og-default.jpg'))

@push('structured_data')
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "Product",
    "name": "{{ $room->name }}",
    "description": {!! json_encode($room->short_description ?? Str::limit($room->description, 200), JSON_HEX_TAG | JSON_HEX_AMP) !!},
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
                {{-- ===================== IMAGE GALLERY ===================== --}}
                <div class="room-gallery mb-4">
                    @if($room->images->isNotEmpty())
                        <div class="gallery-card overflow-hidden rounded-4 shadow-lg mb-3">
                            <img src="{{ asset('storage/'.$room->primaryImage()->path) }}" alt="{{ $room->name }}" class="img-fluid w-100" style="max-height: 460px; object-fit: cover;">
                        </div>
                        @if($room->images->count() > 1)
                        <div class="row g-2 room-gallery-thumbs">
                            @foreach($room->images as $image)
                            <div class="col-3">
                                <div class="gallery-card overflow-hidden rounded-3 {{ $image->is_primary ? 'active-thumb' : '' }}">
                                    <img src="{{ asset('storage/'.$image->path) }}" alt="{{ $room->name }} — photo {{ $loop->iteration }}" class="img-fluid w-100" style="height:90px; object-fit:cover;">
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    @else
                        <div class="gallery-card overflow-hidden rounded-4 shadow-lg mb-3">
                            <img src="https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=1200&q=80" alt="{{ $room->name }}" class="img-fluid w-100" style="max-height: 460px; object-fit: cover;">
                        </div>
                    @endif
                </div>

                {{-- ===================== QUICK FACTS ===================== --}}
                <div class="room-quick-facts">
                    <div class="room-fact-chip">
                        <i class="bi bi-people-fill"></i>
                        <div>
                            <span class="room-fact-label">Sleeps</span>
                            <span class="room-fact-value">{{ $room->max_guests }} Guests</span>
                        </div>
                    </div>
                    <div class="room-fact-chip">
                        <i class="bi bi-lamp-fill"></i>
                        <div>
                            <span class="room-fact-label">Bed Type</span>
                            <span class="room-fact-value">{{ $room->bed_type ?? 'King Bed' }}</span>
                        </div>
                    </div>
                    @if($room->size_sqm)
                    <div class="room-fact-chip">
                        <i class="bi bi-aspect-ratio"></i>
                        <div>
                            <span class="room-fact-label">Room Size</span>
                            <span class="room-fact-value">{{ $room->size_sqm }} m²</span>
                        </div>
                    </div>
                    @endif
                    @if($room->rating > 0)
                    <div class="room-fact-chip">
                        <i class="bi bi-star-fill text-warning"></i>
                        <div>
                            <span class="room-fact-label">Rating</span>
                            <span class="room-fact-value">{{ $room->rating }}/5 ({{ $room->review_count }})</span>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- ===================== DESCRIPTION ===================== --}}
                <div class="room-description mb-5">
                    <h2 class="font-heading text-emerald mb-3">Room Overview</h2>
                    <p class="text-muted leading-relaxed">{{ $room->description }}</p>
                </div>

                {{-- ===================== AMENITIES & FACILITIES ===================== --}}
                @if($room->amenities->isNotEmpty())
                <div class="room-amenities mb-5 p-4 bg-light rounded-4 border">
                    <h3 class="font-heading text-emerald mb-4">Amenities & Facilities</h3>
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
                    <span class="availability-badge mb-3"><i class="bi bi-check-circle-fill"></i> Available for Booking</span>
                    <h4 class="mb-2">Book This Room</h4>
                    <p class="text-muted mb-4">Starting at <strong>{{ npr($room->price_per_night) }}</strong> per night.</p>
                    <a href="{{ route('booking', ['room' => $room->id]) }}" class="btn-gold w-100 py-3">Book Now</a>

                    {{-- ===================== AVAILABILITY CHECKER =====================
                         Reuses the site-wide availability AJAX (public/js/script.js),
                         which already wires up any [name="check_in"]/[name="check_out"]
                         pair alongside a #availabilityResult box — no new JS needed. --}}
                    <div class="mt-4 pt-4 border-top">
                        <h6 class="mb-3">Check Availability</h6>
                        <input type="hidden" name="room_id" value="{{ $room->id }}">
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="room-avail-checkin" class="booking-label">Check-in</label>
                                <div class="booking-input">
                                    <i class="bi bi-calendar-event"></i>
                                    <input id="room-avail-checkin" type="date" name="check_in" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="col-6">
                                <label for="room-avail-checkout" class="booking-label">Check-out</label>
                                <div class="booking-input">
                                    <i class="bi bi-calendar-check"></i>
                                    <input id="room-avail-checkout" type="date" name="check_out" class="form-control form-control-sm">
                                </div>
                            </div>
                        </div>
                        <div id="availabilityResult" class="mt-3 small"></div>
                    </div>

                    <div class="mt-4 pt-4 border-top text-muted small">
                        <h6 class="mb-3 text-dark">Good to Know</h6>
                        <ul class="list-unstyled mb-0">
                            @if($checkinTime ?? null)
                            <li class="mb-2"><i class="bi bi-clock text-emerald me-1"></i> Check-in from {{ \Illuminate\Support\Carbon::parse($checkinTime)->format('g:i A') }}</li>
                            @endif
                            @if($checkoutTime ?? null)
                            <li class="mb-2"><i class="bi bi-clock-history text-emerald me-1"></i> Check-out until {{ \Illuminate\Support\Carbon::parse($checkoutTime)->format('g:i A') }}</li>
                            @endif
                            <li><i class="bi bi-file-earmark-text text-emerald me-1"></i> <a href="{{ route('terms') }}" class="text-muted">Full booking & cancellation policy</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== RELATED ROOMS ===================== --}}
        @if($relatedRooms->isNotEmpty())
        <div class="mt-5 pt-4 border-top">
            <h3 class="font-heading text-emerald mb-4">You May Also Like</h3>
            <div class="row g-4">
                @foreach($relatedRooms as $related)
                <div class="col-lg-4 col-md-6">
                    <article class="room-card p-4 bg-white rounded-4 border shadow-sm h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="position-relative overflow-hidden rounded-3 mb-3">
                                @if($related->badge_label)<span class="room-tag">{{ $related->badge_label }}</span>@endif
                                <img src="{{ $related->primaryImage() ? asset('storage/'.$related->primaryImage()->path) : 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=800&q=80' }}" alt="{{ $related->name }}" class="img-fluid w-100" style="height: 200px; object-fit: cover;">
                            </div>
                            <h3 class="font-heading text-emerald h5">{{ $related->name }}</h3>
                            <p class="text-muted small mb-3">{{ $related->short_description ?? Str::limit($related->description, 90) }}</p>
                        </div>
                        <div class="border-top pt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span class="fs-5 font-heading fw-bold text-emerald">{{ npr($related->price_per_night) }}</span>
                            <a href="{{ route('rooms.show', $related->slug) }}" class="btn btn-outline-dark py-2 fs-6">View Details</a>
                        </div>
                    </article>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</section>

<div class="lightbox-overlay" id="lightbox">
    <button class="lightbox-close" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    <button class="lightbox-prev" aria-label="Previous"><i class="bi bi-chevron-left"></i></button>
    <img src="" alt="">
    <button class="lightbox-next" aria-label="Next"><i class="bi bi-chevron-right"></i></button>
</div>
@endsection
