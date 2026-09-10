<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\BookingService;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::active()->with(['images', 'amenities'])->orderBy('name')->get();

        return view('rooms', compact('rooms'));
    }

    public function show(Room $room)
    {
        abort_unless($room->is_active, 404);

        $room->load(['images', 'amenities']);

        $relatedRooms = Room::active()
            ->where('id', '!=', $room->id)
            ->with('images')
            ->orderBy('is_featured', 'desc')
            ->orderBy('name')
            ->take(3)
            ->get();

        return view('rooms-details', compact('room', 'relatedRooms'));
    }

    public function availability(Request $request, Room $room, BookingService $bookingService)
    {
        $data = $request->validate([
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ]);

        $available = $room->is_active
            && $bookingService->isAvailable($room->id, $data['check_in'], $data['check_out']);

        return response()->json(['available' => $available]);
    }
}
