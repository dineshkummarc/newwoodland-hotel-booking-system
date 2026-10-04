<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual refund tracking. The money is returned outside the app (cash,
 * bank transfer, or the gateway's own merchant portal); this records what
 * was owed and what was returned. Payment rows are never modified, and a
 * booking can never have more refunded (pending + completed) than was
 * actually collected for it.
 */
class RefundService
{
    /**
     * @param  array{amount:float|string,method:string,reference?:?string,notes?:?string,payment_id?:?int,completed?:bool}  $data
     *
     * @throws ValidationException
     */
    public function record(Booking $booking, array $data, ?int $adminId): Refund
    {
        return DB::transaction(function () use ($booking, $data, $adminId) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $amount = round((float) $data['amount'], 2);
            $refundable = $booking->refundableAmount();

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'The refund amount must be greater than zero.']);
            }

            if ($amount > $refundable) {
                throw ValidationException::withMessages([
                    'amount' => 'The refund cannot exceed the amount still refundable for this booking ('.npr($refundable).').',
                ]);
            }

            $this->guardDuplicate($booking, $amount, $data);

            if (! empty($data['payment_id']) && ! $booking->payments()->whereKey($data['payment_id'])->where('status', 'completed')->exists()) {
                throw ValidationException::withMessages(['payment_id' => 'Choose a completed payment of this booking.']);
            }

            $completed = (bool) ($data['completed'] ?? false);

            return $booking->refunds()->create([
                'payment_id' => $data['payment_id'] ?? null,
                'amount' => $amount,
                'method' => $data['method'],
                'status' => $completed ? Refund::STATUS_COMPLETED : Refund::STATUS_PENDING,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $adminId,
                'processed_by' => $completed ? $adminId : null,
                'refunded_at' => $completed ? now() : null,
            ]);
        });
    }

    /**
     * Duplicate protection (runs under the booking row lock): the same
     * refund reference can only be recorded once per booking, and an
     * identical refund (amount + method) submitted again within a few
     * minutes — a double click or resubmitted form — is refused. Cancelled
     * refunds never count.
     *
     * @throws ValidationException
     */
    protected function guardDuplicate(Booking $booking, float $amount, array $data): void
    {
        $active = $booking->refunds()->openOrCompleted();
        $reference = trim((string) ($data['reference'] ?? ''));

        if ($reference !== '' && (clone $active)->whereRaw('LOWER(reference) = ?', [mb_strtolower($reference)])->exists()) {
            throw ValidationException::withMessages([
                'reference' => 'A refund with this reference has already been recorded for this booking.',
            ]);
        }

        // With a fresh reference it is a distinct refund (checked above).
        $recentTwin = $reference === '' && (clone $active)
            ->where('amount', $amount)
            ->where('method', $data['method'])
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
            ->exists();

        if ($recentTwin) {
            throw ValidationException::withMessages([
                'amount' => 'An identical refund was just recorded for this booking. If this is really a second refund, enter its own reference.',
            ]);
        }
    }

    /** Identical refunds (amount + method) within this window are treated as duplicates. */
    protected const DUPLICATE_WINDOW_MINUTES = 5;

    /**
     * Mark a pending refund as paid out.
     *
     * @throws ValidationException
     */
    public function complete(Refund $refund, ?string $reference, ?int $adminId): Refund
    {
        return $this->transition($refund, function (Refund $refund) use ($reference, $adminId) {
            $refund->update([
                'status' => Refund::STATUS_COMPLETED,
                'reference' => $reference ?: $refund->reference,
                'processed_by' => $adminId,
                'refunded_at' => now(),
            ]);
        });
    }

    /**
     * Withdraw a pending refund that will not be paid (e.g. recorded by mistake).
     *
     * @throws ValidationException
     */
    public function cancel(Refund $refund, ?int $adminId): Refund
    {
        return $this->transition($refund, function (Refund $refund) use ($adminId) {
            $refund->update([
                'status' => Refund::STATUS_CANCELLED,
                'processed_by' => $adminId,
            ]);
        });
    }

    protected function transition(Refund $refund, callable $apply): Refund
    {
        return DB::transaction(function () use ($refund, $apply) {
            Booking::query()->whereKey($refund->booking_id)->lockForUpdate()->firstOrFail();
            $refund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if (! $refund->isPending()) {
                throw ValidationException::withMessages(['refund' => 'Only a pending refund can be changed.']);
            }

            $apply($refund);

            return $refund;
        });
    }
}
