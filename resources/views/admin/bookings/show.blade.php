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
                @if($booking->cancelled_at)
                <tr><td class="text-muted">Cancelled On</td><td>{{ $booking->cancelled_at->format('d M Y, g:i A') }}</td></tr>
                @endif
            </table>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="table-section mb-4">
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

        <div class="table-section">
            <h2>Payment</h2>
            @php $latestPayment = $booking->payments->sortByDesc('created_at')->first(); @endphp
            @if($latestPayment)
                @if($latestPayment->status === 'completed' && $booking->status !== 'confirmed')
                    <div class="alert alert-danger">
                        Payment was completed but this booking could not be auto-confirmed
                        (the room was no longer available for these dates). Please review —
                        a refund or manual reassignment may be required.
                    </div>
                @endif
                <table class="table">
                    <tr><td class="text-muted">Provider</td><td>{{ payment_provider_label($latestPayment->provider) }}</td></tr>
                    <tr><td class="text-muted">Status</td><td><span class="status {{ $latestPayment->status === 'completed' ? 'confirmed' : $latestPayment->status }}">{{ ucfirst($latestPayment->status) }}</span></td></tr>
                    <tr><td class="text-muted">Amount</td><td>{{ npr($latestPayment->amount) }}</td></tr>
                    <tr><td class="text-muted">Transaction Ref</td><td>{{ $latestPayment->reference_id ?: '—' }}</td></tr>
                    <tr><td class="text-muted">Paid At</td><td>{{ $latestPayment->paid_at?->format('d M Y, g:i A') ?: '—' }}</td></tr>
                </table>
                @if($booking->payments->count() > 1)
                    <p class="text-muted small mb-0">{{ $booking->payments->count() }} payment attempts on record for this booking.</p>
                @endif
            @else
                <p class="text-muted mb-0">No payment attempt has been made yet.</p>
            @endif
        </div>
    </div>
</div>
@endsection
