{{-- A room's amenities as compact chips. Params: $amenities (RoomAmenity collection). --}}
@if($amenities->isNotEmpty())
<ul class="room-amenity-list" aria-label="Room amenities">
    @foreach($amenities as $amenity)
        <li><i class="bi {{ $amenity->iconClass() }}" aria-hidden="true"></i> {{ $amenity->label }}</li>
    @endforeach
</ul>
@endif
