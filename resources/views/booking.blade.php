@extends('layouts.app')

@section('title', 'Book Your Stay | New Woodland Hotel')
@section('meta_description', 'Reserve your room at New Woodland Hotel in Bhadrapur with a quick and easy booking request.')
@section('canonical', route('booking'))

@section('content')
<section class="page-banner booking-hero text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Instant Reservation</span>
            <h1 class="font-heading">Book Your Stay</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Reserve your room at New Woodland Hotel in Bhadrapur with a quick and easy booking request.
            </p>
        </div>
    </div>
</section>

<section id="booking-form-section" class="py-5 bg-light">
    <div class="container">
        <div class="booking-form-box p-4 p-md-5 bg-white rounded-4 shadow-sm border">
            @if($rooms->isEmpty())
                <p class="text-center text-muted">No rooms are currently available for booking. Please check back soon.</p>
            @else
            <form action="{{ route('booking.store') }}" method="POST">
                @csrf
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="booking-label">Check-in Date *</label>
                        <input type="date" name="check_in" class="form-control" value="{{ old('check_in') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="booking-label">Check-out Date *</label>
                        <input type="date" name="check_out" class="form-control" value="{{ old('check_out') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="booking-label">Room Type *</label>
                        <select name="room_id" class="form-select" required>
                            <option value="">Select Room</option>
                            @foreach($rooms as $room)
                            <option value="{{ $room->id }}" data-price="{{ $room->price_per_night }}" {{ (old('room_id', request('room')) == $room->id) ? 'selected' : '' }}>
                                {{ $room->name }} — {{ npr($room->price_per_night) }}/night
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="booking-label">Guests *</label>
                        <select name="guests_label" class="form-select" required>
                            <option value="">Select</option>
                            <option value="1 Adult" {{ old('guests_label') === '1 Adult' ? 'selected' : '' }}>1 Adult</option>
                            <option value="2 Adults" {{ old('guests_label') === '2 Adults' ? 'selected' : '' }}>2 Adults</option>
                            <option value="3 Adults" {{ old('guests_label') === '3 Adults' ? 'selected' : '' }}>3 Adults</option>
                            <option value="4 Adults" {{ old('guests_label') === '4 Adults' ? 'selected' : '' }}>4 Adults</option>
                            <option value="Family" {{ old('guests_label') === 'Family' ? 'selected' : '' }}>Family</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="booking-label">Full Name *</label>
                        <input type="text" name="customer_name" class="form-control" value="{{ old('customer_name') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="booking-label">Email Address *</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="booking-label">Phone Number *</label>
                        <input type="tel" name="phone" class="form-control" value="{{ old('phone') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="booking-label">Country</label>
                        <input type="text" name="country" class="form-control" value="{{ old('country') }}">
                    </div>
                    <div class="col-12">
                        <label class="booking-label">Special Requests</label>
                        <textarea name="special_requests" class="form-control" rows="4">{{ old('special_requests') }}</textarea>
                    </div>

                    <div class="col-12">
                        <div id="bookingEstimate" class="booking-summary-box mb-3" style="display:none;"></div>
                        <div id="availabilityResult" class="mb-3"></div>
                    </div>

                    <div class="col-12">
                        <button type="submit" id="bookingSubmitBtn" class="btn-gold py-3 px-5 fs-6">Submit Booking</button>
                        <p class="text-muted small mt-2 mb-0">Your final price is always calculated and confirmed by our system after submission.</p>
                    </div>
                </div>
            </form>
            @endif
        </div>
    </div>
</section>
@endsection
