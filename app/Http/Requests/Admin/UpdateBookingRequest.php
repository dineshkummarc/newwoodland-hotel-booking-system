<?php

namespace App\Http\Requests\Admin;

use App\Models\Booking;
use App\Models\Room;
use App\Rules\PhoneNumber;
use App\Services\PhoneNumberService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests_label' => ['required', 'string', 'max:50', $this->capacityRule()],
            'customer_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone_country' => ['nullable', 'string', Rule::in(app(PhoneNumberService::class)->supportedRegions())],
            'phone' => ['required', 'string', 'max:30', new PhoneNumber($this->input('phone_country'))],
            'country' => ['nullable', 'string', 'max:100'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'check_out.after' => 'Check-out date must be after the check-in date.',
            'phone_country.in' => 'Please choose a valid country code.',
        ];
    }

    /**
     * Validated booking fields with the phone in canonical E.164 form.
     *
     * @return array<string, mixed>
     */
    public function bookingData(): array
    {
        $data = $this->safe()->except('phone_country');
        $data['phone'] = app(PhoneNumberService::class)->toE164($this->validated('phone'), $this->validated('phone_country'));

        return $data;
    }

    /**
     * Validated against the booking's CURRENT room — this form never
     * changes the room itself (see the separate reassign-room action), so
     * capacity is checked against whatever room the booking already has.
     */
    protected function capacityRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            /** @var Booking|null $booking */
            $booking = $this->route('booking');
            $room = $booking?->room;

            if ($room instanceof Room && $room->exceedsCapacityFor((string) $value)) {
                $fail("This room accommodates a maximum of {$room->max_guests} guest(s). Please choose a smaller party size or reassign the room first.");
            } elseif ($room instanceof Room && ($error = $room->partySizeErrorFor((string) $value))) {
                $fail($error);
            }
        };
    }
}
