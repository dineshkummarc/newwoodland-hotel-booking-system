<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomRateRequest;
use App\Models\Room;
use App\Models\RoomRate;
use App\Services\AuditLogService;
use App\Services\RoomPricingService;
use Illuminate\Validation\ValidationException;

/**
 * Weekend / season / holiday pricing rules. Changes only affect bookings
 * made afterwards — each booking keeps the per-night rates it was priced at.
 */
class RoomRateController extends Controller
{
    public function index()
    {
        $rates = RoomRate::orderByDesc('is_active')->orderBy('type')->orderBy('start_date')->get();

        return view('admin.room-rates.index', compact('rates'));
    }

    public function create()
    {
        return view('admin.room-rates.create', ['rate' => new RoomRate(['is_active' => true, 'adjustment' => RoomRate::ADJUSTMENT_PERCENT]), 'roomTypes' => $this->roomTypes()]);
    }

    public function store(StoreRoomRateRequest $request, RoomPricingService $pricing, AuditLogService $auditLog)
    {
        $rate = new RoomRate($request->rateData());
        $this->guardConflicts($rate, $pricing);
        $rate->save();

        $auditLog->log('room_rate.created', 'room_rate', $rate->id, "Created pricing rule \"{$rate->name}\" ({$rate->typeLabel()}, {$rate->adjustmentLabel()}).", $rate->only([
            'type', 'room_type', 'start_date', 'end_date', 'days_of_week', 'adjustment', 'value', 'is_active',
        ]));

        return redirect()->route('admin.room-rates.index')->with('success', 'Pricing rule created. It applies to new bookings only.');
    }

    public function edit(RoomRate $roomRate)
    {
        return view('admin.room-rates.edit', ['rate' => $roomRate, 'roomTypes' => $this->roomTypes()]);
    }

    public function update(StoreRoomRateRequest $request, RoomRate $roomRate, RoomPricingService $pricing, AuditLogService $auditLog)
    {
        $before = $roomRate->getAttributes();
        $roomRate->fill($request->rateData());
        $this->guardConflicts($roomRate, $pricing);
        $roomRate->save();

        $auditLog->logChanges('room_rate.updated', 'room_rate', $roomRate, "Updated pricing rule \"{$roomRate->name}\".", $before);

        return redirect()->route('admin.room-rates.index')->with('success', 'Pricing rule updated. Existing bookings keep their prices.');
    }

    public function destroy(RoomRate $roomRate, AuditLogService $auditLog)
    {
        $roomRate->delete();

        $auditLog->log('room_rate.deleted', 'room_rate', $roomRate->id, "Deleted pricing rule \"{$roomRate->name}\".");

        return redirect()->route('admin.room-rates.index')->with('success', 'Pricing rule deleted. Existing bookings keep their prices.');
    }

    protected function guardConflicts(RoomRate $rate, RoomPricingService $pricing): void
    {
        $conflicts = $pricing->conflictsFor($rate);

        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'start_date' => 'This rule overlaps another active '.strtolower($rate->typeLabel()).' rule for the same rooms: '
                    .$conflicts->pluck('name')->map(fn ($n) => "\"{$n}\"")->implode(', ')
                    .'. Adjust the dates, days or rooms, or deactivate the other rule.',
            ]);
        }
    }

    protected function roomTypes(): array
    {
        return Room::query()->distinct()->orderBy('name')->pluck('name')->all();
    }
}
