<div class="form-grid">
    <div>
        <label class="form-label">Room Name *</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $room->name) }}" required>
    </div>
    <div>
        <label class="form-label">Slug (URL)</label>
        <input type="text" name="slug" class="form-control" value="{{ old('slug', $room->slug) }}" placeholder="auto-generated from name">
    </div>
    <div>
        <label class="form-label">Price per Night (NPR) *</label>
        <input type="number" step="0.01" min="0" name="price_per_night" class="form-control" value="{{ old('price_per_night', $room->price_per_night) }}" required>
    </div>
    <div>
        <label class="form-label">Max Guests *</label>
        <input type="number" min="1" name="max_guests" class="form-control" value="{{ old('max_guests', $room->max_guests ?? 2) }}" required>
    </div>
    <div>
        <label class="form-label">Bed Type</label>
        <input type="text" name="bed_type" class="form-control" value="{{ old('bed_type', $room->bed_type) }}">
    </div>
    <div>
        <label class="form-label">Room Size (m²)</label>
        <input type="number" min="0" name="size_sqm" class="form-control" value="{{ old('size_sqm', $room->size_sqm) }}">
    </div>
    <div>
        <label class="form-label">Badge Label</label>
        <input type="text" name="badge_label" class="form-control" value="{{ old('badge_label', $room->badge_label) }}" placeholder="e.g. Best Seller">
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
        <textarea name="amenities_text" class="form-control" rows="4" id="amenitiesText" placeholder="Free Wi-Fi&#10;Breakfast Included">{{ old('amenities_text', $room->exists ? $room->amenities->pluck('label')->implode("\n") : '') }}</textarea>
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
