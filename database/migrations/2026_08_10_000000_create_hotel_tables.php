<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price_per_night', 10, 2);
            $table->unsignedTinyInteger('max_guests')->default(2);
            $table->string('bed_type')->nullable();
            $table->unsignedInteger('size_sqm')->nullable();
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->string('badge_label')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('icon')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('show_on_homepage')->default(true);
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('content');
            $table->string('avatar_path')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('confirmation_code')->unique();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedInteger('nights');
            $table->string('guests_label');
            $table->string('customer_name');
            $table->string('email');
            $table->string('phone', 50);
            $table->string('country')->nullable();
            $table->text('special_requests')->nullable();
            $table->decimal('nightly_rate', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['room_id', 'check_in', 'check_out'], 'bookings_room_dates_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('rooms');
    }
};
