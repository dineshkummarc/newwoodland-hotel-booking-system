<?php

namespace App\Http\Requests;

use App\Models\Room;
use App\Rules\PhoneNumber;
use App\Services\PhoneNumberService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id' => [
                'required',
                Rule::exists('rooms', 'id')->where('is_active', true)->where('availability_status', Room::AVAILABILITY_AVAILABLE)->whereNotNull('price_per_night'),
            ],
            'check_in' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays((int) config('bookings.max_advance_days', 730))->toDateString()],
            'check_out' => ['required', 'date', 'after:check_in', $this->maxNightsRule()],
            'guests_label' => ['required', 'string', 'max:50', $this->capacityRule()],
            'customer_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            // Any real phone number, any country: the country picker is only
            // used when the number is typed without "+<country code>"
            // (default Nepal). Stored in E.164 (+9779812345678) — see e164Phone().
            'phone_country' => ['nullable', 'string', Rule::in(app(PhoneNumberService::class)->supportedRegions())],
            'phone' => ['required', 'string', 'max:30', new PhoneNumber($this->input('phone_country'))],
            'country' => ['nullable', 'string', 'max:100'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            // Public form: book this many rooms of the chosen type (guests_label is per room).
            'rooms_count' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ROOMS],
            // Staff form: specific extra rooms, each with its own party.
            'additional_rooms' => ['nullable', 'array', 'max:'.(self::MAX_ROOMS - 1)],
            'additional_rooms.*.room_id' => [
                'required', 'integer', 'distinct', 'different:room_id',
                Rule::exists('rooms', 'id')->where('is_active', true)->where('availability_status', Room::AVAILABILITY_AVAILABLE)->whereNotNull('price_per_night'),
            ],
            'additional_rooms.*.guests_label' => ['required', 'string', 'max:50'],
        ];
    }

    /** Upper bound on rooms in a single booking. */
    public const MAX_ROOMS = 5;

    /**
     * Additional rooms for BookingService::create(), skipping blank rows.
     *
     * @return list<array{room: Room, guests_label: string}>
     */
    public function additionalRooms(): array
    {
        return collect($this->validated('additional_rooms') ?? [])
            ->filter(fn ($row) => ! empty($row['room_id']))
            ->map(fn ($row) => ['room' => Room::findOrFail($row['room_id']), 'guests_label' => $row['guests_label']])
            ->values()
            ->all();
    }

    /**
     * Blank "add another room" rows on the staff form are dropped before
     * validation, so an unused row never blocks the booking.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('additional_rooms'))) {
            $this->merge([
                'additional_rooms' => array_values(array_filter($this->input('additional_rooms'), fn ($row) => is_array($row) && filled($row['room_id'] ?? null))),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'room_id.exists' => 'The selected room is unavailable.',
            'check_in.after_or_equal' => 'Check-in date cannot be in the past.',
            'check_out.after' => 'Check-out date must be after the check-in date.',
            'check_in.before_or_equal' => 'Bookings can only be made up to '.(int) config('bookings.max_advance_days', 730).' days in advance. Please contact the hotel for later dates.',
            'phone_country.in' => 'Please choose a valid country code.',
            'additional_rooms.*.room_id.exists' => 'One of the additional rooms is unavailable.',
            'additional_rooms.*.room_id.distinct' => 'Each room can only be added once.',
            'additional_rooms.*.room_id.different' => 'The additional rooms must be different from the main room.',
            'additional_rooms.*.guests_label.required' => 'Choose the guests for each additional room.',
        ];
    }

    /**
     * The guest's phone in canonical E.164 form (e.g. +9779812345678,
     * +919812345678), so staff can always dial it and repeat guests match.
     */
    public function e164Phone(): string
    {
        return app(PhoneNumberService::class)->toE164($this->validated('phone'), $this->validated('phone_country'));
    }

    /**
     * Technical ceiling on the length of one online booking (see
     * config/bookings.php — the real limit is a client decision).
     */
    protected function maxNightsRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $max = (int) config('bookings.max_nights', 90);

            try {
                $nights = Carbon::parse((string) $this->input('check_in'))->diffInDays(Carbon::parse((string) $value), false);
            } catch (\Throwable) {
                return;
            }

            if ($max > 0 && $nights > $max) {
                $fail("Stays longer than {$max} nights can't be booked online. Please contact the hotel.");
            }
        };
    }

    public function room(): Room
    {
        return Room::findOrFail($this->validated('room_id'));
    }

    /**
     * Server-side guard: the selected guest count must not exceed the
     * selected room's max_guests. Never relies on Blade/JS alone — the
     * room is re-fetched here and checked against the raw submitted
     * room_id regardless of whether it has passed its own validation yet.
     */
    protected function capacityRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $room = Room::find($this->input('room_id'));

            if ($room && ($error = $room->partySizeErrorFor((string) $value))) {
                $fail($error);
            }
        };
    }
}
