<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server-side payment reconciliation (payments:reconcile) and the staff
     * "needs review" queue:
     *  - last_checked_at / check_attempts: when the gateway was last asked
     *    about a pending attempt, and how often — an unanswered check
     *    (timeout, outage) leaves the payment pending for the next run;
     *  - review_*: a payment that needs a human (paid after its booking
     *    expired, paid for a room that was taken meanwhile, paid twice,
     *    or an attempt the gateway cannot be asked about). Shown on the
     *    admin dashboard until staff mark it resolved.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('last_checked_at')->nullable()->after('paid_at');
            $table->unsignedInteger('check_attempts')->default(0)->after('last_checked_at');
            $table->string('review_reason', 500)->nullable()->after('check_attempts');
            $table->timestamp('review_flagged_at')->nullable()->index()->after('review_reason');
            $table->timestamp('review_resolved_at')->nullable()->after('review_flagged_at');
            $table->foreignId('review_resolved_by')->nullable()->after('review_resolved_at')
                ->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('review_resolved_by');
            $table->dropIndex(['review_flagged_at']);
            $table->dropColumn(['last_checked_at', 'check_attempts', 'review_reason', 'review_flagged_at', 'review_resolved_at']);
        });
    }
};
