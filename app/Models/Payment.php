<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'booking_id', 'provider', 'transaction_uuid', 'reference_id',
        'amount', 'currency', 'status', 'gateway_response', 'paid_at',
        'last_checked_at', 'check_attempts',
        'review_reason', 'review_flagged_at', 'review_resolved_at', 'review_resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'check_attempts' => 'integer',
            'review_flagged_at' => 'datetime',
            'review_resolved_at' => 'datetime',
        ];
    }

    /**
     * Payments waiting for a human decision (see flagForReview()).
     */
    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->whereNotNull('review_flagged_at')->whereNull('review_resolved_at');
    }

    public function needsReview(): bool
    {
        return $this->review_flagged_at !== null && $this->review_resolved_at === null;
    }

    /**
     * Put this payment on the staff review queue (admin dashboard). Seeing
     * the same situation again (reconciliation runs every few minutes) is a
     * no-op; a NEW situation on an open flag (e.g. an attempt flagged as
     * "check the portal" that has since been paid) replaces the reason.
     * Returns whether anything changed — callers notify staff only then.
     */
    public function flagForReview(string $reason): bool
    {
        $reason = mb_substr($reason, 0, 500);

        if ($this->needsReview()) {
            if ($this->review_reason === $reason) {
                return false;
            }

            $this->forceFill(['review_reason' => $reason])->save();

            return true;
        }

        $this->forceFill([
            'review_reason' => $reason,
            'review_flagged_at' => now(),
            'review_resolved_at' => null,
            'review_resolved_by' => null,
        ])->save();

        return true;
    }

    /**
     * Every payment status change is written to the audit log, whichever
     * path made it — the customer's return, scheduled reconciliation, the
     * expiry check, or staff — so a disputed payment can always be traced.
     */
    protected static function booted(): void
    {
        static::updated(function (Payment $payment) {
            if (! $payment->wasChanged('status')) {
                return;
            }

            AuditLog::create([
                'admin_id' => auth('admin')->id(),
                'action' => 'payment.status_changed',
                'target_type' => 'booking',
                'target_id' => $payment->booking_id,
                'description' => sprintf('%s payment %s: %s → %s.',
                    $payment->provider, $payment->transaction_uuid, $payment->getOriginal('status'), $payment->status),
                'meta' => [
                    'payment_id' => $payment->id,
                    'from' => $payment->getOriginal('status'),
                    'to' => $payment->status,
                    'amount' => (string) $payment->amount,
                    'check_attempts' => $payment->check_attempts,
                ],
            ]);
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Offline methods the guest can choose at checkout, each paid outside
     * any gateway and confirmed by staff, with how long (hours) a booking
     * stays held after the guest chooses it.
     *
     * @return array<string, int>
     */
    public static function offlineHoldHours(): array
    {
        return [
            'bank_transfer' => (int) config('bank_transfer.hold_hours', 24),
            'cash' => (int) config('payments.methods.cash.hold_hours', 24),
        ];
    }

    /**
     * A pending offline (bank transfer / cash) payment recent enough that its
     * booking should still be held for the guest while staff confirm it.
     */
    public function scopeActiveOfflineHold(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where(function (Builder $q) {
                foreach (self::offlineHoldHours() as $provider => $hours) {
                    $q->orWhere(fn (Builder $p) => $p->where('provider', $provider)
                        ->where('created_at', '>=', now()->subHours($hours)));
                }
            });
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * The gateway's own transaction id, as shown to the customer in their
     * wallet app, when it differs from reference_id. For Khalti,
     * reference_id is the pidx (payment session id) and the transaction id
     * comes from the verified lookup response.
     */
    public function gatewayTransactionId(): ?string
    {
        if ($this->provider !== 'khalti') {
            return null;
        }

        $transactionId = $this->gateway_response['lookup']['transaction_id'] ?? null;

        return is_string($transactionId) && $transactionId !== '' ? $transactionId : null;
    }
}
