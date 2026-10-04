@extends('layouts.admin')

@section('title', 'Payment Report')

@section('content')
<div class="table-section mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Provider</label>
            <select name="provider" class="form-select">
                <option value="">All</option>
                @foreach(config('payments.methods') as $key => $method)
                    <option value="{{ $key }}" {{ request('provider') === $key ? 'selected' : '' }}>{{ $method['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                @foreach(['pending', 'completed', 'failed', 'cancelled'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn-admin-primary">Filter</button>
            <a href="{{ route('admin.reports.payments.export', request()->query()) }}" class="btn btn-outline-dark" title="Export CSV"><i class="bi bi-download"></i></a>
        </div>
    </form>
</div>

<div class="table-section">
    <table class="table align-middle">
        <thead><tr><th>Booking</th><th>Provider</th><th>Status</th><th>Amount</th><th>Reference</th><th>Created</th></tr></thead>
        <tbody>
            @forelse($payments as $payment)
            <tr>
                <td>{{ $payment->booking->confirmation_code ?? '—' }}</td>
                <td>{{ payment_provider_label($payment->provider) }}</td>
                <td><span class="status {{ $payment->status === 'completed' ? 'confirmed' : $payment->status }}">{{ ucfirst($payment->status) }}</span></td>
                <td>{{ npr($payment->amount) }}</td>
                <td>{{ $payment->reference_id ?: '—' }}</td>
                <td>{{ $payment->created_at->format('d M Y, g:i A') }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-muted">No payment records found.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $payments->links() }}
</div>
@endsection
