<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Room extends Model
{
    use HasFactory;

    public const AVAILABILITY_AVAILABLE = 'available';

    public const AVAILABILITY_MAINTENANCE = 'maintenance';

    public const AVAILABILITY_OUT_OF_SERVICE = 'out_of_service';

    public const AVAILABILITY_STATUSES = [
        self::AVAILABILITY_AVAILABLE => 'Available',
        self::AVAILABILITY_MAINTENANCE => 'Maintenance',
        self::AVAILABILITY_OUT_OF_SERVICE => 'Out of Service',
    ];

    protected $fillable = [
        'name', 'room_number', 'slug', 'short_description', 'description', 'price_per_night',
        'price_per_person', 'min_guests', 'max_guests', 'bed_type', 'size_sqm', 'rating', 'review_count',
        'badge_label', 'is_featured', 'is_active', 'availability_status',
    ];

    protected function casts(): array
    {
        return [
            'price_per_night' => 'decimal:2',
            'price_per_person' => 'boolean',
            'rating' => 'decimal:1',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class)->orderBy('sort_order');
    }

    public function amenities(): HasMany
    {
        return $this->hasMany(RoomAmenity::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Rooms that can accept NEW bookings right now — active (publicly
     * listed) AND not currently under maintenance/out of service. A room
     * can be active but temporarily unbookable, or bookable-by-status but
     * hidden from the catalog; only the intersection is actually bookable.
     */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('availability_status', self::AVAILABILITY_AVAILABLE)
            ->whereNotNull('price_per_night');
    }

    /**
     * A room with no confirmed nightly rate yet can be listed publicly (so
     * its amenities are visible) but can never be booked — there is nothing
     * to charge.
     */
    public function isBookable(): bool
    {
        return $this->is_active
            && $this->availability_status === self::AVAILABILITY_AVAILABLE
            && $this->hasPrice();
    }

    public function hasPrice(): bool
    {
        return $this->price_per_night !== null;
    }

    /**
     * Other rooms of the same type. Rooms sharing a name are one room type
     * to guests (e.g. two "Single Room (VIP)" rooms): the public site lists
     * the type once, and a booking takes whichever of them is free.
     */
    public function scopeSameTypeAs(Builder $query, self $room): Builder
    {
        return $query->where('name', $room->name)->whereKeyNot($room->getKey());
    }

    /**
     * One room per type for public listings, preferring a bookable one as
     * the representative so "Book" links point at a room that can take
     * bookings. Each representative gets `type_count` = rooms of that type.
     *
     * @param  Collection<int, Room>  $rooms
     * @return Collection<int, Room>
     */
    public static function onePerType(Collection $rooms): Collection
    {
        return $rooms->groupBy('name')
            ->map(function (Collection $sameType) {
                $room = $sameType->sortByDesc(fn (Room $r) => $r->isBookable())->first();
                $room->type_count = $sameType->count();

                return $room;
            })
            ->values();
    }

    /**
     * Staff-facing name that tells same-type rooms apart, e.g.
     * "Single Room (VIP) — Room 101".
     */
    public function label(): string
    {
        return filled($this->room_number) ? "{$this->name} — Room {$this->room_number}" : $this->name;
    }

    public function availabilityStatusLabel(): string
    {
        return self::AVAILABILITY_STATUSES[$this->availability_status] ?? ucfirst($this->availability_status);
    }

    /**
     * Best-effort capacity guard against the free-text guest count label
     * used at booking time (e.g. "2 Adults", "Family") — not exact
     * occupancy modeling, just a guard against an obvious mismatch like a
     * "4 Adults" booking on a 2-guest room. A label with a leading number
     * is checked directly; "Family" (no literal number) is treated as a
     * minimum party of 3 for this purpose, since it has no number to parse.
     * Anything else unparseable is not flagged — we can't validate what we
     * can't read, and this is a guard, not the source of truth. A room
     * with no confirmed guest limit (max_guests null) is never flagged.
     */
    public function exceedsCapacityFor(string $guestsLabel): bool
    {
        if ($this->max_guests === null) {
            return false;
        }

        $label = trim($guestsLabel);

        if (($size = self::partySize($label)) !== null) {
            return $size > $this->max_guests;
        }

        if (strtolower($label) === 'family') {
            return $this->max_guests < 3;
        }

        return false;
    }

    /**
     * The number of guests in a label such as "4 Adults", or null when the
     * label carries no number (e.g. "Family").
     */
    public static function partySize(string $guestsLabel): ?int
    {
        return preg_match('/^\s*(\d+)/', $guestsLabel, $matches) ? (int) $matches[1] : null;
    }

    /**
     * Why this party can't book this room, or null if it can: too many
     * guests, too few for a room with a minimum (e.g. a 4–5 person group
     * room), or no exact head count for a room charged per person.
     */
    public function partySizeErrorFor(string $guestsLabel): ?string
    {
        $size = self::partySize($guestsLabel);

        if ($this->exceedsCapacityFor($guestsLabel)) {
            return "This room accommodates a maximum of {$this->max_guests} guest(s). Please choose a smaller party size or a different room.";
        }

        if ($this->price_per_person && $size === null) {
            return 'This room is priced per person. Please choose the exact number of guests.';
        }

        if ($this->min_guests !== null && $size !== null && $size < $this->min_guests) {
            return "This room is for groups of at least {$this->min_guests} guests. Please choose a larger party size or a different room.";
        }

        return null;
    }

    /**
     * The nightly charge for this party: the room rate, or for a room priced
     * per person, the rate × number of guests. Null if there is no price or
     * the party size can't be read for a per-person room.
     */
    public function nightlyRateFor(string $guestsLabel): ?float
    {
        if (! $this->hasPrice()) {
            return null;
        }

        if (! $this->price_per_person) {
            return (float) $this->price_per_night;
        }

        $size = self::partySize($guestsLabel);

        return $size === null ? null : round((float) $this->price_per_night * $size, 2);
    }

    /**
     * Unit shown after the price, e.g. "NPR 1,000 / person / night".
     */
    public function priceUnit(): string
    {
        return $this->price_per_person ? '/ person / night' : '/ night';
    }

    public function primaryImage(): ?RoomImage
    {
        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }
}
