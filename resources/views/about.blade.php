@extends('layouts.app')

@section('title', 'About Newwoodland Hotel & Lounge')
@section('meta_description', 'Newwoodland Hotel & Lounge in Bhadrapur-8, Jhapa, Nepal — a family-friendly hotel offering comfortable accommodation, attentive service and genuine Nepali hospitality.')
@section('canonical', route('about'))

{{--
    About page — client-approved copy. Order:
    Hero → Our story → Our approach → Our people → Our commitment → Final welcome.
    (Facilities are deliberately NOT listed here — they have their own page.)

    Content rules: only client-provided/confirmed wording. No founding year,
    awards, statistics, owner biographies or "best/only/luxury" claims.
    Photos: named slots in config/about.php — real hotel photos only;
    a branded placeholder is shown until they are provided.
--}}
@php
    $slots = config('about.slots');
    $address = $hotelAddress ?: 'Bhadrapur-8, Jhapa, Nepal';
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/about.css') }}?v={{ filemtime(public_path('css/about.css')) }}">
@endpush

@section('content')

{{-- 01 · HERO --}}
<section id="about-hero" class="about-hero" aria-labelledby="about-hero-title">
    @include('partials.about-image', ['path' => $slots['hero'], 'alt' => 'Shared guest spaces at Newwoodland Hotel & Lounge, Bhadrapur', 'label' => 'Shared Hotel Spaces', 'icon' => 'bi-building', 'class' => 'about-hero__media', 'eager' => true])
    <div class="about-hero__shade" aria-hidden="true"></div>
    <div class="container about-hero__inner">
        <span class="about-eyebrow about-eyebrow--light">About Newwoodland</span>
        <h1 id="about-hero-title" class="about-hero__title">Where Comfort Meets Togetherness</h1>
        <p class="about-hero__lead">
            Located in {{ $address }}, <strong>Newwoodland Hotel &amp; Lounge</strong> is a family-friendly hotel
            committed to providing comfortable accommodation and welcoming hospitality.
        </p>
        <p class="about-hero__lead">
            We offer a relaxed environment for families, friends and travelers, supported by attentive service,
            well-maintained surroundings and genuine Nepali hospitality. Our focus is to provide guests with a
            comfortable and pleasant stay from arrival to departure.
        </p>
        <div class="about-actions">
            <a href="#our-story" class="btn-gold">Explore Newwoodland</a>
            <a href="{{ route('booking') }}" class="about-btn-ghost">Check Availability</a>
        </div>
    </div>
</section>

{{-- 02 · OUR STORY --}}
<section id="our-story" class="about-section about-story">
    <div class="container">
        <div class="about-story__text">
            <span class="about-eyebrow">Our Story</span>
            <h2 class="about-heading">Built Around Comfort, Leisure &amp; Togetherness</h2>
            <p class="about-text about-text--lead">
                Newwoodland was established with the aim of providing a comfortable and welcoming place for guests
                to stay, relax and spend quality time together.
            </p>
            <p class="about-text">
                We believe that a positive hospitality experience is built on comfort, cleanliness, attentive service
                and a welcoming environment. These principles continue to guide the way we develop and operate Newwoodland.
            </p>
            <p class="about-text">
                Our goal is to provide a reliable and enjoyable stay for families, friends and travelers visiting
                Bhadrapur and the surrounding area.
            </p>
        </div>
    </div>
    <div class="container-fluid px-0 mt-4 mt-lg-5">
        @include('partials.about-image', ['path' => $slots['story'], 'alt' => 'Wide view of the Newwoodland Hotel & Lounge property', 'label' => 'Wide Property View', 'icon' => 'bi-tree', 'class' => 'about-photo--panorama'])
    </div>
</section>

{{-- 03 · OUR APPROACH --}}
<section id="our-approach" class="about-section about-section--ivory">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-4">
                <span class="about-eyebrow">Our Approach</span>
                <h2 class="about-heading">Hospitality That Begins With a Warm Welcome</h2>
                <p class="about-text">
                    At Newwoodland, we believe that professional hospitality begins with a warm welcome and continues
                    through consistent care and attentive service.
                </p>
            </div>
            <div class="col-lg-8">
                <ol class="about-principles">
                    <li>
                        <span class="about-principles__num" aria-hidden="true">01</span>
                        <h3>Comfort</h3>
                        <p>Providing clean, comfortable and well-maintained spaces for our guests.</p>
                    </li>
                    <li>
                        <span class="about-principles__num" aria-hidden="true">02</span>
                        <h3>Care</h3>
                        <p>Responding to guest needs with attention, courtesy and consideration.</p>
                    </li>
                    <li>
                        <span class="about-principles__num" aria-hidden="true">03</span>
                        <h3>Cleanliness</h3>
                        <p>Maintaining rooms, shared spaces and surroundings to a clean and appropriate standard.</p>
                    </li>
                    <li>
                        <span class="about-principles__num" aria-hidden="true">04</span>
                        <h3>Warm Hospitality</h3>
                        <p>Treating every guest with respect, courtesy and genuine Nepali hospitality.</p>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</section>

{{-- 04 · OUR PEOPLE — the confirmed owners (names + role only) --}}
<section id="about-owners" class="about-section about-section--ivory">
    <div class="container">
        <div class="text-center about-narrow mx-auto">
            <span class="about-eyebrow">Our People</span>
            <h2 class="about-heading">The People Behind Newwoodland</h2>
            <p class="about-text">
                Led by its owners and supported by a dedicated team, Newwoodland Hotel &amp; Lounge is committed
                to refined hospitality and attentive care for every guest.
            </p>
        </div>
        <div class="row g-4 justify-content-center mt-2">
            @foreach(config('about.owners') as $owner)
                @php
                    $hasPhoto = \Illuminate\Support\Facades\Storage::disk('public')->exists($owner['photo']);
                    $initials = collect(explode(' ', $owner['name']))->map(fn ($p) => mb_substr($p, 0, 1))->join('');
                @endphp
                <div class="col-sm-6 col-lg-4">
                    <figure class="about-owner">
                        <div class="about-owner__portrait">
                            @if($hasPhoto)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($owner['photo']) }}" alt="Portrait of {{ $owner['name'] }}" loading="lazy">
                            @else
                                {{-- CLIENT INPUT REQUIRED: portrait → storage/app/public/{{ $owner['photo'] }} --}}
                                <span class="about-owner__initials" aria-hidden="true">{{ $initials }}</span>
                            @endif
                        </div>
                        <figcaption>
                            <h3 class="about-owner__name">{{ $owner['name'] }}</h3>
                            <span class="about-owner__role">{{ $owner['role'] }}</span>
                            @if($owner['experience'] ?? null)
                                <span class="about-owner__experience">{{ $owner['experience'] }}</span>
                            @endif
                            @if($owner['message'])
                                <p class="about-owner__message">{{ $owner['message'] }}</p>
                            @endif
                        </figcaption>
                    </figure>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- 05 · OUR COMMITMENT --}}
<section id="our-commitment" class="about-section">
    <div class="container">
        <div class="row g-4 g-lg-5 align-items-start">
            <div class="col-lg-5">
                <span class="about-eyebrow">Our Commitment</span>
                <h2 class="about-heading">Creating a Stay Worth Returning To</h2>
            </div>
            <div class="col-lg-7">
                <p class="about-text about-text--lead">
                    We are committed to maintaining a comfortable, clean and welcoming environment for every guest.
                </p>
                <p class="about-text">
                    Our team works to provide courteous service, maintain our property responsibly and respond to guest
                    needs with care. We also value guest feedback as an important part of improving our services and standards.
                </p>
            </div>
        </div>
        <div class="about-pledges">
            <div class="about-pledge">
                <h3><i class="bi bi-heart" aria-hidden="true"></i> To Our Guests</h3>
                <p>To provide comfortable accommodation, cleanliness, attentive service and respectful hospitality.</p>
            </div>
            <div class="about-pledge">
                <h3><i class="bi bi-people" aria-hidden="true"></i> To Our Team</h3>
                <p>To promote a professional, respectful and cooperative working environment.</p>
            </div>
            <div class="about-pledge">
                <h3><i class="bi bi-tree" aria-hidden="true"></i> To Our Property</h3>
                <p>To maintain our rooms, shared spaces, gardens and surroundings responsibly and continue improving the property.</p>
            </div>
        </div>
    </div>
</section>

{{-- 06 · WELCOME --}}
<section id="about-welcome" class="about-section about-welcome">
    <div class="container about-narrow text-center">
        <span class="about-eyebrow about-eyebrow--light">Welcome to Newwoodland</span>
        <h2 class="about-heading about-heading--light">Your Time at Newwoodland, Your Way</h2>
        <p class="about-welcome__text">
            Whether you are visiting Bhadrapur, travelling with family or staying overnight, Newwoodland welcomes you
            with comfortable accommodation, attentive service and genuine Nepali hospitality.
        </p>
        <div class="about-actions justify-content-center">
            <a href="{{ route('rooms.index') }}" class="btn-gold">Explore Our Rooms</a>
            <a href="{{ route('booking') }}" class="about-btn-ghost">Check Availability</a>
        </div>
    </div>
</section>

@endsection
