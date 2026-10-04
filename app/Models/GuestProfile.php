<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Staff-only guest record (VIP flag + internal notes), keyed by normalized
 * email so it covers both registered customers and no-account guests. Only
 * ever read/written from the admin area.
 */
class GuestProfile extends Model
{
    protected $fillable = ['email', 'is_vip', 'notes', 'updated_by'];

    protected function casts(): array
    {
        return ['is_vip' => 'boolean'];
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function forEmail(string $email): ?self
    {
        return static::where('email', static::normalizeEmail($email))->first();
    }

    /**
     * Profiles for many emails at once, keyed by normalized email — for
     * badge display in lists without an N+1 query.
     *
     * @param  iterable<string>  $emails
     * @return Collection<string, self>
     */
    public static function forEmails(iterable $emails): Collection
    {
        $normalized = collect($emails)->filter()->map(fn ($e) => static::normalizeEmail($e))->unique()->values();

        return $normalized->isEmpty()
            ? collect()
            : static::whereIn('email', $normalized)->get()->keyBy('email');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }
}
