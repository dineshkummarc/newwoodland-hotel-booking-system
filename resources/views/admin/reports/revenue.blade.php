@extends('layouts.admin')

@section('title', 'Revenue Report')

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

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="table-section text-center py-4">
            <div class="fs-3 fw-bold text-emerald">{{ npr($totalRevenue) }}</div>
            <div class="text-muted">Confirmed/Paid Revenue</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="table-section text-center py-4">
            <div class="fs-3 fw-bold">{{ $payments->count() }}</div>
            <div class="text-muted">Completed Payments</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="table-section text-center py-4">
            <div class="fs-3 fw-bold text-danger">{{ $cancelledCount }}</div>
            <div class="text-muted">Cancelled Bookings (excluded from revenue)</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="table-section text-center py-4">
            <div class="fs-4 fw-bold text-danger">− {{ npr($refundsTotal) }}</div>
            <div class="text-muted">Refunded to Guests (completed refunds)</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="table-section text-center py-4">
            <div class="fs-4 fw-bold text-emerald">{{ npr($netRevenue) }}</div>
            <div class="text-muted">Net Revenue (payments − refunds)</div>
        </div>
    </div>
</div>

<div class="table-section mb-4">
    <h2>Revenue by Payment Method</h2>
    <table class="table align-middle">
        <thead><tr><th>Provider</th><th>Payments</th><th>Total</th></tr></thead>
        <tbody>
            @forelse($byProvider as $provider => $data)
            <tr>
                <td>{{ payment_provider_label($provider) }}</td>
                <td>{{ $data['count'] }}</td>
                <td>{{ npr($data['total']) }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="text-muted">No completed payments in this range.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="table-section mb-4">
    <h2>Extra Charges Billed</h2>
    <p class="text-muted small">Restaurant, laundry, minibar and other charges added to stays in this period (voided charges excluded). Amounts collected are included in the payments above.</p>
    <table class="table align-middle">
        <thead><tr><th>Category</th><th>Charges</th><th>Total Billed</th></tr></thead>
        <tbody>
            @forelse($chargesByCategory as $row)
            <tr>
                <td>{{ \App\Models\BookingCharge::CATEGORIES[$row->category] ?? ucfirst($row->category) }}</td>
                <td>{{ $row->count }}</td>
                <td>{{ npr($row->total) }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="text-muted">No extra charges in this range.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="table-section">
    <h2>Payment Detail</h2>
    <table class="table align-middle">
        <thead><tr><th>Booking</th><th>Provider</th><th>Amount</th><th>Reference</th><th>Paid At</th></tr></thead>
        <tbody>
            @forelse($payments as $payment)
            <tr>
                <td>{{ $payment->booking->confirmation_code ?? '—' }}</td>
                <td>{{ payment_provider_label($payment->provider) }}</td>
                <td>{{ npr($payment->amount) }}</td>
                <td>{{ $payment->reference_id ?: '—' }}</td>
                <td>{{ $payment->paid_at?->format('d M Y, g:i A') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-muted">No completed payments in this range.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
