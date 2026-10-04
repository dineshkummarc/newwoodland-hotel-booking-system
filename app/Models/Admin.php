<?php

namespace App\Models;

use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_FRONT_DESK = 'front_desk';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_FRONT_DESK => 'Front Desk',
    ];

    /**
     * Roles with full back-office access (rooms, facilities, gallery,
     * settings, reports, etc). front_desk is deliberately excluded — its
     * access is limited to bookings/check-in/check-out/guest directory,
     * enforced server-side by the 'admin.role' middleware, not by hiding
     * links in Blade.
     */
    public const FULL_ACCESS_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN];

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'phone_verified_at' => 'datetime',
        ];
    }

    /**
     * Whether this staff member proved ownership of phone_e164 by SMS code —
     * only then can the number be used to reset their password.
     * (phone_e164 / phone_verified_at are deliberately not mass-assignable.)
     */
    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null && $this->phone_e164 !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function hasFullAccess(): bool
    {
        return in_array($this->role, self::FULL_ACCESS_ROLES, true);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether this admin is the only remaining active super_admin — used to
     * refuse deactivating or demoting the last one, which would otherwise
     * permanently lock every account-management screen behind a role
     * nobody can reach anymore.
     */
    public function isLastActiveSuperAdmin(): bool
    {
        if (! $this->isSuperAdmin() || ! $this->is_active) {
            return false;
        }

        return self::query()
            ->where('role', self::ROLE_SUPER_ADMIN)
            ->where('is_active', true)
            ->count() <= 1;
    }

    /**
     * Staff reset links go to the staff reset page, not the customer one.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AdminResetPasswordNotification($token));
    }
}
