@extends('layouts.admin')

@section('title', 'Rooms')

@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.rooms.create') }}" class="btn-admin-primary"><i class="bi bi-plus-lg"></i> Add Room</a>
</div>

<div class="table-section">
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Room</th><th>Price / Night</th><th>Guests</th><th>Images</th><th>Bookings</th><th>Status</th><th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($rooms as $room)
            <tr>
                <td><strong>{{ $room->name }}</strong><br><span class="text-muted small">{{ $room->slug }}</span></td>
                <td>{{ npr($room->price_per_night) }}</td>
                <td>{{ $room->max_guests }}</td>
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
                <td class="text-end">
                    <a href="{{ route('admin.rooms.edit', $room) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                    <form action="{{ route('admin.rooms.destroy', $room) }}" method="POST" class="d-inline" data-confirm-delete="Delete this room? This cannot be undone.">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-muted">No rooms yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $rooms->links() }}
</div>
@endsection
