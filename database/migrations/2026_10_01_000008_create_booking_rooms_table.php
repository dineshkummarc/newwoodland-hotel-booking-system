<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additional rooms on a multi-room booking. The booking's own room_id /
     * guests_label / nightly_rate / price_breakdown remain its primary room,
     * so every existing single-room booking is unchanged and needs no
     * backfill; only rooms 2..n of a booking live here. bookings.total_price
     * covers all rooms (less the booking's discount).
     *
     * room_id is restrictOnDelete: a room with booking history must be
     * deactivated, never deleted (same rule as the primary room).
     */
    public function up(): void
    {
        Schema::create('booking_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('guests_label', 50);
            $table->decimal('nightly_rate', 10, 2);
            $table->json('price_breakdown')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();

            $table->unique(['booking_id', 'room_id']);
            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_rooms');
    }
};
