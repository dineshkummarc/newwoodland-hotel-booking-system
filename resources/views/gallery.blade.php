@extends('layouts.app')

@section('title', 'Gallery | New Woodland Hotel')
@section('meta_description', 'Explore the scenic spaces, elegant rooms, and memorable experiences that await at New Woodland Hotel.')
@section('canonical', route('gallery'))

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Our Gallery</span>
            <h1 class="font-heading">Moments from New Woodland Hotel</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Explore the scenic spaces, elegant rooms, and memorable experiences that await at our hotel.
            </p>
        </div>
    </div>
</section>

<section class="gallery-section py-5 bg-white">
    <div class="container">
        <div class="section-heading text-center mb-5">
            <span class="section-subtitle">Photo Gallery</span>
            <h2 class="section-title">A Visual Tour of Our Hotel</h2>
            <p class="text-muted">See the welcoming interiors, beautiful dining areas, luxurious rooms, and premium hotel facilities.</p>
        </div>

        <div class="gallery-filters">
            <button type="button" data-filter="all" class="active">All</button>
            <button type="button" data-filter="rooms">Rooms</button>
            <button type="button" data-filter="pool">Pool</button>
            <button type="button" data-filter="restaurant">Restaurant</button>
            <button type="button" data-filter="garden">Garden</button>
        </div>

        <div class="row g-4">
            @forelse($images as $image)
            <div class="col-lg-3 col-md-4 col-sm-6 gallery-item" data-category="{{ $image->category }}">
                <div class="gallery-card overflow-hidden rounded-4 shadow-sm">
                    <img src="{{ asset('storage/'.$image->path) }}" alt="{{ $image->title ?: 'New Woodland Hotel' }}" class="img-fluid w-100" style="height: 260px; object-fit: cover;">
                </div>
            </div>
            @empty
            <div class="col-12 text-center text-muted">No gallery images have been added yet.</div>
            @endforelse
        </div>
    </div>
</section>

<div class="lightbox-overlay" id="lightbox">
    <button class="lightbox-close" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    <button class="lightbox-prev" aria-label="Previous"><i class="bi bi-chevron-left"></i></button>
    <img src="" alt="">
    <button class="lightbox-next" aria-label="Next"><i class="bi bi-chevron-right"></i></button>
</div>
@endsection
