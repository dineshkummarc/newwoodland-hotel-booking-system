<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Room;
use App\Services\BookingService;
use App\Services\RoomPricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::onePerType(Room::active()->with(['images', 'amenities'])->orderBy('name')->get());

        return view('rooms', compact('rooms'));
    }

    public function show(Room $room)
    {
        abort_unless($room->is_active, 404);

        $room->load(['images', 'amenities']);
        $room->type_count = Room::active()->where('name', $room->name)->count();

        $relatedRooms = Room::onePerType(
            Room::active()
                ->where('name', '!=', $room->name)
                ->with('images')
                ->orderBy('is_featured', 'desc')
                ->orderBy('name')
                ->get()
        )->take(3);

        return view('rooms-details', compact('room', 'relatedRooms'));
    }

    public function availability(Request $request, Room $room, BookingService $bookingService, RoomPricingService $pricing)
    {
        $data = $request->validate([
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests_label' => ['nullable', 'string', 'max:50'],
        ]);

        // Answer exactly what the booking form would: run the SAME rules as
        // StoreBookingRequest (bookable room, no past check-in, booking
        // horizon, maximum stay, party size) before looking at inventory,
        // so "available" here always means the booking can go through.
        if ($problem = $this->bookingRuleProblem($request, $room, $data)) {
            return response()->json(['available' => false, 'free_rooms' => 0, 'estimate' => null, 'reason' => $problem]);
        }

        // Server-side estimate (weekend/season/holiday rules included), so the
        // booking form never shows a price the booking won't actually use.
        // Capped at 60 nights to keep this public endpoint cheap.
        $estimate = null;
        $nights = Carbon::parse($data['check_in'])->diffInDays(Carbon::parse($data['check_out']));
        if (! empty($data['guests_label']) && $nights <= 60 && $room->hasPrice()) {
            $quote = $pricing->quote($room, $data['guests_label'], $data['check_in'], $data['check_out']);
            $estimate = $quote ? ['nights' => $quote['nights'], 'subtotal' => $quote['subtotal'], 'varies' => $quote['varies']] : null;
        }

        $available = $bookingService->isTypeAvailable($room, $data['check_in'], $data['check_out']);

        return response()->json([
            'available' => $available,
            // For booking several rooms of this type at once.
            'free_rooms' => $bookingService->freeRoomsOfType($room, $data['check_in'], $data['check_out']),
            'estimate' => $estimate,
            'reason' => $available ? null : 'This room is not available for these dates.',
        ]);
    }

    /**
     * The first message the booking form's own validation would give for
     * this room/stay/party, or null if it would pass.
     */
    protected function bookingRuleProblem(Request $request, Room $room, array $data): ?string
    {
        $input = [
            'room_id' => $room->id,
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'guests_label' => $data['guests_label'] ?? null,
        ];

        $form = StoreBookingRequest::createFrom($request)->replace($input);
        $rules = Arr::only($form->rules(), array_keys($input));

        if (empty($input['guests_label'])) {
            unset($rules['guests_label']);
        }

        $validator = Validator::make($input, $rules, $form->messages());

        return $validator->fails() ? $validator->errors()->first() : null;
    }
}
