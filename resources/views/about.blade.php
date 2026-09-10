@extends('layouts.app')

@section('title', 'About Newwoodland Hotel and Lounge')
@section('meta_description', 'Learn about Newwoodland Hotel and Lounge — luxury accommodation and authentic Nepalese hospitality in Bhadrapur-8, Jhapa, Nepal.')
@section('canonical', route('about'))

@section('content')
<section id="about-hero" class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">About Us</span>
            <h1 class="font-heading">Our Story in Bhadrapur</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Newwoodland Hotel and Lounge blends contemporary comfort with warm Nepalese hospitality in a peaceful Bhadrapur setting.
            </p>
        </div>
    </div>
</section>

<section id="about-content" class="py-5 bg-white">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <img src="https://images.unsplash.com/photo-1501117716987-c8e3bceeacd2?auto=format&fit=crop&w=1200&q=80" alt="Hotel Interior" class="img-fluid rounded-4 shadow-lg">
            </div>
            <div class="col-lg-6">
                <span class="section-subtitle">Why Choose Us</span>
                <h2 class="section-title">Hospitality That Feels Like Home</h2>
                <p class="text-muted mb-4">
                    Our hotel offers premium rooms, delicious dining, modern facilities, and attentive service for guests visiting Bhadrapur, Jhapa.
                </p>
                <ul class="list-unstyled text-muted mb-4">
                    <li class="mb-3"><i class="bi bi-check-circle-fill text-warning me-2"></i>Central location near the airport</li>
                    <li class="mb-3"><i class="bi bi-check-circle-fill text-warning me-2"></i>Comfortable rooms with modern amenities</li>
                    <li class="mb-3"><i class="bi bi-check-circle-fill text-warning me-2"></i>Restaurant, pool, and guest services</li>
                </ul>
                <a href="{{ route('booking') }}" class="btn-gold py-3 px-5">Make a Reservation</a>
            </div>
        </div>
    </div>
</section>
@endsection
