@extends('layouts.app')

@section('title', 'Newwoodland Hotel and Lounge | Hotel in Bhadrapur-8, Jhapa')
@section('meta_description', 'Newwoodland Hotel and Lounge offers luxury accommodation, premium dining, modern facilities, and exceptional hospitality in Bhadrapur-8, Jhapa, Nepal.')
@section('canonical', route('home'))

@push('structured_data')
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "Hotel",
    "name": "Newwoodland Hotel and Lounge",
    "image": "{{ asset('storage/site/hero-1.jpg') }}",
    "url": "{{ route('home') }}",
    "telephone": "{{ $hotelPhone ?? '+977 9801415107' }}",
    "email": "{{ $hotelEmail ?? 'info@newwoodlandhotel.com' }}",
    "address": {
        "@@type": "PostalAddress",
        "streetAddress": "{{ $hotelAddress ?? 'Bhadrapur-8, Jhapa, Nepal' }}",
        "addressCountry": "NP"
    }
    @if($testimonials->count())
    ,"aggregateRating": {
        "@@type": "AggregateRating",
        "ratingValue": "{{ number_format($testimonials->avg('rating'), 1) }}",
        "reviewCount": "{{ $testimonials->count() }}"
    }
    @endif
}
</script>
@endpush

@section('content')
<section id="hero" class="page-banner text-center" style="background-image:url('{{ asset('storage/site/hero-1.jpg') }}'); background-size:cover; background-position:center;">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Welcome to Newwoodland Hotel and Lounge</span>
            <h1 class="font-heading">Experience Timeless Comfort</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Newwoodland Hotel and Lounge offers luxury accommodation, premium dining, modern facilities, and exceptional hospitality in Bhadrapur-8, Jhapa, Nepal.
            </p>
            <div class="hero-buttons d-flex justify-content-center gap-3 mt-4">
                <a href="{{ route('booking') }}" class="btn-gold py-3 px-5 fs-6">Book Your Stay</a>
                <a href="{{ route('rooms.index') }}" class="btn-outline text-white border-white py-3 px-5 fs-6">Explore Rooms</a>
            </div>
        </div>
    </div>
</section>

<section id="about-preview" class="py-5 bg-white">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <img src="{{ asset('storage/site/about.jpg') }}" alt="Newwoodland Hotel and Lounge" class="img-fluid rounded-4 shadow-lg">
            </div>
            <div class="col-lg-6">
                <span class="section-subtitle">About Newwoodland Hotel and Lounge</span>
                <h2 class="section-title">Comfort & Authentic Nepalese Hospitality</h2>
                <p class="text-muted leading-relaxed">
                    Located in Bhadrapur-8, Jhapa, Nepal, Newwoodland Hotel and Lounge provides comfortable and luxurious accommodation for business travelers, families, and leisure guests alike.
                </p>
                <a href="{{ route('about') }}" class="btn-gold">Learn More About Us</a>
            </div>
        </div>
    </div>
</section>

<section id="featured-rooms" class="py-5 bg-light">
    <div class="container">
        <div class="section-heading text-center mb-5">
            <span class="section-subtitle">Featured Rooms</span>
            <h2 class="section-title">Luxury Rooms & Suites</h2>
            <p class="text-muted">Enjoy beautifully appointed rooms, modern amenities, and authentic Nepalese hospitality during your stay.</p>
        </div>
        <div class="row g-4">
            @forelse($featuredRooms as $room)
            <div class="col-lg-4 col-md-6">
                <article class="room-card p-4 bg-white rounded-4 border shadow-sm h-100 d-flex flex-column justify-content-between">
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
                        </ul>
                    </div>
                    <div class="border-top pt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="fs-4 font-heading fw-bold text-emerald">{{ npr($room->price_per_night) }}</span>
                            <small class="text-muted">/ Night</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('rooms.show', $room->slug) }}" class="btn btn-outline-dark py-2 fs-6">Details</a>
                            <a href="{{ route('booking', ['room' => $room->id]) }}" class="btn-gold py-2 fs-6">Book</a>
                        </div>
                    </div>
                </article>
            </div>
            @empty
            <div class="col-12">
                <p class="text-center text-muted">No featured rooms are available at the moment.</p>
            </div>
            @endforelse
        </div>
    </div>
</section>

<section id="facilities" class="py-5 bg-white">
    <div class="container">
        <div class="section-heading text-center mb-5">
            <span class="section-subtitle">Hotel Facilities</span>
            <h2 class="section-title">Designed for Your Comfort</h2>
            <p class="text-muted">From free Wi-Fi to attentive service, discover the amenities that make every stay better.</p>
        </div>
        <div class="row g-4">
            @foreach($facilities as $facility)
            <div class="col-lg-4 col-md-6">
                <div class="facility-card p-4 bg-light rounded-4 shadow-sm h-100">
                    <div class="facility-icon mb-3"><i class="bi {{ $facility->icon ?: 'bi-check-circle-fill' }}"></i></div>
                    <h4 class="mb-3">{{ $facility->title }}</h4>
                    <p class="text-muted">{{ $facility->description }}</p>
                </div>
            </div>
            @endforeach
        </div>
        <div class="text-center mt-5">
            <a href="{{ route('facilities') }}" class="btn-gold py-3 px-5">View All Facilities</a>
        </div>
    </div>
</section>

@if($galleryPreview->count())
<section id="gallery-preview" class="py-5 bg-light">
    <div class="container">
        <div class="section-heading text-center mb-5">
            <span class="section-subtitle">Photo Gallery</span>
            <h2 class="section-title">A Glimpse of Newwoodland Hotel and Lounge</h2>
        </div>
        <div class="row g-4">
            @foreach($galleryPreview as $image)
            <div class="col-lg-2 col-md-4 col-6">
                <div class="gallery-card overflow-hidden rounded-4 shadow-sm">
                    <img src="{{ asset('storage/'.$image->path) }}" alt="{{ $image->title }}" class="img-fluid w-100" style="height: 160px; object-fit: cover;">
                </div>
            </div>
            @endforeach
        </div>
        <div class="text-center mt-5">
            <a href="{{ route('gallery') }}" class="btn-outline">View Full Gallery</a>
        </div>
    </div>
</section>
@endif

<section id="testimonials" class="py-5 bg-white">
    <div class="container">
        <div class="section-heading text-center mb-5">
            <span class="section-subtitle">Guest Reviews</span>
            <h2 class="section-title">What Guests Are Saying</h2>
            <p class="text-muted">Real guest feedback from beautiful stays at Newwoodland Hotel and Lounge.</p>
        </div>
        <div class="row g-4">
            @forelse($testimonials as $testimonial)
            <div class="col-lg-4">
                <div class="testimonial-card p-4 bg-light rounded-4 border shadow-sm h-100">
                    <div class="testimonial-rating">@for($i = 0; $i < $testimonial->rating; $i++)<i class="bi bi-star-fill"></i>@endfor</div>
                    <p class="mb-4">&ldquo;{{ $testimonial->content }}&rdquo;</p>
                    <div class="testimonial-user d-flex align-items-center gap-3">
                        <div>
                            <h5 class="mb-0">{{ $testimonial->name }}</h5>
                            <span class="text-muted small">{{ $testimonial->location }}</span>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 text-center text-muted">No testimonials are available at this time.</div>
            @endforelse
        </div>
    </div>
</section>

<section id="faq" class="py-5 bg-light">
    <div class="container">
        <div class="section-heading text-center mb-5">
            <span class="section-subtitle">Frequently Asked Questions</span>
            <h2 class="section-title">Need to Know</h2>
            <p class="text-muted">Answers to the most common questions about your stay.</p>
        </div>
        <div class="faq-wrapper">
            @foreach($faqs as $index => $faq)
            <div class="faq-item {{ $index === 0 ? 'active' : '' }}">
                <button class="faq-question" type="button">
                    <span>{{ $faq->question }}</span>
                    <i class="bi bi-plus-lg"></i>
                </button>
                <div class="faq-answer">
                    <p>{{ $faq->answer }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
