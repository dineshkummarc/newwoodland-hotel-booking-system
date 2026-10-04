<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The hotel's PAN/VAT registration number, printed on invoices once the
     * hotel provides it. Deliberately empty until then — never seeded.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('pan_vat_number', 50)->nullable()->after('bank_qr_path');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('pan_vat_number');
        });
    }
};
