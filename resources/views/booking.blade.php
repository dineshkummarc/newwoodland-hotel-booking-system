@extends('layouts.app')

@section('title', 'Book Your Stay | Newwoodland Hotel & Lounge')
@section('meta_description', 'Reserve your room at Newwoodland Hotel & Lounge in Bhadrapur with a quick and easy booking request.')
@section('canonical', route('booking'))

@section('content')
<section class="page-banner booking-hero text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Instant Reservation</span>
            <h1 class="font-heading">Book Your Stay</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Reserve your room at Newwoodland Hotel & Lounge in Bhadrapur with a quick and easy booking request.
            </p>
        </div>
    </div>
</section>

<section id="booking-form-section" class="py-5 bg-light">
    <div class="container">
        <div class="booking-form-box p-4 p-md-5 bg-white rounded-4 shadow-sm border">
            @if($errors->any())
                <div class="alert alert-danger">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @if($rooms->isEmpty())
                <p class="text-center text-muted">No rooms are currently available for booking. Please check back soon.</p>
            @else
            <form action="{{ route('booking.store') }}" method="POST">
                @csrf
                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="booking-check-in" class="booking-label">Check-in Date *</label>
                        <input id="booking-check-in" type="date" name="check_in" class="form-control" value="{{ old('check_in') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label for="booking-check-out" class="booking-label">Check-out Date *</label>
                        <input id="booking-check-out" type="date" name="check_out" class="form-control" value="{{ old('check_out') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label for="booking-room" class="booking-label">Room Type *</label>
                        <select id="booking-room" name="room_id" class="form-select" required>
                            <option value="">Select Room</option>
                            @foreach($rooms as $room)
                            <option value="{{ $room->id }}" data-price="{{ $room->price_per_night }}" data-per-person="{{ $room->price_per_person ? 1 : 0 }}" {{ $selectedRoomId == $room->id ? 'selected' : '' }}>
                                {{ $room->name }} — {{ npr($room->price_per_night) }} {{ $room->priceUnit() }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="booking-guests" class="booking-label">Guests *</label>
                        <select id="booking-guests" name="guests_label" class="form-select" required>
                            <option value="">Select</option>
                            <option value="1 Adult" {{ old('guests_label') === '1 Adult' ? 'selected' : '' }}>1 Adult</option>
                            <option value="2 Adults" {{ old('guests_label') === '2 Adults' ? 'selected' : '' }}>2 Adults</option>
                            <option value="3 Adults" {{ old('guests_label') === '3 Adults' ? 'selected' : '' }}>3 Adults</option>
                            <option value="4 Adults" {{ old('guests_label') === '4 Adults' ? 'selected' : '' }}>4 Adults</option>
                            <option value="5 Adults" {{ old('guests_label') === '5 Adults' ? 'selected' : '' }}>5 Adults</option>
                            <option value="Family" {{ old('guests_label') === 'Family' ? 'selected' : '' }}>Family</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="booking-rooms" class="booking-label">Rooms</label>
                        <select id="booking-rooms" name="rooms_count" class="form-select">
                            @for($n = 1; $n <= \App\Http\Requests\StoreBookingRequest::MAX_ROOMS; $n++)
                            <option value="{{ $n }}" {{ (int) old('rooms_count', 1) === $n ? 'selected' : '' }}>{{ $n }} {{ Str::plural('room', $n) }}</option>
                            @endfor
                        </select>
                        <div class="form-text">Booking more than one room? Guests above are per room.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="booking-name" class="booking-label">Full Name *</label>
                        <input id="booking-name" type="text" name="customer_name" class="form-control" value="{{ old('customer_name') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label for="booking-email" class="booking-label">Email Address *</label>
                        <input id="booking-email" type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="col-md-6">
                        @include('partials.phone-input', [
                            'id' => 'booking-phone',
                            'label' => 'Phone Number',
                            'required' => true,
                        ])
                    </div>
                    <div class="col-md-6">
                        <label for="booking-country" class="booking-label">Country</label>
                        <input id="booking-country" type="text" name="country" class="form-control" value="{{ old('country') }}">
                    </div>
                    <div class="col-12">
                        <label for="booking-requests" class="booking-label">Special Requests</label>
                        <textarea id="booking-requests" name="special_requests" class="form-control" rows="4">{{ old('special_requests') }}</textarea>
                    </div>

                    <div class="col-12">
                        <div id="bookingEstimate" class="booking-summary-box mb-3" style="display:none;"></div>
                        @if($firstBookingDiscount ?? 0)
                        <p class="small text-success mb-3"><i class="bi bi-tag-fill me-1"></i> First time booking with us? You get {{ percent($firstBookingDiscount) }} off — applied automatically on the next step.</p>
                        @endif
                        <div id="availabilityResult" class="mb-3"></div>
                        @include('partials.stay-policies', ['class' => 'mb-3'])
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
