@php $selectedDays = array_map('intval', old('days_of_week', $rate->days_of_week ?? [])); @endphp
<div class="form-grid">
    <div>
        <label class="form-label">Name *</label>
        <input type="text" name="name" class="form-control" maxlength="100" value="{{ old('name', $rate->name) }}" placeholder="e.g. Dashain 2026, Weekend nights" required>
    </div>
    <div>
        <label class="form-label">Type *</label>
        <select name="type" class="form-select" required>
            @foreach(\App\Models\RoomRate::TYPES as $key => $label)
                <option value="{{ $key }}" @selected(old('type', $rate->type) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label">Rooms</label>
        <select name="room_type" class="form-select">
            <option value="">All rooms</option>
            @foreach($roomTypes as $type)
                <option value="{{ $type }}" @selected(old('room_type', $rate->room_type) === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label">Start date <span class="text-muted small">(required for seasons/holidays)</span></label>
        <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $rate->start_date?->toDateString()) }}">
    </div>
    <div>
        <label class="form-label">End date (inclusive)</label>
        <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $rate->end_date?->toDateString()) }}">
    </div>
    <div>
        <label class="form-label">Nights of the week <span class="text-muted small">(weekend rules only)</span></label>
        <div>
            @foreach(\App\Models\RoomRate::DAYS as $num => $label)
                <label class="me-2"><input type="checkbox" name="days_of_week[]" value="{{ $num }}" @checked(in_array($num, $selectedDays, true))> {{ $label }}</label>
            @endforeach
        </div>
        <small class="text-muted">The night that starts on that day (e.g. Fri = Friday night).</small>
    </div>
    <div>
        <label class="form-label">Price change *</label>
        <select name="adjustment" class="form-select" required>
            <option value="percent" @selected(old('adjustment', $rate->adjustment) === 'percent')>Percentage of the base price (+/−)</option>
            <option value="fixed" @selected(old('adjustment', $rate->adjustment) === 'fixed')>Fixed nightly price (NPR)</option>
        </select>
        <small class="text-muted">For rooms priced per person, a fixed price is per person per night.</small>
    </div>
    <div>
        <label class="form-label">Value *</label>
        <input type="number" name="value" step="0.01" class="form-control" value="{{ old('value', $rate->value) }}" placeholder="e.g. 20 for +20%, or 3500" required>
    </div>
    <div class="full-width">
        <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rate->is_active))> Active</label>
    </div>
</div>
