<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

/**
 * Rooms as confirmed by the client (New Woodland Hotel & Lounge, 9 rooms in
 * total). Each row is one physical, bookable room; rooms sharing a name are
 * one room type on the public site. Only the rooms whose type is confirmed
 * are seeded here — the remaining rooms are added in Admin → Rooms once the
 * hotel confirms their type (Admin → Rooms shows "N of 9 set up").
 *
 * Safe to re-run on an existing database: rooms are matched by slug (or a
 * legacy slug) and updated in place — nothing is deleted, booking history
 * and uploaded images are untouched. Nightly rates are the client-confirmed
 * prices (Package Room is charged per person, for groups of 4–5). Fields
 * the client has not confirmed (room size, other guest limits, badge) are
 * cleared rather than guessed.
 */
class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            [
                'name' => 'Package Room',
                'slug' => 'package-room',
                // NPR 1,000 per person per night, for a group of 4–5 people.
                'price_per_night' => 1000,
                'price_per_person' => true,
                'min_guests' => 4,
                'max_guests' => 5,
                'amenities' => ['Table', 'Sofa', 'Washroom', 'AC', 'Cupboard'],
            ],
            [
                'name' => 'Deluxe Room',
                'slug' => 'deluxe-room',
                'price_per_night' => 2000,
                'amenities' => ['AC', 'Sofa', 'Table', 'Shoe Rack', 'Cupboard', 'Washroom'],
            ],
            [
                'name' => 'Family Room',
                'slug' => 'family-room',
                'legacy_slugs' => ['family-suite'],
                'price_per_night' => 2500,
                'amenities' => ['TV', 'Sofa', 'Bed', 'Cupboard', 'Shoe Rack', 'AC', 'Washroom'],
            ],
            // The hotel has two VIP single rooms: same type, two rooms.
            [
                'name' => 'Single Room (VIP)',
                'slug' => 'single-room-vip',
                'price_per_night' => 1500,
                'amenities' => ['AC', 'Clothes Hanger', 'Cupboard', 'Shoe Rack', 'Washroom'],
            ],
            [
                'name' => 'Single Room (VIP)',
                'slug' => 'single-room-vip-2',
                'price_per_night' => 1500,
                'amenities' => ['AC', 'Clothes Hanger', 'Cupboard', 'Shoe Rack', 'Washroom'],
            ],
            [
                'name' => 'Group Room',
                'slug' => 'group-room',
                'price_per_night' => 3000,
                'bed_type' => '5 Beds',
                'amenities' => ['5 Beds', 'Table', 'AC'],
            ],
        ];

        foreach ($rooms as $data) {
            $room = Room::whereIn('slug', [$data['slug'], ...($data['legacy_slugs'] ?? [])])->first() ?? new Room;

            $room->fill([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'short_description' => $this->describe($data['amenities']),
                'description' => null,
                'bed_type' => $data['bed_type'] ?? null,
                'size_sqm' => null,
                'price_per_night' => $data['price_per_night'],
                'price_per_person' => $data['price_per_person'] ?? false,
                'min_guests' => $data['min_guests'] ?? null,
                'max_guests' => $data['max_guests'] ?? null,
                'badge_label' => null,
                'is_featured' => true,
                'is_active' => true,
            ]);

            if (! $room->exists) {
                $room->availability_status = Room::AVAILABILITY_AVAILABLE;
            }

            $room->save();

            $room->amenities()->delete();
            foreach ($data['amenities'] as $label) {
                $room->amenities()->create(['label' => $label]);
            }
        }

        // Placeholder room from earlier development that is not one of the
        // client's room types. Hidden from guests, not deleted: it may have
        // booking history. Rename/reuse it in the admin if it is a real room.
        Room::where('slug', 'executive-room')->update(['is_active' => false, 'is_featured' => false]);
    }

    /**
     * A one-line summary built only from the room's confirmed amenities.
     *
     * @param  list<string>  $amenities
     */
    protected function describe(array $amenities): string
    {
        $items = array_map(fn (string $a) => in_array($a, ['AC', 'TV'], true) ? $a : strtolower($a), $amenities);
        $last = array_pop($items);

        return 'Room with '.($items ? implode(', ', $items).' and ' : '').$last.'.';
    }
}
