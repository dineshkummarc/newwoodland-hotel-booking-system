<?php

namespace App\Http\Requests\Admin;

use App\Models\Room;
use App\Models\RoomRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'room_type' => $this->filled('room_type') ? $this->input('room_type') : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $isPercent = $this->input('adjustment') === RoomRate::ADJUSTMENT_PERCENT;

        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(RoomRate::TYPES))],
            'room_type' => ['nullable', 'string', Rule::in(Room::query()->distinct()->pluck('name')->all())],
            // Seasons and holidays need dates; a weekend rule may be year-round.
            'start_date' => ['nullable', 'date', Rule::requiredIf(fn () => $this->input('type') !== RoomRate::TYPE_WEEKEND)],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', Rule::requiredIf(fn () => $this->input('type') !== RoomRate::TYPE_WEEKEND)],
            'days_of_week' => ['nullable', 'array', Rule::requiredIf(fn () => $this->input('type') === RoomRate::TYPE_WEEKEND)],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'adjustment' => ['required', Rule::in([RoomRate::ADJUSTMENT_FIXED, RoomRate::ADJUSTMENT_PERCENT])],
            'value' => $isPercent
                ? ['required', 'numeric', 'between:-90,500']
                : ['required', 'numeric', 'min:1', 'max:1000000'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'Seasons and holidays need a start date.',
            'end_date.required' => 'Seasons and holidays need an end date.',
            'days_of_week.required' => 'Choose at least one day for a weekend rule.',
            'value.between' => 'A percentage adjustment must be between -90% and +500%.',
        ];
    }

    /** Validated data shaped for the model (days only kept for weekend rules). */
    public function rateData(): array
    {
        $data = $this->validated();
        $data['days_of_week'] = $data['type'] === RoomRate::TYPE_WEEKEND
            ? array_values(array_unique(array_map('intval', $data['days_of_week'] ?? [])))
            : null;

        return $data;
    }
}
