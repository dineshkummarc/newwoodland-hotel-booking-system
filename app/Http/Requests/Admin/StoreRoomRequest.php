<?php

namespace App\Http\Requests\Admin;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            // Several rooms can share a type name (e.g. two VIP single
            // rooms), so a generated slug gets a numeric suffix when taken.
            $base = Str::slug(trim($this->input('name').' '.$this->input('room_number')));
            $slug = $base;

            for ($i = 2; Room::where('slug', $slug)->whereKeyNot($this->route('room')?->id)->exists(); $i++) {
                $slug = "{$base}-{$i}";
            }

            $this->merge(['slug' => $slug]);
        }
    }

    public function rules(): array
    {
        $roomId = $this->route('room')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'room_number' => ['nullable', 'string', 'max:50'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('rooms', 'slug')->ignore($roomId)],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Optional until the hotel confirms the rate: an unpriced room is
            // listed publicly but cannot be booked (Room::scopeBookable).
            'price_per_night' => ['nullable', 'numeric', 'min:1'],
            'price_per_person' => ['nullable', 'boolean'],
            'min_guests' => ['nullable', 'integer', 'min:1', 'max:20'],
            'max_guests' => ['nullable', 'integer', 'min:1', 'max:20', ...($this->filled('min_guests') ? ['gte:min_guests'] : [])],
            'bed_type' => ['nullable', 'string', 'max:100'],
            'size_sqm' => ['nullable', 'integer', 'min:0'],
            'badge_label' => ['nullable', 'string', 'max:50'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'availability_status' => ['nullable', Rule::in(array_keys(Room::AVAILABILITY_STATUSES))],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['nullable', 'string', 'max:100'],
        ];
    }
}
