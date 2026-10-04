<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff-only notes and VIP flag for a guest, keyed by normalized email —
     * the one identifier shared by registered customers and no-account
     * guest bookings. Kept off the users table on purpose: nothing here is
     * ever reachable from the customer-facing side of the app.
     */
    public function up(): void
    {
        Schema::create('guest_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->boolean('is_vip')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_profiles');
    }
};
