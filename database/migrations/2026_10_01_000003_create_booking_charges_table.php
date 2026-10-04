<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Incidental charges added to a stay (restaurant, laundry, minibar…).
     * Never deleted: a mistaken charge is voided (voided_at/by/reason) so the
     * folio history stays complete. bookings.total_price stays the agreed
     * room price; charges are added on top of it (Booking::grandTotal()).
     */
    public function up(): void
    {
        Schema::create('booking_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('description');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('amount', 10, 2);
            $table->timestamp('charged_at');
            $table->text('notes')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_charges');
    }
};
