<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomRequest;
use App\Models\Room;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::withCount(['images', 'amenities', 'bookings'])->orderBy('name')->paginate(10);

        return view('admin.rooms.index', compact('rooms'));
    }

    public function create()
    {
        $room = new Room;

        return view('admin.rooms.create', compact('room'));
    }

    public function store(StoreRoomRequest $request)
    {
        $data = $request->validated();
        $amenities = $data['amenities'] ?? [];
        unset($data['amenities']);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');

        $room = Room::create($data);

        foreach (array_filter($amenities) as $label) {
            $room->amenities()->create(['label' => $label]);
        }

        $this->forgetPublicCaches();

        return redirect()->route('admin.rooms.edit', $room)->with('success', 'Room created successfully. You can now upload images.');
    }

    public function edit(Room $room)
    {
        $room->load(['images', 'amenities']);

        return view('admin.rooms.edit', compact('room'));
    }

    public function update(StoreRoomRequest $request, Room $room)
    {
        $data = $request->validated();
        $amenities = $data['amenities'] ?? null;
        unset($data['amenities']);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');

        $room->update($data);

        if ($amenities !== null) {
            $room->amenities()->delete();
            foreach (array_filter($amenities) as $label) {
                $room->amenities()->create(['label' => $label]);
            }
        }

        $this->forgetPublicCaches();

        return redirect()->route('admin.rooms.edit', $room)->with('success', 'Room updated successfully.');
    }

    public function destroy(Room $room)
    {
        if ($room->bookings()->exists()) {
            return back()->with('error', 'This room has booking history and cannot be deleted. Deactivate it instead.');
        }

        foreach ($room->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $room->delete();

        $this->forgetPublicCaches();

        return redirect()->route('admin.rooms.index')->with('success', 'Room deleted successfully.');
    }

    public function toggleActive(Room $room)
    {
        $room->update(['is_active' => ! $room->is_active]);

        $this->forgetPublicCaches();

        return back()->with('success', 'Room status updated.');
    }

    protected function forgetPublicCaches(): void
    {
        Cache::forget('home.page.data');
        Cache::forget('sitemap.xml');
    }
}
