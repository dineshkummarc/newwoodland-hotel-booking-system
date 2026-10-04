@extends('layouts.admin')

@section('title', 'Reports')

@section('content')
<div class="row g-4">
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('admin.reports.bookings') }}" class="table-section d-block text-decoration-none text-dark h-100">
            <h2><i class="bi bi-calendar-check-fill text-emerald"></i> Booking Report</h2>
            <p class="text-muted mb-0">Bookings by date range, status, room, and guest.</p>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('admin.reports.revenue') }}" class="table-section d-block text-decoration-none text-dark h-100">
            <h2><i class="bi bi-cash-stack text-emerald"></i> Revenue Report</h2>
            <p class="text-muted mb-0">Confirmed/paid revenue by date range and payment provider.</p>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('admin.reports.occupancy') }}" class="table-section d-block text-decoration-none text-dark h-100">
            <h2><i class="bi bi-house-gear-fill text-emerald"></i> Occupancy Report</h2>
            <p class="text-muted mb-0">Occupied vs. available room-nights and occupancy %.</p>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('admin.reports.room-performance') }}" class="table-section d-block text-decoration-none text-dark h-100">
            <h2><i class="bi bi-door-open-fill text-emerald"></i> Room Performance</h2>
            <p class="text-muted mb-0">Bookings, room-nights, and revenue per room.</p>
        </a>
    </div>
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('admin.reports.payments') }}" class="table-section d-block text-decoration-none text-dark h-100">
            <h2><i class="bi bi-credit-card-fill text-emerald"></i> Payment Report</h2>
            <p class="text-muted mb-0">Every payment record across eSewa, Khalti, and any other provider.</p>
        </a>
    </div>
</div>
@endsection
