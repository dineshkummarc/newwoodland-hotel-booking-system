<div class="form-grid">
    <div>
        <label class="form-label">Room Type / Name *</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $room->name) }}" required>
        <small class="text-muted">Rooms with exactly the same name are shown to guests as one room type, and a booking gets whichever of them is free.</small>
    </div>
    <div>
        <label class="form-label">Room Number</label>
        <input type="text" name="room_number" class="form-control" maxlength="50" value="{{ old('room_number', $room->room_number) }}" placeholder="e.g. 101">
        <small class="text-muted">Staff-facing; tells rooms of the same type apart.</small>
    </div>
    <div>
        <label class="form-label">Slug (URL)</label>
        <input type="text" name="slug" class="form-control" value="{{ old('slug', $room->slug) }}" placeholder="auto-generated from name">
    </div>
    <div>
        <label class="form-label">Price per Night (NPR)</label>
        <input type="number" step="0.01" min="1" name="price_per_night" class="form-control" value="{{ old('price_per_night', $room->price_per_night) }}">
        <small class="text-muted">Leave blank until the rate is confirmed — the room is then shown as "Rate on request" and cannot be booked online.</small>
        <div class="form-check mt-2">
            <input type="checkbox" name="price_per_person" class="form-check-input" id="price_per_person" value="1" {{ old('price_per_person', $room->price_per_person) ? 'checked' : '' }}>
            <label class="form-check-label" for="price_per_person">Price is per person (charged × number of guests)</label>
        </div>
    </div>
    <div>
        <label class="form-label">Guests (min – max)</label>
        <div class="d-flex gap-2">
            <input type="number" min="1" max="20" name="min_guests" class="form-control" value="{{ old('min_guests', $room->min_guests) }}" placeholder="Min">
            <input type="number" min="1" max="20" name="max_guests" class="form-control" value="{{ old('max_guests', $room->max_guests) }}" placeholder="Max">
        </div>
        <small class="text-muted">Leave blank if not confirmed; bookings are then not limited by party size. Min is for group-only rooms.</small>
    </div>
    <div>
        <label class="form-label">Bed Type</label>
        <input type="text" name="bed_type" class="form-control" value="{{ old('bed_type', $room->bed_type) }}" placeholder="e.g. King Bed, Single Bed">
    </div>
    <div>
        <label class="form-label">Room Size (m²)</label>
        <input type="number" min="0" name="size_sqm" class="form-control" value="{{ old('size_sqm', $room->size_sqm) }}">
    </div>
    <div>
        <label class="form-label">Badge Label</label>
        <input type="text" name="badge_label" class="form-control" value="{{ old('badge_label', $room->badge_label) }}" placeholder="e.g. Best Seller">
    </div>
    <div>
        <label class="form-label">Availability Status *</label>
        <select name="availability_status" class="form-select" required>
            @foreach(\App\Models\Room::AVAILABILITY_STATUSES as $value => $label)
                <option value="{{ $value }}" {{ old('availability_status', $room->availability_status ?? 'available') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <small class="text-muted">A room under maintenance/out of service cannot receive new bookings, even if Active.</small>
    </div>
    <div class="d-flex align-items-end gap-4">
        <div class="form-check">
            <input type="checkbox" name="is_featured" class="form-check-input" id="is_featured" value="1" {{ old('is_featured', $room->is_featured) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_featured">Featured on Homepage</label>
        </div>
        <div class="form-check">
            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" {{ old('is_active', $room->exists ? $room->is_active : true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active (visible to guests)</label>
        </div>
    </div>
    <div class="full-width">
        <label class="form-label">Short Description</label>
        <input type="text" name="short_description" class="form-control" value="{{ old('short_description', $room->short_description) }}" maxlength="500">
    </div>
    <div class="full-width">
        <label class="form-label">Full Description</label>
        <textarea name="description" class="form-control" rows="4">{{ old('description', $room->description) }}</textarea>
    </div>
    <div class="full-width">
        <label class="form-label">Amenities (one per line)</label>
        <textarea name="amenities_text" class="form-control" rows="4" id="amenitiesText" placeholder="AC&#10;Washroom&#10;Cupboard">{{ old('amenities_text', $room->exists ? $room->amenities->pluck('label')->implode("\n") : '') }}</textarea>
        <div id="amenitiesHidden"></div>
    </div>
</div>

<script>
    document.getElementById('roomForm').addEventListener('submit', function () {
        var lines = document.getElementById('amenitiesText').value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
        var container = document.getElementById('amenitiesHidden');
        container.innerHTML = '';
        lines.forEach(function (label) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'amenities[]';
            input.value = label;
            container.appendChild(input);
        });
    });
</script>
