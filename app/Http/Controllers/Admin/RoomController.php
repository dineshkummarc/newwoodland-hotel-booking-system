<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomRequest;
use App\Models\Room;
use App\Services\AuditLogService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    public function index(SettingsService $settings)
    {
        $rooms = Room::withCount(['images', 'amenities', 'bookings'])->orderBy('name')->orderBy('room_number')->paginate(10);

        // Inventory check against the hotel's confirmed room count (Settings).
        $inventory = [
            'total' => $settings->current()->total_rooms,
            'active' => Room::active()->count(),
            'unpriced' => Room::active()->whereNull('price_per_night')->count(),
        ];

        return view('admin.rooms.index', compact('rooms', 'inventory'));
    }

    public function create()
    {
        $room = new Room;

        return view('admin.rooms.create', compact('room'));
    }

    public function store(StoreRoomRequest $request, AuditLogService $auditLog)
    {
        $data = $request->validated();
        $amenities = $data['amenities'] ?? [];
        unset($data['amenities']);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['price_per_person'] = $request->boolean('price_per_person');
        $data['is_active'] = $request->boolean('is_active');
        $data['availability_status'] = $data['availability_status'] ?? Room::AVAILABILITY_AVAILABLE;

        $room = Room::create($data);

        foreach (array_filter($amenities) as $label) {
            $room->amenities()->create(['label' => $label]);
        }

        $auditLog->log('room.created', 'room', $room->id, "Created room {$room->label()}.", [
            'price_per_night' => $room->price_per_night,
            'is_active' => $room->is_active,
        ]);

        $this->forgetPublicCaches();

        return redirect()->route('admin.rooms.edit', $room)->with('success', 'Room created successfully. You can now upload images.');
    }

    public function edit(Room $room)
    {
        $room->load(['images', 'amenities']);

        return view('admin.rooms.edit', compact('room'));
    }

    public function update(StoreRoomRequest $request, Room $room, AuditLogService $auditLog)
    {
        $before = $room->getAttributes();
        $amenitiesBefore = $room->amenities()->pluck('label')->all();
        $data = $request->validated();
        $amenities = $data['amenities'] ?? null;
        unset($data['amenities']);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['price_per_person'] = $request->boolean('price_per_person');
        $data['is_active'] = $request->boolean('is_active');
        $data['availability_status'] = $data['availability_status'] ?? $room->availability_status;

        $room->update($data);

        if ($amenities !== null) {
            $room->amenities()->delete();
            foreach (array_filter($amenities) as $label) {
                $room->amenities()->create(['label' => $label]);
            }
        }

        $amenitiesAfter = $room->amenities()->pluck('label')->all();
        $auditLog->logChanges('room.updated', 'room', $room, "Updated room {$room->label()}.", $before,
            $amenitiesBefore !== $amenitiesAfter ? ['amenities' => ['before' => $amenitiesBefore, 'after' => $amenitiesAfter]] : []);

        $this->forgetPublicCaches();

        return redirect()->route('admin.rooms.edit', $room)->with('success', 'Room updated successfully.');
    }

    public function destroy(Room $room, AuditLogService $auditLog)
    {
        if ($room->bookings()->exists() || \App\Models\BookingRoom::where('room_id', $room->id)->exists()) {
            return back()->with('error', 'This room has booking history and cannot be deleted. Deactivate it instead.');
        }

        foreach ($room->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $room->delete();

        $auditLog->log('room.deleted', 'room', $room->id, "Deleted room {$room->label()}.");

        $this->forgetPublicCaches();

        return redirect()->route('admin.rooms.index')->with('success', 'Room deleted successfully.');
    }

    public function toggleActive(Room $room, AuditLogService $auditLog)
    {
        $room->update(['is_active' => ! $room->is_active]);

        $auditLog->log($room->is_active ? 'room.activated' : 'room.deactivated', 'room', $room->id,
            ($room->is_active ? 'Activated' : 'Deactivated')." room {$room->label()}.");

        $this->forgetPublicCaches();

        return back()->with('success', 'Room status updated.');
    }

    protected function forgetPublicCaches(): void
    {
        Cache::forget('home.page.data');
        Cache::forget('sitemap.xml');
    }
}
