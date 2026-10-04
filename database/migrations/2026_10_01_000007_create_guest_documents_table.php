<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional ID/passport records captured by staff at check-in (feature
     * flag GUEST_DOCUMENTS_ENABLED, off by default). document_number is
     * stored encrypted (Eloquent `encrypted` cast, hence text); uploaded
     * files live on the private `local` disk and are only ever streamed
     * through an authenticated staff route.
     */
    public function up(): void
    {
        Schema::create('guest_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->text('document_number');
            $table->string('issuing_country', 100)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_mime', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_documents');
    }
};
