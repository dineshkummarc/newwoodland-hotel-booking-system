<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A pricing rule on top of a room's base price. See RoomPricingService for
 * how rules are combined (holiday > season > weekend > base).
 */
class RoomRate extends Model
{
    public const TYPE_WEEKEND = 'weekend';

    public const TYPE_SEASON = 'season';

    public const TYPE_HOLIDAY = 'holiday';

    /** Highest precedence first. */
    public const TYPES = [
        self::TYPE_HOLIDAY => 'Holiday / special date',
        self::TYPE_SEASON => 'Season',
        self::TYPE_WEEKEND => 'Weekend / day of week',
    ];

    public const ADJUSTMENT_FIXED = 'fixed';

    public const ADJUSTMENT_PERCENT = 'percent';

    /** ISO-8601 day numbers (1 = Monday … 7 = Sunday). */
    public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    protected $fillable = [
        'name', 'type', 'room_type', 'start_date', 'end_date', 'days_of_week', 'adjustment', 'value', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'days_of_week' => 'array',
            'value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Rules that can apply to a room: its own type, or every room. */
    public function scopeForRoom(Builder $query, Room $room): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('room_type')->orWhere('room_type', $room->name));
    }

    /**
     * Whether this rule covers the night starting on $night (dates are
     * inclusive; a weekend rule without dates applies all year).
     */
    public function coversNight(CarbonInterface $night): bool
    {
        if ($this->start_date && $night->lt($this->start_date->copy()->startOfDay())) {
            return false;
        }

        if ($this->end_date && $night->gt($this->end_date->copy()->endOfDay())) {
            return false;
        }

        if ($this->type === self::TYPE_WEEKEND) {
            return in_array($night->isoWeekday(), array_map('intval', $this->days_of_week ?? []), true);
        }

        return true;
    }

    /**
     * The nightly rate for a night this rule covers. A fixed value is the
     * price per room (or per person, for rooms priced per person); a
     * percentage adjusts the booking's base rate. Never below zero.
     */
    public function applyTo(float $baseRate, Room $room, ?int $partySize): float
    {
        if ($this->adjustment === self::ADJUSTMENT_PERCENT) {
            return max(0, round($baseRate * (1 + (float) $this->value / 100), 2));
        }

        $fixed = (float) $this->value;

        return max(0, round($room->price_per_person ? $fixed * max(1, (int) $partySize) : $fixed, 2));
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function adjustmentLabel(): string
    {
        return $this->adjustment === self::ADJUSTMENT_PERCENT
            ? ((float) $this->value >= 0 ? '+' : '').rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').'%'
            : npr($this->value).' / night';
    }

    public function daysLabel(): string
    {
        return collect($this->days_of_week ?? [])->map(fn ($d) => self::DAYS[(int) $d] ?? $d)->implode(', ');
    }
}
