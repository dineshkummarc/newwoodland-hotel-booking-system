<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomRate;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Per-night pricing from a room's base price plus RoomRate rules.
 *
 * For each night, the first matching rule in this precedence wins:
 *   holiday  >  season  >  weekend  >  base price.
 * Rules of the same type may not overlap (conflictsFor() is checked when a
 * rule is saved), so at most one rule of each type matches a night and the
 * result never depends on rule order or IDs.
 */
class RoomPricingService
{
    /**
     * @return array{nights:int, subtotal:float, varies:bool, nightly:list<array{date:string, rate:float, rule:?string}>}|null
     *         null when the room has no price for this party size
     */
    public function quote(Room $room, string $guestsLabel, string $checkIn, string $checkOut, ?float $baseRate = null): ?array
    {
        $baseRate ??= $room->nightlyRateFor($guestsLabel);

        if ($baseRate === null) {
            return null;
        }

        $partySize = Room::partySize($guestsLabel);
        $rules = RoomRate::active()->forRoom($room)->get()->groupBy('type');
        $nightly = [];

        foreach (CarbonPeriod::create(Carbon::parse($checkIn), Carbon::parse($checkOut)->subDay()) as $night) {
            $rule = $this->ruleFor($rules, $night);

            $nightly[] = [
                'date' => $night->toDateString(),
                'rate' => $rule ? $rule->applyTo($baseRate, $room, $partySize) : round($baseRate, 2),
                'rule' => $rule?->name,
            ];
        }

        return [
            'nights' => count($nightly),
            'subtotal' => round(array_sum(array_column($nightly, 'rate')), 2),
            'varies' => collect($nightly)->contains(fn ($n) => $n['rule'] !== null),
            'nightly' => $nightly,
        ];
    }

    /**
     * Other active rules of the same type that would match some of the same
     * nights for some of the same rooms — saving $rate alongside them would
     * make pricing ambiguous.
     *
     * @return Collection<int, RoomRate>
     */
    public function conflictsFor(RoomRate $rate): Collection
    {
        if (! $rate->is_active) {
            return collect();
        }

        return RoomRate::active()
            ->where('type', $rate->type)
            ->when($rate->exists, fn ($q) => $q->whereKeyNot($rate->getKey()))
            ->get()
            ->filter(function (RoomRate $other) use ($rate) {
                $sameRooms = $rate->room_type === null || $other->room_type === null || $rate->room_type === $other->room_type;

                return $sameRooms && $this->datesOverlap($rate, $other) && $this->daysOverlap($rate, $other);
            })
            ->values();
    }

    /** @param Collection<string, Collection<int, RoomRate>> $rulesByType */
    protected function ruleFor(Collection $rulesByType, Carbon $night): ?RoomRate
    {
        foreach (array_keys(RoomRate::TYPES) as $type) {
            $match = ($rulesByType[$type] ?? collect())->first(fn (RoomRate $r) => $r->coversNight($night));

            if ($match) {
                return $match;
            }
        }

        return null;
    }

    protected function datesOverlap(RoomRate $a, RoomRate $b): bool
    {
        $aStart = $a->start_date?->toDateString() ?? '0000-01-01';
        $aEnd = $a->end_date?->toDateString() ?? '9999-12-31';
        $bStart = $b->start_date?->toDateString() ?? '0000-01-01';
        $bEnd = $b->end_date?->toDateString() ?? '9999-12-31';

        return $aStart <= $bEnd && $bStart <= $aEnd;
    }

    protected function daysOverlap(RoomRate $a, RoomRate $b): bool
    {
        if ($a->type !== RoomRate::TYPE_WEEKEND) {
            return true;
        }

        return (bool) array_intersect(array_map('intval', $a->days_of_week ?? []), array_map('intval', $b->days_of_week ?? []));
    }
}
