@extends('layouts.admin')

@section('title', 'Guest: ' . ($name ?: $email))

@section('content')
<div class="row g-4">
    <div class="col-lg-7">
        <div class="table-section">
            <h2>
                {{ $name ?: $email }}
                @if($profile->is_vip)<span class="status vip ms-2"><i class="bi bi-star-fill"></i> VIP</span>@endif
            </h2>
            <table class="table">
                <tr><td class="text-muted">Email</td><td>{{ $email }}</td></tr>
                <tr><td class="text-muted">Account</td><td>{{ $user ? 'Registered customer' : 'No account (guest checkout)' }}</td></tr>
                @if($user?->phone)
                <tr><td class="text-muted">Phone</td><td>{{ $user->phone }}</td></tr>
                @endif
                <tr><td class="text-muted">Bookings</td><td>{{ $bookings->count() }}</td></tr>
            </table>

            <h2 class="mt-4">Stay History</h2>
            <table class="table align-middle">
                <thead><tr><th>Code</th><th>Room</th><th>Dates</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($bookings as $booking)
                    <tr>
                        <td><a href="{{ route('admin.bookings.show', $booking) }}">{{ $booking->confirmation_code }}</a></td>
                        <td>{{ $booking->roomsSummary() }}</td>
                        <td class="small">{{ $booking->check_in->format('d M Y') }} → {{ $booking->check_out->format('d M Y') }}</td>
                        <td>{{ npr($booking->total_price) }}</td>
                        <td><span class="status {{ $booking->status }}">{{ str_replace('_', ' ', $booking->status) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted">No bookings yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="table-section">
            <h2>Staff Notes</h2>
            <p class="text-muted small">Visible to hotel staff only — never shown to the guest.</p>
            <form action="{{ route('admin.guests.update') }}" method="POST">
                @csrf @method('PUT')
                <input type="hidden" name="email" value="{{ $email }}">
                <div class="form-check mb-3">
                    <input type="hidden" name="is_vip" value="0">
                    <input class="form-check-input" type="checkbox" name="is_vip" value="1" id="is_vip" @checked(old('is_vip', $profile->is_vip))>
                    <label class="form-check-label" for="is_vip">VIP guest</label>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea name="notes" id="notes" rows="8" maxlength="5000" class="form-control" placeholder="e.g. prefers a quiet room, previous feedback…">{{ old('notes', $profile->notes) }}</textarea>
                </div>
                <button type="submit" class="btn-admin-primary">Save</button>
                @if($profile->exists && $profile->updatedBy)
                    <p class="text-muted small mt-2 mb-0">Last updated by {{ $profile->updatedBy->name }}, {{ $profile->updated_at->diffForHumans() }}.</p>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
