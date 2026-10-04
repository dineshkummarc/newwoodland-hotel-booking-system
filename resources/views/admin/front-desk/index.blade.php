@extends('layouts.admin')

@section('title', 'Front Desk')

@section('content')
<div class="row g-4 mb-2">
    <div class="col-lg-4">
        <div class="table-section h-100">
            <h2><i class="bi bi-box-arrow-in-right text-success"></i> Today's Arrivals <span class="text-muted small">({{ $todaysArrivals->count() }})</span></h2>
            @forelse($todaysArrivals as $booking)
                <div class="border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>{{ $booking->customer_name }}</strong>@if($vipEmails->contains(strtolower($booking->email))) <span class="status vip">VIP</span>@endif<br>
                            <span class="text-muted small">{{ $booking->roomsSummary() }} &bull; {{ $booking->confirmation_code }}</span>
                        </div>
                        <span class="status {{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                    </div>
                    <form action="{{ route('admin.bookings.check-in', $booking) }}" method="POST" class="mt-2">
                        @csrf @method('PATCH')
                        @include('admin.bookings._photo-id-check', ['idSuffix' => 'desk'])
                        <button type="submit" class="btn btn-sm btn-admin-primary w-100">Check In</button>
                    </form>
                </div>
            @empty
                <p class="text-muted mb-0">No arrivals scheduled for today.</p>
            @endforelse
        </div>
    </div>

    <div class="col-lg-4">
        <div class="table-section h-100">
            <h2><i class="bi bi-box-arrow-right text-danger"></i> Today's Departures <span class="text-muted small">({{ $todaysDepartures->count() }})</span></h2>
            @forelse($todaysDepartures as $booking)
                <div class="border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>{{ $booking->customer_name }}</strong>@if($vipEmails->contains(strtolower($booking->email))) <span class="status vip">VIP</span>@endif<br>
                            <span class="text-muted small">{{ $booking->roomsSummary() }} &bull; {{ $booking->confirmation_code }}</span>
                        </div>
                        <span class="status {{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                    </div>
                    <form action="{{ route('admin.bookings.check-out', $booking) }}" method="POST" class="mt-2">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-sm btn-admin-primary w-100">Check Out</button>
                    </form>
                </div>
            @empty
                <p class="text-muted mb-0">No departures scheduled for today.</p>
            @endforelse
        </div>
    </div>

    <div class="col-lg-4">
        <div class="table-section h-100">
            <h2><i class="bi bi-house-door-fill text-primary"></i> In-House Guests <span class="text-muted small">({{ $inHouseGuests->count() }})</span></h2>
            @forelse($inHouseGuests as $booking)
                <div class="border-bottom py-3">
                    <strong>{{ $booking->customer_name }}</strong>@if($vipEmails->contains(strtolower($booking->email))) <span class="status vip">VIP</span>@endif<br>
                    <span class="text-muted small">{{ $booking->roomsSummary() }} &bull; until {{ $booking->check_out->format('d M') }}</span>
                    <div class="mt-1"><a href="{{ route('admin.bookings.show', $booking) }}" class="small">View booking</a></div>
                </div>
            @empty
                <p class="text-muted mb-0">No guests currently checked in.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="table-section">
    <h2>Upcoming Arrivals (Next Confirmed Bookings)</h2>
    <table class="table align-middle">
        <thead><tr><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th></th></tr></thead>
        <tbody>
            @forelse($upcomingArrivals as $booking)
            <tr>
                <td>{{ $booking->customer_name }}@if($vipEmails->contains(strtolower($booking->email))) <span class="status vip">VIP</span>@endif</td>
                <td>{{ $booking->roomsSummary() }}</td>
                <td>{{ $booking->check_in->format('d M Y') }}</td>
                <td>{{ $booking->check_out->format('d M Y') }}</td>
                <td class="text-end"><a href="{{ route('admin.bookings.show', $booking) }}" class="btn btn-sm btn-outline-dark">View</a></td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-muted">No upcoming confirmed arrivals.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
