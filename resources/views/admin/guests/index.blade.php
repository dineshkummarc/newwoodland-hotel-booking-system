@extends('layouts.admin')

@section('title', 'Guests')

@section('content')
<div class="table-section mb-4">
    <h2>Registered Customers</h2>
    <form method="GET" class="row g-3 align-items-end mb-3">
        <div class="col-md-4">
            <label class="form-label">Search (name, email)</label>
            <input type="text" name="registered_search" class="form-control" value="{{ request('registered_search') }}">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn-admin-primary">Search</button>
        </div>
    </form>
    <table class="table align-middle">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Bookings</th><th>Latest Booking</th><th>Total Amount</th><th></th></tr></thead>
        <tbody>
            @forelse($registeredCustomers as $user)
            <tr>
                <td>{{ $user->name }} @if($profiles->get(strtolower($user->email))?->is_vip)<span class="status vip">VIP</span>@endif</td>
                <td>{{ $user->email }}</td>
                <td>{{ $user->phone ?: '—' }}</td>
                <td>{{ $user->bookings_count }}</td>
                <td>{{ $user->bookings->first()?->created_at?->format('d M Y') ?? '—' }}</td>
                <td>{{ npr($user->total_amount ?? 0) }}</td>
                <td class="text-end"><a href="{{ route('admin.guests.show', ['email' => $user->email]) }}" class="btn btn-sm btn-outline-dark">View</a></td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-muted">No registered customers yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $registeredCustomers->links() }}
</div>

<div class="table-section">
    <h2>Guest Bookings (No Account)</h2>
    <form method="GET" class="row g-3 align-items-end mb-3">
        <div class="col-md-4">
            <label class="form-label">Search (name, email)</label>
            <input type="text" name="guest_search" class="form-control" value="{{ request('guest_search') }}">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn-admin-primary">Search</button>
        </div>
    </form>
    <table class="table align-middle">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Bookings</th><th>Latest Booking</th><th>Total Amount</th><th></th></tr></thead>
        <tbody>
            @forelse($guestBookings as $guest)
            <tr>
                <td>{{ $guest->customer_name }} @if($profiles->get(strtolower($guest->email))?->is_vip)<span class="status vip">VIP</span>@endif</td>
                <td>{{ $guest->email }}</td>
                <td>{{ $guest->phone ?: '—' }}</td>
                <td>{{ $guest->booking_count }}</td>
                <td>{{ \Illuminate\Support\Carbon::parse($guest->latest_booking_at)->format('d M Y') }}</td>
                <td>{{ npr($guest->total_amount ?? 0) }}</td>
                <td class="text-end"><a href="{{ route('admin.guests.show', ['email' => $guest->email]) }}" class="btn btn-sm btn-outline-dark">View</a></td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-muted">No guest (no-account) bookings yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $guestBookings->links() }}
</div>
@endsection
