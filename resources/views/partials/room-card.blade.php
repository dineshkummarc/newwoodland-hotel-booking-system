{{-- Public room-type card (home, rooms). Params: $room; optional $cardClass. --}}
<article class="room-card {{ $cardClass ?? 'p-4 bg-white' }} rounded-4 border shadow-sm h-100 d-flex flex-column justify-content-between">
    <div>
        <div class="position-relative overflow-hidden rounded-3 mb-3">
            @if($room->badge_label)<span class="room-tag">{{ $room->badge_label }}</span>@endif
            @if($image = $room->primaryImage())
                <img src="{{ asset('storage/'.$image->path) }}" alt="{{ $room->name }} at Newwoodland Hotel &amp; Lounge" class="img-fluid w-100" style="height: 240px; object-fit: cover;" loading="lazy" decoding="async">
            @else
                {{-- CLIENT INPUT REQUIRED: room photo (Admin → Rooms → Edit) --}}
                <div class="photo-frame photo-frame--card">
                    @include('partials.photo-placeholder', ['label' => $room->name, 'icon' => 'bi-door-open'])
                </div>
            @endif
        </div>
        <h3 class="font-heading text-emerald h4">{{ $room->name }}</h3>
        @if($room->short_description || $room->description)
            <p class="text-muted small mb-3">{{ $room->short_description ?? Str::limit($room->description, 120) }}</p>
        @endif
        <ul class="list-unstyled text-muted small mb-3 lh-lg">
            @if(($room->type_count ?? 1) > 1)<li><i class="bi bi-door-open me-1"></i> {{ $room->type_count }} rooms of this type</li>@endif
            @if($room->max_guests)<li><i class="bi bi-people-fill me-1"></i> {{ $room->min_guests ? "For {$room->min_guests}–{$room->max_guests} guests" : 'Up to '.$room->max_guests.' '.Str::plural('guest', $room->max_guests) }}</li>@endif
            @if($room->bed_type)<li><i class="bi bi-lamp-fill me-1"></i> {{ $room->bed_type }}</li>@endif
            @if($room->size_sqm)<li><i class="bi bi-aspect-ratio me-1"></i> {{ $room->size_sqm }} m²</li>@endif
        </ul>
        <div class="mb-4">
            @include('partials.room-amenities', ['amenities' => $room->amenities])
        </div>
    </div>
    <div class="border-top pt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            @if($room->hasPrice())
                <span class="fs-4 font-heading fw-bold text-emerald">{{ npr($room->price_per_night) }}</span>
                <small class="text-muted">{{ $room->priceUnit() }}</small>
            @else
                <span class="room-price-on-request">Rate on request</span>
            @endif
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('rooms.show', $room->slug) }}" class="btn btn-outline-dark py-2 fs-6">Details</a>
            @if($room->isBookable())
                <a href="{{ route('booking', ['room' => $room->id]) }}" class="btn-gold py-2 fs-6">Book</a>
            @else
                <a href="{{ route('contact') }}" class="btn-gold py-2 fs-6">Enquire</a>
            @endif
        </div>
    </div>
</article>
