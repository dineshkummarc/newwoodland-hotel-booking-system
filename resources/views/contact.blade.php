@extends('layouts.app')

@section('title', 'Contact Us | Newwoodland Hotel and Lounge')
@section('meta_description', 'Get in touch with Newwoodland Hotel and Lounge for reservations, inquiries, and special requests.')
@section('canonical', route('contact'))

@section('content')
@if(session('success'))
<div class="container mt-4">
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
@endif
@if($errors->any())
<div class="container mt-4">
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
@endif
<section id="page-hero" class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Get In Touch</span>
            <h1 class="font-heading">Contact Newwoodland Hotel and Lounge</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                We are ready to assist you with reservations, inquiries, travel directions, and special requests.
            </p>
        </div>
    </div>
</section>

<section id="contact-cards" class="py-5 bg-white">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="contact-card p-4 bg-light rounded-4 shadow-sm border text-center h-100">
                    <div class="icon fs-1 text-warning mb-3"><i class="bi bi-geo-alt-fill"></i></div>
                    <h4 class="font-heading text-emerald mb-2">Our Location</h4>
                    <p class="text-muted mb-3">{{ $hotelAddress ?? 'Bhadrapur-8, Jhapa, Nepal' }}</p>
                    <a href="#map" class="btn btn-sm btn-outline-dark rounded-pill px-4">View Map</a>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="contact-card p-4 bg-light rounded-4 shadow-sm border text-center h-100">
                    <div class="icon fs-1 text-warning mb-3"><i class="bi bi-telephone-fill"></i></div>
                    <h4 class="font-heading text-emerald mb-2">Call Us</h4>
                    <p class="text-muted mb-3">{{ $hotelPhone ?? '+977 9801415107' }}</p>
                    <a href="tel:{{ $hotelPhoneRaw ?? '+9779801415107' }}" class="btn btn-sm btn-outline-dark rounded-pill px-4">Call Now</a>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="contact-card p-4 bg-light rounded-4 shadow-sm border text-center h-100">
                    <div class="icon fs-1 text-warning mb-3"><i class="bi bi-envelope-fill"></i></div>
                    <h4 class="font-heading text-emerald mb-2">Email Us</h4>
                    <p class="text-muted mb-3">{{ $hotelEmail ?? 'info@newwoodlandhotel.com' }}</p>
                    <a href="mailto:{{ $hotelEmail ?? 'info@newwoodlandhotel.com' }}" class="btn btn-sm btn-outline-dark rounded-pill px-4">Send Email</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="contact-form-section" class="py-5 bg-light">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <span class="section-subtitle">Reservation Inquiry</span>
                <h2 class="section-title">Plan Your Perfect Stay With Us</h2>
                <p class="text-muted">Send us a message and our team will contact you shortly.</p>
                <div class="contact-features mt-4">
                    <div class="d-flex align-items-center gap-3 mb-3"><i class="bi bi-check-circle-fill text-warning fs-5"></i> <span>24/7 Assistance</span></div>
                    <div class="d-flex align-items-center gap-3 mb-3"><i class="bi bi-check-circle-fill text-warning fs-5"></i> <span>Quick Booking Support</span></div>
                    <div class="d-flex align-items-center gap-3 mb-3"><i class="bi bi-check-circle-fill text-warning fs-5"></i> <span>Personalized Service</span></div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="contact-form-box p-4 p-md-5 bg-white rounded-4 shadow-sm border">
                    <form action="{{ route('contact.store') }}" method="POST">
                        @csrf
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label for="contact-name" class="form-label fw-medium">Full Name *</label>
                                <input id="contact-name" type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="contact-email" class="form-label fw-medium">Email Address *</label>
                                <input id="contact-email" type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="contact-phone" class="form-label fw-medium">Phone Number *</label>
                                <input id="contact-phone" type="tel" name="phone" class="form-control" value="{{ old('phone') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="contact-subject" class="form-label fw-medium">Subject</label>
                                <input id="contact-subject" type="text" name="subject" class="form-control" value="{{ old('subject') }}">
                            </div>
                            <div class="col-12">
                                <label for="contact-message" class="form-label fw-medium">Message *</label>
                                <textarea id="contact-message" name="message" class="form-control" rows="5" required>{{ old('message') }}</textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn-gold py-3 px-5 fs-6">Send Message</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="map" class="py-5 bg-white">
    <div class="container">
        <div class="section-heading text-center mb-4">
            <span class="section-subtitle">Find Us</span>
            <h2 class="section-title">Our Hotel Location</h2>
            <p class="text-muted">Conveniently located in {{ $hotelAddress ?? 'Bhadrapur-8, Jhapa, Nepal' }}.</p>
        </div>
        <div class="map-wrapper rounded-4 overflow-hidden shadow-sm border">
            <iframe src="https://www.google.com/maps?q=Bhadrapur%20Jhapa%20Nepal&output=embed" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </div>
</section>
@endsection
