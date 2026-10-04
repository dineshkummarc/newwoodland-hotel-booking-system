<div class="form-grid">
    <div>
        <label class="form-label">Bootstrap Icon Class</label>
        <input type="text" name="icon" class="form-control" value="{{ old('icon', $facility->icon) }}" placeholder="bi-water">
        <div class="form-text">See <a href="https://icons.getbootstrap.com" target="_blank" rel="noopener">icons.getbootstrap.com</a> for class names.</div>
    </div>
    <div>
        <label class="form-label">Title *</label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $facility->title) }}" required>
    </div>
    <div>
        <label class="form-label">Category</label>
        <select name="category" class="form-select">
            <option value="">— Uncategorized —</option>
            @foreach(\App\Models\Facility::CATEGORIES as $value => $label)
                <option value="{{ $value }}" {{ old('category', $facility->category) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <div class="form-text">Groups this facility on the public Facilities page.</div>
    </div>
    <div class="full-width">
        <label class="form-label">Highlights (one per line)</label>
        <textarea name="description" class="form-control" rows="4" placeholder="Separate pools for adults and children&#10;Landscaped garden">{{ old('description', $facility->description) }}</textarea>
        <small class="text-muted">Each line is shown to guests as a bullet point. Keep each line short.</small>
    </div>
    <div class="form-check">
        <input type="checkbox" name="show_on_homepage" class="form-check-input" id="show_on_homepage" value="1" {{ old('show_on_homepage', $facility->exists ? $facility->show_on_homepage : true) ? 'checked' : '' }}>
        <label class="form-check-label" for="show_on_homepage">Show on homepage</label>
    </div>
</div>
