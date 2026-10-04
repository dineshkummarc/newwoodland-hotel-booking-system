<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pricing rules layered on a room's base price (rooms.price_per_night).
     * Per night, precedence is holiday > season > weekend > base; rules of
     * the same type may not overlap (enforced when saving, see
     * RoomPricingService::conflictsFor). room_type is a room name — the same
     * notion of "type" as Room::scopeSameTypeAs — or null for every room.
     *
     * bookings.price_breakdown stores the per-night rates a booking was
     * priced at, so later rule changes never alter existing bookings.
     */
    public function up(): void
    {
        Schema::create('room_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20);
            $table->string('room_type')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('days_of_week')->nullable();
            $table->string('adjustment', 10);
            $table->decimal('value', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'type']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->json('price_breakdown')->nullable()->after('nightly_rate');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('price_breakdown');
        });

        Schema::dropIfExists('room_rates');
    }
};
