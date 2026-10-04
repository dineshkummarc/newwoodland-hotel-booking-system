@extends('layouts.admin')

@section('title', 'Rooms')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="text-muted small">
        @if($inventory['total'])
            <strong>{{ $inventory['active'] }} of {{ $inventory['total'] }}</strong> rooms set up and active.
            @if($inventory['active'] < $inventory['total'])
                {{ $inventory['total'] - $inventory['active'] }} still to be added once their details are confirmed.
            @elseif($inventory['active'] > $inventory['total'])
                <span class="text-danger">More active rooms than the hotel's total ({{ $inventory['total'] }}) — check for duplicates or update Settings → Total Rooms.</span>
            @endif
        @else
            {{ $inventory['active'] }} active rooms. Set the hotel's total room count in Settings to track inventory.
        @endif
        @if($inventory['unpriced'])
            <br>{{ $inventory['unpriced'] }} active {{ Str::plural('room', $inventory['unpriced']) }} without a nightly rate — shown to guests as "Rate on request" and not bookable online.
        @endif
    </div>
    <a href="{{ route('admin.rooms.create') }}" class="btn-admin-primary"><i class="bi bi-plus-lg"></i> Add Room</a>
</div>

<div class="table-section">
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Room</th><th>Room No.</th><th>Price / Night</th><th>Guests</th><th>Images</th><th>Bookings</th><th>Status</th><th>Availability</th><th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($rooms as $room)
            <tr>
                <td><strong>{{ $room->name }}</strong><br><span class="text-muted small">{{ $room->slug }}</span></td>
                <td>{{ $room->room_number ?: '—' }}</td>
                <td>{!! $room->hasPrice() ? e(npr($room->price_per_night).($room->price_per_person ? ' / person' : '')) : '<span class="text-muted">Not set</span>' !!}</td>
                <td>{{ $room->min_guests ? $room->min_guests.'–' : '' }}{{ $room->max_guests ?: '—' }}</td>
                <td>{{ $room->images_count }}</td>
                <td>{{ $room->bookings_count }}</td>
                <td>
                    <form action="{{ route('admin.rooms.toggle-active', $room) }}" method="POST">
                        @csrf @method('PATCH')
                        <button type="submit" class="status {{ $room->is_active ? 'confirmed' : 'cancelled' }}" style="border:none;">
                            {{ $room->is_active ? 'Active' : 'Inactive' }}
                        </button>
                    </form>
                </td>
                <td>
                    <span class="status {{ $room->availability_status === 'available' ? 'confirmed' : $room->availability_status }}">
                        {{ $room->availabilityStatusLabel() }}
                    </span>
                </td>
                <td class="text-end">
                    <a href="{{ route('admin.rooms.edit', $room) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                    <form action="{{ route('admin.rooms.destroy', $room) }}" method="POST" class="d-inline" data-confirm-delete="Delete this room? This cannot be undone.">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" class="text-muted">No rooms yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $rooms->links() }}
</div>
@endsection
