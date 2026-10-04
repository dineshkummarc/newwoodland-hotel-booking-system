@extends('layouts.admin')

@section('title', 'Occupancy Report')

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

<div class="row g-3">
    <div class="col-md-3">
        <div class="table-section text-center py-4">
            <div class="fs-3 fw-bold">{{ $totalRooms }}</div>
            <div class="text-muted">Active Rooms</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-section text-center py-4">
            <div class="fs-3 fw-bold">{{ $availableRoomNights }}</div>
            <div class="text-muted">Available Room-Nights ({{ $totalDays }} days)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-section text-center py-4">
            <div class="fs-3 fw-bold">{{ $occupiedRoomNights }}</div>
            <div class="text-muted">Occupied Room-Nights</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-section text-center py-4">
            <div class="fs-3 fw-bold text-emerald">{{ $occupancyPercentage }}%</div>
            <div class="text-muted">Occupancy Rate</div>
        </div>
    </div>
</div>
<p class="text-muted small mt-3">
    Occupied room-nights counts every night, within this date range, reserved by a confirmed, checked-in, checked-out, or completed booking. Pending, cancelled, and no-show bookings are excluded.
</p>
@endsection
