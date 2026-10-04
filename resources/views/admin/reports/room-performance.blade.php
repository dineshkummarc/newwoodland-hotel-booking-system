@extends('layouts.admin')

@section('title', 'Room Performance')

@section('content')
<div class="table-section mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="{{ $from->toDateString() }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="{{ $to->toDateString() }}">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn-admin-primary">Filter</button>
        </div>
    </form>
</div>

<div class="table-section">
    <table class="table align-middle">
        <thead><tr><th>Room</th><th>Bookings</th><th>Room-Nights</th><th>Revenue</th></tr></thead>
        <tbody>
            @forelse($rooms as $room)
            <tr>
                <td>{{ $room->name }}</td>
                <td>{{ $room->bookings_count }}</td>
                <td>{{ $room->room_nights ?? 0 }}</td>
                <td>{{ npr($room->revenue ?? 0) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-muted">No rooms found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
