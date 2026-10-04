@extends('layouts.app')

@section('title', 'Newwoodland Hotel & Lounge | Hotel in Bhadrapur-8, Jhapa')
@section('meta_description', 'Newwoodland Hotel & Lounge offers luxury accommodation, premium dining, modern facilities, and exceptional hospitality in Bhadrapur-8, Jhapa, Nepal.')
@section('canonical', route('home'))
@php
    // Client photo slots (config/photos.php): real photo when uploaded,
    // otherwise a labelled placeholder.
    $heroPhoto = config('photos.home_hero');
    $heroPhotoUrl = site_photo_url($heroPhoto['path']);
    $introPhoto = config('photos.home_intro');
    $introPhotoUrl = site_photo_url($introPhoto['path']);
@endphp
@if($heroPhotoUrl)
    @section('og_image', $heroPhotoUrl)
@endif

@push('structured_data')
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "Hotel",
    "name": "Newwoodland Hotel & Lounge",
    @if($heroPhotoUrl)"image": "{{ $heroPhotoUrl }}",@endif
    "url": "{{ route('home') }}",
    "telephone": "{{ $hotelPhone ?? '+977 9852690590' }}",
    "email": "{{ $hotelEmail ?? 'woodlandhotel146@gmail.com' }}",
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
<section id="hero" class="page-banner text-center{{ $heroPhotoUrl ? ' page-banner--photo' : '' }}" @if($heroPhotoUrl) style="background-image:url('{{ $heroPhotoUrl }}'); background-size:cover; background-position:center;" @endif>
    @unless($heroPhotoUrl)
        {{-- CLIENT INPUT REQUIRED: storage/app/public/{{ $heroPhoto['path'] }} --}}
        <span class="hero-photo-note"><i class="bi bi-camera" aria-hidden="true"></i> {{ $heroPhoto['label'] }} — Client Photo Required</span>
    @endunless
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Welcome to Newwoodland Hotel & Lounge</span>
            <h1 class="font-heading">Experience Timeless Comfort</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Newwoodland Hotel & Lounge offers luxury accommodation, premium dining, modern facilities, and exceptional hospitality in Bhadrapur-8, Jhapa, Nepal.
            </p>
            <div class="hero-buttons d-flex justify-content-center gap-3 mt-4">
                <a href="{{ route('booking') }}" class="btn-gold py-3 px-5 fs-6">Book Your Stay</a>
                <a href="{{ route('rooms.index') }}" class="btn-outline text-white border-white py-3 px-5 fs-6">Explore Rooms</a>
            </div>
        </div>
    </div>
</section>

@if($firstBookingDiscount || $guestSatisfaction)
<section id="home-highlights" class="home-highlights py-4">
    <div class="container">
        <div class="row g-3 align-items-center justify-content-center text-center text-md-start">
            @if($firstBookingDiscount)
            <div class="col-md-8 d-flex align-items-center justify-content-center justify-content-md-start gap-3">
                <span class="home-highlights__badge">{{ percent($firstBookingDiscount) }} OFF</span>
                <div>
                    <strong class="d-block font-heading fs-5">Your first booking is {{ percent($firstBookingDiscount) }} off</strong>
                    <span class="small">Book directly on our website — the discount is applied automatically.</span>
                </div>
            </div>
            @endif
            @if($guestSatisfaction)
            <div class="col-md-4 d-flex align-items-center justify-content-center justify-content-md-end gap-2">
                <i class="bi bi-people-fill fs-2 text-gold"></i>
                <div>
                    <strong class="d-block font-heading fs-4">{{ $guestSatisfaction }}</strong>
                    <span class="small">guests served to date</span>
                </div>
            </div>
            @endif
        </div>
    </div>
</section>
@endif

<section id="about-preview" class="py-5 bg-white">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                @if($introPhotoUrl)
                    <img src="{{ $introPhotoUrl }}" alt="{{ $introPhoto['alt'] }}" class="img-fluid rounded-4 shadow-lg" loading="lazy" decoding="async">
                @else
                    <div class="photo-frame photo-frame--intro rounded-4 shadow-lg">
                        @include('partials.photo-placeholder', ['label' => $introPhoto['label'], 'icon' => 'bi-buildings'])
                    </div>
                @endif
            </div>
            <div class="col-lg-6">
                <span class="section-subtitle">About Newwoodland Hotel & Lounge</span>
                <h2 class="section-title">Comfort & Authentic Nepalese Hospitality</h2>
                <p class="text-muted leading-relaxed">
                    Located in Bhadrapur-8, Jhapa, Nepal, Newwoodland Hotel & Lounge provides comfortable and luxurious accommodation for business travelers, families, and leisure guests alike.
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
            <h2 class="section-title">Our Rooms</h2>
            <p class="text-muted">Enjoy beautifully appointed rooms, modern amenities, and authentic Nepalese hospitality during your stay.</p>
        </div>
        <div class="row g-4">
            @forelse($featuredRooms as $room)
            <div class="col-lg-4 col-md-6">
                @include('partials.room-card', ['room' => $room])
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
                @include('partials.facility-card', ['facility' => $facility])
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
            <h2 class="section-title">A Glimpse of Newwoodland Hotel & Lounge</h2>
        </div>
        <div class="row g-4">
            @foreach($galleryPreview as $image)
            <div class="col-lg-2 col-md-4 col-6">
                <div class="gallery-card overflow-hidden rounded-4 shadow-sm">
                    <img src="{{ asset('storage/'.$image->path) }}" alt="{{ $image->altText() }}" class="img-fluid w-100" style="height: 160px; object-fit: cover;" loading="lazy" decoding="async">
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
            <p class="text-muted">Real guest feedback from beautiful stays at Newwoodland Hotel & Lounge.</p>
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
