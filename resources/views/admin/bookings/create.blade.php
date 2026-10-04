@extends('layouts.admin')

@section('title', 'New Booking')

@section('content')
<div class="table-section">
    <h2>Walk-in / Phone Booking</h2>
    <p class="text-muted small">Creates a held (pending) booking exactly like the public site — price is always calculated server-side. Record a payment or confirm it from the booking page afterward.</p>

    @if($rooms->isEmpty())
        <p class="text-muted">No rooms are currently bookable (check room availability status).</p>
    @else
    <form action="{{ route('admin.bookings.store') }}" method="POST">
        @csrf
        <div class="form-grid">
            <div>
                <label class="form-label">Check-in Date *</label>
                <input type="date" name="check_in" class="form-control" value="{{ old('check_in') }}" required>
            </div>
            <div>
                <label class="form-label">Check-out Date *</label>
                <input type="date" name="check_out" class="form-control" value="{{ old('check_out') }}" required>
            </div>
            <div>
                <label class="form-label">Room *</label>
                <select name="room_id" class="form-select" required>
                    <option value="">Select Room</option>
                    @foreach($rooms as $room)
                    <option value="{{ $room->id }}" {{ old('room_id') == $room->id ? 'selected' : '' }}>
                        {{ $room->label() }} — {{ npr($room->price_per_night) }} {{ $room->priceUnit() }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Guests *</label>
                <select name="guests_label" class="form-select" required>
                    <option value="">Select</option>
                    <option value="1 Adult" {{ old('guests_label') === '1 Adult' ? 'selected' : '' }}>1 Adult</option>
                    <option value="2 Adults" {{ old('guests_label') === '2 Adults' ? 'selected' : '' }}>2 Adults</option>
                    <option value="3 Adults" {{ old('guests_label') === '3 Adults' ? 'selected' : '' }}>3 Adults</option>
                    <option value="4 Adults" {{ old('guests_label') === '4 Adults' ? 'selected' : '' }}>4 Adults</option>
                    <option value="5 Adults" {{ old('guests_label') === '5 Adults' ? 'selected' : '' }}>5 Adults</option>
                    <option value="Family" {{ old('guests_label') === 'Family' ? 'selected' : '' }}>Family</option>
                </select>
            </div>
            <div>
                <label class="form-label">Guest Name *</label>
                <input type="text" name="customer_name" class="form-control" value="{{ old('customer_name') }}" required>
            </div>
            <div>
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            </div>
            <div>
                <label class="form-label">Phone *</label>
                <input type="tel" name="phone" class="form-control" value="{{ old('phone') }}" required>
            </div>
            <div>
                <label class="form-label">Country</label>
                <input type="text" name="country" class="form-control" value="{{ old('country') }}">
            </div>
            <div class="full-width">
                <label class="form-label">Additional Rooms <span class="text-muted small">(optional — one booking for several rooms, same dates)</span></label>
                @for($i = 0; $i < \App\Http\Requests\StoreBookingRequest::MAX_ROOMS - 1; $i++)
                <div class="row g-2 mb-2">
                    <div class="col-md-7">
                        <select name="additional_rooms[{{ $i }}][room_id]" class="form-select">
                            <option value="">— No additional room —</option>
                            @foreach($rooms as $room)
                            <option value="{{ $room->id }}" {{ old("additional_rooms.$i.room_id") == $room->id ? 'selected' : '' }}>
                                {{ $room->label() }} — {{ npr($room->price_per_night) }} {{ $room->priceUnit() }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <select name="additional_rooms[{{ $i }}][guests_label]" class="form-select">
                            <option value="">Guests in this room</option>
                            @foreach(['1 Adult', '2 Adults', '3 Adults', '4 Adults', '5 Adults', 'Family'] as $label)
                            <option value="{{ $label }}" {{ old("additional_rooms.$i.guests_label") === $label ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @endfor
            </div>
            <div class="full-width">
                <label class="form-label">Special Requests</label>
                <textarea name="special_requests" class="form-control" rows="3">{{ old('special_requests') }}</textarea>
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Create Booking</button>
        </div>
    </form>
    @endif
</div>
@endsection
