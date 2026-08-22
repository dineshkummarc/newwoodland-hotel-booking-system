<div class="form-grid">
    <div>
        <label class="form-label">Guest Name *</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $testimonial->name) }}" required>
    </div>
    <div>
        <label class="form-label">Location</label>
        <input type="text" name="location" class="form-control" value="{{ old('location', $testimonial->location) }}">
    </div>
    <div>
        <label class="form-label">Rating *</label>
        <select name="rating" class="form-select" required>
            @for($i = 5; $i >= 1; $i--)
                <option value="{{ $i }}" {{ old('rating', $testimonial->rating ?? 5) == $i ? 'selected' : '' }}>{{ $i }} Star{{ $i > 1 ? 's' : '' }}</option>
            @endfor
        </select>
    </div>
    <div>
        <label class="form-label">Avatar (optional)</label>
        <input type="file" name="avatar" accept="image/*" class="form-control">
    </div>
    <div class="full-width">
        <label class="form-label">Review Content *</label>
        <textarea name="content" class="form-control" rows="4" required>{{ old('content', $testimonial->content) }}</textarea>
    </div>
</div>
