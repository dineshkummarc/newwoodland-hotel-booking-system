@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
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

<div class="table-section">
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
                        <td>{{ $booking->room->name ?? '—' }}</td>
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
