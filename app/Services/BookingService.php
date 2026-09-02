<?php

namespace App\Services;

use App\Mail\BookingConfirmationMail;
use App\Mail\NewBookingNotificationMail;
use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    /**
     * Determine whether a room is free for the given date range.
     * Only pending/confirmed bookings block availability.
     */
    public function isAvailable(int $roomId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): bool
    {
        $query = Booking::query()->overlapping($roomId, $checkIn, $checkOut);

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return ! $query->exists();
    }

    /**
     * Create a booking with server-side price calculation and a
     * transaction-safe availability re-check to prevent race conditions.
     *
     * @throws ValidationException if the room becomes unavailable
     */
    public function create(Room $room, array $data): Booking
    {
        return DB::transaction(function () use ($room, $data) {
            // Lock any existing overlapping bookings for this room so a concurrent
            // request cannot slip a conflicting booking in between our check and insert.
            $hasOverlap = Booking::query()
                ->overlapping($room->id, $data['check_in'], $data['check_out'])
                ->lockForUpdate()
                ->exists();

            if ($hasOverlap) {
                throw ValidationException::withMessages([
                    'check_in' => 'This room is no longer available for the selected dates.',
                ]);
            }

            $checkIn = Carbon::parse($data['check_in']);
            $checkOut = Carbon::parse($data['check_out']);
            $nights = $checkIn->diffInDays($checkOut);
            $nightlyRate = $room->price_per_night;
            $total = round($nightlyRate * $nights, 2);

            $booking = Booking::create([
                'confirmation_code' => $this->generateConfirmationCode(),
                'user_id' => $data['user_id'] ?? null,
                'room_id' => $room->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'nights' => $nights,
                'guests_label' => $data['guests_label'],
                'customer_name' => $data['customer_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'country' => $data['country'] ?? null,
                'special_requests' => $data['special_requests'] ?? null,
                'nightly_rate' => $nightlyRate,
                'total_price' => $total,
                'status' => Booking::STATUS_PENDING,
            ]);

            return $booking;
        });
    }

    public function sendNotifications(Booking $booking, ?string $adminEmail): void
    {
        Mail::to($booking->email)->send(new BookingConfirmationMail($booking));

        if ($adminEmail) {
            Mail::to($adminEmail)->send(new NewBookingNotificationMail($booking));
        }
    }

    protected function generateConfirmationCode(): string
    {
        $year = now()->format('Y');

        do {
            $code = sprintf('NW-%s-%s', $year, Str::upper(Str::random(6)));
        } while (Booking::where('confirmation_code', $code)->exists());

        return $code;
    }
}
