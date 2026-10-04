@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@if($paymentsNeedingReview->isNotEmpty())
<div class="alert alert-danger">
    <strong><i class="bi bi-exclamation-octagon-fill"></i> {{ $paymentsNeedingReview->count() }} {{ Str::plural('payment', $paymentsNeedingReview->count()) }} need{{ $paymentsNeedingReview->count() === 1 ? 's' : '' }} review</strong>
    <ul class="mb-0 mt-2 small">
        @foreach($paymentsNeedingReview as $flagged)
            <li>
                @if($flagged->booking)
                    <a href="{{ route('admin.bookings.show', $flagged->booking) }}">{{ $flagged->booking->confirmation_code }}</a> —
                @endif
                {{ payment_provider_label($flagged->provider) }} {{ npr($flagged->amount) }}: {{ Str::limit($flagged->review_reason, 160) }}
            </li>
        @endforeach
    </ul>
</div>
@endif
<div class="summary-cards">
    <div class="summary-card">
        <div class="icon"><i class="bi bi-door-open-fill"></i></div>
        <div>
            <div class="value">{{ $totalRooms }}</div>
            <div class="label">Total Rooms</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="bi bi-calendar-check-fill"></i></div>
        <div>
            <div class="value">{{ $totalBookings }}</div>
            <div class="label">Total Bookings</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="bi bi-hourglass-split"></i></div>
        <div>
            <div class="value">{{ $pendingBookings }}</div>
            <div class="label">Pending Bookings</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="bi bi-envelope-fill"></i></div>
        <div>
            <div class="value">{{ $unreadMessages }}</div>
            <div class="label">Unread Messages</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="bi bi-cash-coin"></i></div>
        <div>
            <div class="value">{{ npr($monthlyRevenue) }}</div>
            <div class="label">This Month's Confirmed Revenue</div>
        </div>
    </div>
</div>

<h5 class="mt-4 mb-3 text-muted">Today at the Front Desk</h5>
<div class="summary-cards">
    <div class="summary-card">
        <div class="icon"><i class="bi bi-box-arrow-in-right"></i></div>
        <div>
            <div class="value">{{ $todaysArrivals }}</div>
            <div class="label">Today's Arrivals</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="bi bi-box-arrow-right"></i></div>
        <div>
            <div class="value">{{ $todaysDepartures }}</div>
            <div class="label">Today's Departures</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="bi bi-people-fill"></i></div>
        <div>
            <div class="value">{{ $occupiedRooms }}</div>
            <div class="label">In-House Guests</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="bi bi-calendar-plus"></i></div>
        <div>
            <div class="value">{{ $upcomingArrivals }}</div>
            <div class="label">Upcoming Arrivals</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="bi bi-x-circle"></i></div>
        <div>
            <div class="value">{{ $cancelledBookingsThisMonth }}</div>
            <div class="label">Cancelled This Month</div>
        </div>
    </div>
</div>

<h5 class="mt-4 mb-3 text-muted">Room Inventory</h5>
<div class="summary-cards">
    <div class="summary-card">
        <div class="icon"><i class="bi bi-check-circle"></i></div>
        <div>
            <div class="value">{{ $availableRooms }}</div>
            <div class="label">Available Now</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="bi bi-house-fill"></i></div>
        <div>
            <div class="value">{{ $occupiedRooms }}</div>
            <div class="label">Occupied Now</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="bi bi-tools"></i></div>
        <div>
            <div class="value">{{ $maintenanceRooms }}</div>
            <div class="label">Maintenance / Out of Service</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="bi bi-pie-chart-fill"></i></div>
        <div>
            <div class="value">{{ $occupancyPercentage }}%</div>
            <div class="label">Occupancy Rate</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="bi bi-hourglass-bottom"></i></div>
        <div>
            <div class="value">{{ npr($pendingPaymentAmount) }}</div>
            <div class="label">Pending Payment Amount</div>
        </div>
    </div>
</div>

@if($revenueByProvider->isNotEmpty())
<div class="table-section mt-4">
    <h2>This Month's Revenue by Payment Method</h2>
    <table class="table align-middle mb-0">
        <thead><tr><th>Provider</th><th>Amount</th></tr></thead>
        <tbody>
            @foreach($revenueByProvider as $provider => $amount)
            <tr><td>{{ payment_provider_label($provider) }}</td><td>{{ npr($amount) }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="table-section mt-4">
    <h2>Bookings — Last 30 Days</h2>
    <canvas id="bookingsChart" height="90" data-labels='@json($chartLabels)' data-values='@json($chartData)'></canvas>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="table-section">
            <h2>Recent Bookings</h2>
            <table class="table">
                <thead><tr><th>Code</th><th>Room</th><th>Guest</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($recentBookings as $booking)
                    <tr>
                        <td><a href="{{ route('admin.bookings.show', $booking) }}">{{ $booking->confirmation_code }}</a></td>
                        <td>{{ $booking->roomNames() }}</td>
                        <td>{{ $booking->customer_name }}</td>
                        <td><span class="status {{ $booking->status }}">{{ $booking->status }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-muted">No bookings yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="table-section">
            <h2>Recent Messages</h2>
            <table class="table">
                <thead><tr><th>Name</th><th>Subject</th><th></th></tr></thead>
                <tbody>
                    @forelse($recentMessages as $message)
                    <tr>
                        <td>{{ $message->name }} @if(!$message->is_read)<span class="badge-count">new</span>@endif</td>
                        <td>{{ $message->subject ?: '—' }}</td>
                        <td><a href="{{ route('admin.messages.show', $message) }}">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-muted">No messages yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
