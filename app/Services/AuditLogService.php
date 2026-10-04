<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * A single, centralized place to write audit entries so no controller has
 * to duplicate "who is acting, when, on what" bookkeeping. Deliberately
 * thin — this is a lightweight trail of staff actions, not a general
 * event/logging framework. Never pass secrets (passwords, payment
 * credentials) into $meta; it is stored as plain JSON. As a safety net,
 * any meta key that looks like a secret is redacted before storage.
 */
class AuditLogService
{
    /** Meta keys whose values are never stored (matched case-insensitively, anywhere in the key). */
    protected const REDACTED_KEYS = ['password', 'token', 'secret', 'api_key', 'document_number'];

    /** Long text (descriptions, policies) is truncated so the log stays readable. */
    protected const MAX_VALUE_LENGTH = 300;

    public function log(string $action, ?string $targetType, ?int $targetId, string $description, array $meta = []): AuditLog
    {
        return AuditLog::create([
            'admin_id' => Auth::guard('admin')->id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'description' => $description,
            'meta' => $meta ? $this->sanitize($meta) : null,
        ]);
    }

    /**
     * Log an update with only the fields that actually changed, as
     * before/after pairs. $before is the model's attributes captured before
     * the save. Returns null (and logs nothing) when nothing changed.
     */
    public function logChanges(string $action, string $targetType, Model $model, string $description, array $before, array $extraMeta = []): ?AuditLog
    {
        $after = Arr::except($model->getChanges(), ['created_at', 'updated_at']);

        if (! $after && ! $extraMeta) {
            return null;
        }

        return $this->log($action, $targetType, $model->getKey(), $description, array_merge(
            $after ? ['before' => Arr::only($before, array_keys($after)), 'after' => $after] : [],
            $extraMeta,
        ));
    }

    protected function sanitize(array $meta): array
    {
        foreach ($meta as $key => $value) {
            if (is_string($key) && Str::contains(strtolower($key), self::REDACTED_KEYS)) {
                $meta[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $meta[$key] = $this->sanitize($value);
            } elseif (is_string($value)) {
                $meta[$key] = Str::limit($value, self::MAX_VALUE_LENGTH);
            }
        }

        return $meta;
    }
}
