@extends('layouts.admin')

@section('title', 'Bookings')

@section('content')
<div class="table-section mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                @foreach(['pending', 'confirmed', 'cancelled'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="{{ request('from') }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="{{ request('to') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Search (code, name, email)</label>
            <input type="text" name="search" class="form-control" value="{{ request('search') }}">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn-admin-primary">Filter</button>
            <a href="{{ route('admin.bookings.export', request()->query()) }}" class="btn btn-outline-dark" title="Export CSV"><i class="bi bi-download"></i></a>
        </div>
    </form>
</div>

<div class="table-section">
    <table class="table align-middle">
        <thead><tr><th>Code</th><th>Room</th><th>Guest</th><th>Dates</th><th>Total</th><th>Status</th><th>Payment</th><th></th></tr></thead>
        <tbody>
            @forelse($bookings as $booking)
            @php $payment = $booking->payments->sortByDesc('created_at')->first(); @endphp
            <tr>
                <td>{{ $booking->confirmation_code }}</td>
                <td>{{ $booking->room->name ?? '—' }}</td>
                <td>{{ $booking->customer_name }}<br><span class="text-muted small">{{ $booking->email }}</span></td>
                <td>{{ $booking->check_in->format('d M') }} — {{ $booking->check_out->format('d M Y') }}</td>
                <td>{{ npr($booking->total_price) }}</td>
                <td><span class="status {{ $booking->status }}">{{ $booking->status }}</span></td>
                <td>
                    @if($payment)
                        <span class="status {{ $payment->status === 'completed' ? 'confirmed' : $payment->status }}">{{ ucfirst($payment->status) }}</span>
                    @else
                        <span class="text-muted small">—</span>
                    @endif
                </td>
                <td class="text-end"><a href="{{ route('admin.bookings.show', $booking) }}" class="btn btn-sm btn-outline-dark">View</a></td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-muted">No bookings found.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $bookings->links() }}
</div>
@endsection
