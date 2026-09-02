@extends('layouts.app')

@section('title', 'My Bookings | New Woodland Hotel')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">My Account</span>
            <h1 class="font-heading">My Bookings</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Welcome back, {{ auth()->user()->name }}. Here's your reservation history.
            </p>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($bookings->isEmpty())
            <div class="p-5 bg-white rounded-4 shadow-sm border text-center">
                <p class="text-muted mb-4">You haven't made any bookings yet.</p>
                <a href="{{ route('booking') }}" class="btn-gold">Book a Room</a>
            </div>
        @else
            <div class="row g-4">
                @foreach($bookings as $booking)
                <div class="col-lg-6">
                    <div class="p-4 bg-white rounded-4 shadow-sm border h-100">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="section-subtitle d-block">{{ $booking->confirmation_code }}</span>
                                <h3 class="font-heading h5 mb-0">{{ $booking->room->name ?? 'Room unavailable' }}</h3>
                            </div>
                            <span class="status {{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                        </div>
                        <table class="table table-sm mb-3">
                            <tr><td class="text-muted">Check-in</td><td class="text-end fw-semibold">{{ $booking->check_in->format('D, d M Y') }}</td></tr>
                            <tr><td class="text-muted">Check-out</td><td class="text-end fw-semibold">{{ $booking->check_out->format('D, d M Y') }}</td></tr>
                            <tr><td class="text-muted">Nights</td><td class="text-end fw-semibold">{{ $booking->nights }}</td></tr>
                            <tr><td class="text-muted fw-semibold">Total</td><td class="text-end fw-bold text-emerald">{{ npr($booking->total_price) }}</td></tr>
                        </table>
                        <a href="{{ route('booking.confirmation', $booking->confirmation_code) }}" class="btn-outline py-2 px-4">View Details</a>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $bookings->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
