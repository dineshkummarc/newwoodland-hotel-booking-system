@extends('layouts.admin')

@section('title', 'Booking ' . $booking->confirmation_code)

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="table-section">
            <h2>Booking Details</h2>
            <table class="table">
                <tr><td class="text-muted">Confirmation Code</td><td><strong>{{ $booking->confirmation_code }}</strong></td></tr>
                <tr><td class="text-muted">Room</td><td>{{ $booking->room->name ?? '—' }}</td></tr>
                <tr><td class="text-muted">Guest</td><td>{{ $booking->customer_name }}</td></tr>
                <tr><td class="text-muted">Email</td><td>{{ $booking->email }}</td></tr>
                <tr><td class="text-muted">Phone</td><td>{{ $booking->phone }}</td></tr>
                <tr><td class="text-muted">Country</td><td>{{ $booking->country ?: '—' }}</td></tr>
                <tr><td class="text-muted">Check-in</td><td>{{ $booking->check_in->format('D, d M Y') }}</td></tr>
                <tr><td class="text-muted">Check-out</td><td>{{ $booking->check_out->format('D, d M Y') }}</td></tr>
                <tr><td class="text-muted">Nights</td><td>{{ $booking->nights }}</td></tr>
                <tr><td class="text-muted">Guests</td><td>{{ $booking->guests_label }}</td></tr>
                <tr><td class="text-muted">Nightly Rate</td><td>{{ npr($booking->nightly_rate) }}</td></tr>
                <tr><td class="text-muted">Total Price</td><td><strong>{{ npr($booking->total_price) }}</strong></td></tr>
                <tr><td class="text-muted">Special Requests</td><td>{{ $booking->special_requests ?: '—' }}</td></tr>
                <tr><td class="text-muted">Booked On</td><td>{{ $booking->created_at->format('d M Y, g:i A') }}</td></tr>
            </table>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="table-section">
            <h2>Status</h2>
            <p><span class="status {{ $booking->status }}">{{ $booking->status }}</span></p>
            <form action="{{ route('admin.bookings.status', $booking) }}" method="POST">
                @csrf @method('PATCH')
                <select name="status" class="form-select mb-3">
                    @foreach(['pending', 'confirmed', 'cancelled'] as $status)
                        <option value="{{ $status }}" {{ $booking->status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn-admin-primary w-100">Update Status</button>
            </form>
        </div>
    </div>
</div>
@endsection
