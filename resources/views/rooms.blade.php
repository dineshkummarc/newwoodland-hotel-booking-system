@extends('layouts.app')

@section('title', 'Our Rooms | Newwoodland Hotel & Lounge')
@section('meta_description', 'Explore the room types at Newwoodland Hotel & Lounge in Bhadrapur-8, Jhapa, Nepal — amenities, rates and online booking.')
@section('canonical', route('rooms.index'))

@section('content')
<section id="rooms-hero" class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Luxury Accommodation</span>
            <h1 class="font-heading">Our Rooms</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Explore our room types in Bhadrapur-8, Jhapa, and the amenities each one offers.
            </p>
        </div>
    </div>
</section>

<section id="rooms" class="py-5 bg-white">
    <div class="container">
        <div class="section-heading text-center mb-5">
            <span class="section-subtitle">Room Types</span>
            <h2 class="section-title">Choose Your Perfect Stay</h2>
        </div>
        <div class="row g-4">
            @forelse($rooms as $room)
            <div class="col-lg-4 col-md-6">
                @include('partials.room-card', ['room' => $room, 'cardClass' => 'p-3 bg-light'])
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
