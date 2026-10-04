<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneOtp extends Model
{
    use Prunable;

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public const PURPOSE_PHONE_VERIFICATION = 'phone_verification';

    /** Staff equivalents — a separate namespace, so a customer and a staff member sharing a phone never collide. */
    public const PURPOSE_ADMIN_PASSWORD_RESET = 'admin_password_reset';

    public const PURPOSE_ADMIN_PHONE_VERIFICATION = 'admin_phone_verification';

    protected $fillable = ['user_id', 'admin_id', 'phone', 'purpose', 'code_hash', 'expires_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Codes that can still be checked: not yet verified, not invalidated,
     * not expired.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('verified_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Old rows are useless once expired; keep a day for troubleshooting.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDay());
    }
}
