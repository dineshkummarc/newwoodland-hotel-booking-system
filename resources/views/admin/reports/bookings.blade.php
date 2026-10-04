@extends('layouts.admin')

@section('title', 'Booking Report')

@section('content')
<div class="table-section mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-2">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="{{ $from->toDateString() }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="{{ $to->toDateString() }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                @foreach(['pending', 'confirmed', 'checked_in', 'checked_out', 'completed', 'cancelled', 'no_show'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Room</label>
            <select name="room_id" class="form-select">
                <option value="">All</option>
                @foreach($rooms as $room)
                    <option value="{{ $room->id }}" {{ request('room_id') == $room->id ? 'selected' : '' }}>{{ $room->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Guest (name/email)</label>
            <input type="text" name="guest" class="form-control" value="{{ request('guest') }}">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn-admin-primary">Filter</button>
            <a href="{{ route('admin.reports.bookings.export', request()->query()) }}" class="btn btn-outline-dark" title="Export CSV"><i class="bi bi-download"></i></a>
        </div>
    </form>
</div>

<div class="row g-3 mb-4">
    @foreach($statusCounts as $status => $count)
    <div class="col-6 col-md-3 col-lg-2">
        <div class="table-section text-center py-3">
            <div class="fs-4 fw-bold">{{ $count }}</div>
            <div class="text-muted small">{{ ucfirst(str_replace('_', ' ', $status)) }}</div>
        </div>
    </div>
    @endforeach
</div>

<div class="table-section">
    <table class="table align-middle">
        <thead><tr><th>Code</th><th>Room</th><th>Guest</th><th>Dates</th><th>Nights</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
            @forelse($bookings as $booking)
            <tr>
                <td>{{ $booking->confirmation_code }}</td>
                <td>{{ $booking->roomNames() }}</td>
                <td>{{ $booking->customer_name }}</td>
                <td>{{ $booking->check_in->format('d M') }} — {{ $booking->check_out->format('d M Y') }}</td>
                <td>{{ $booking->nights }}</td>
                <td>{{ npr($booking->total_price) }}</td>
                <td><span class="status {{ $booking->status }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span></td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-muted">No bookings in this range.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $bookings->links() }}
</div>
@endsection
