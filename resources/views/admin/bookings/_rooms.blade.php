{{-- Move a booking (or one room of a multi-room booking) to another room.
     The server re-checks availability, capacity and status — this list is
     only a convenience. --}}
@if(in_array($booking->status, [\App\Models\Booking::STATUS_PENDING, \App\Models\Booking::STATUS_CONFIRMED, \App\Models\Booking::STATUS_CHECKED_IN], true))
@php
    $occupied = $booking->roomIds();
    $targets = \App\Models\Room::bookable()->orderBy('name')->orderBy('room_number')->get()
        ->reject(fn ($room) => in_array($room->id, $occupied, true));
@endphp
<div class="table-section mt-4">
    <h2>Rooms</h2>
    <table class="table align-middle">
        <thead><tr><th>Room</th><th>Guests</th><th>Move to</th></tr></thead>
        <tbody>
            @foreach($booking->roomLines() as $index => $line)
            <tr>
                <td>{{ $line['room']?->label() ?? '—' }}@if($line['primary'] && $booking->isMultiRoom()) <span class="text-muted small">(main)</span>@endif</td>
                <td class="small">{{ $line['guests_label'] }}</td>
                <td>
                    <form action="{{ route('admin.bookings.reassign-room', $booking) }}" method="POST" class="d-flex gap-1">
                        @csrf @method('PATCH')
                        @unless($line['primary'])
                            <input type="hidden" name="booking_room_id" value="{{ $booking->additionalRooms[$index - 1]->id }}">
                        @endunless
                        <select name="room_id" class="form-select form-select-sm" required>
                            <option value="">Choose a room…</option>
                            @foreach($targets as $target)
                                <option value="{{ $target->id }}">{{ $target->label() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-dark text-nowrap">Move</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
