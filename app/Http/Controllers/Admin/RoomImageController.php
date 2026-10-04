<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomImageRequest;
use App\Models\Room;
use App\Models\RoomImage;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class RoomImageController extends Controller
{
    public function store(StoreRoomImageRequest $request, Room $room, AuditLogService $auditLog)
    {
        $path = $request->file('image')->store('rooms', 'public');

        $isPrimary = $request->boolean('is_primary') || ! $room->images()->exists();

        if ($isPrimary) {
            $room->images()->update(['is_primary' => false]);
        }

        $image = $room->images()->create([
            'path' => $path,
            'is_primary' => $isPrimary,
            'sort_order' => $room->images()->max('sort_order') + 1,
        ]);

        $auditLog->log('room.image_added', 'room', $room->id, "Added an image to room {$room->label()}.", [
            'image_id' => $image->id,
            'path' => $path,
            'is_primary' => $isPrimary,
        ]);

        Cache::forget('home.page.data');

        return back()->with('success', 'Image uploaded successfully.');
    }

    public function destroy(Room $room, RoomImage $image, AuditLogService $auditLog)
    {
        abort_unless($image->room_id === $room->id, 404);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        $auditLog->log('room.image_deleted', 'room', $room->id, "Deleted an image from room {$room->label()}.", [
            'image_id' => $image->id,
            'path' => $image->path,
        ]);

        Cache::forget('home.page.data');

        return back()->with('success', 'Image deleted successfully.');
    }

    public function makePrimary(Room $room, RoomImage $image, AuditLogService $auditLog)
    {
        abort_unless($image->room_id === $room->id, 404);

        $room->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        $auditLog->log('room.image_primary', 'room', $room->id, "Changed the primary image of room {$room->label()}.", [
            'image_id' => $image->id,
        ]);

        Cache::forget('home.page.data');

        return back()->with('success', 'Primary image updated.');
    }

    public function reorder(Request $request, Room $room)
    {
        $order = $request->validate(['order' => ['required', 'array']])['order'];

        foreach ($order as $index => $imageId) {
            RoomImage::where('id', $imageId)->where('room_id', $room->id)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }
}
