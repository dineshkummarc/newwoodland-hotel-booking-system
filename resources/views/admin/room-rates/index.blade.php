@extends('layouts.admin')

@section('title', 'Pricing Rules')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted small mb-0">
        For each night: <strong>holiday</strong> beats <strong>season</strong> beats <strong>weekend</strong> beats the room's base price.
        Rules only affect new bookings — existing bookings keep the prices they were made at.
    </p>
    <a href="{{ route('admin.room-rates.create') }}" class="btn-admin-primary text-nowrap"><i class="bi bi-plus-lg"></i> Add Rule</a>
</div>

<div class="table-section">
    <table class="table align-middle">
        <thead><tr><th>Rule</th><th>Type</th><th>Rooms</th><th>When</th><th>Price</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse($rates as $rate)
            <tr class="{{ $rate->is_active ? '' : 'text-muted' }}">
                <td><strong>{{ $rate->name }}</strong></td>
                <td>{{ $rate->typeLabel() }}</td>
                <td>{{ $rate->room_type ?? 'All rooms' }}</td>
                <td class="small">
                    @if($rate->start_date){{ $rate->start_date->format('d M Y') }} – {{ $rate->end_date->format('d M Y') }}@else Year-round @endif
                    @if($rate->type === \App\Models\RoomRate::TYPE_WEEKEND)<br>{{ $rate->daysLabel() }} nights @endif
                </td>
                <td>{{ $rate->adjustmentLabel() }}</td>
                <td><span class="status {{ $rate->is_active ? 'confirmed' : 'cancelled' }}">{{ $rate->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('admin.room-rates.edit', $rate) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                    <form action="{{ route('admin.room-rates.destroy', $rate) }}" method="POST" class="d-inline" data-confirm-delete="Delete this pricing rule? Existing bookings keep their prices.">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-muted">No pricing rules yet — every night is charged at the room's base price.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
