<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActivityLog extends Model
{
    use Prunable;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'action',
        'entity_type',
        'entity_id',
        'entity_identifier',
        'description',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the prunable model query (retains 1 year of audit logs by default when model:prune runs).
     */
    public function prunable()
    {
        return static::where('created_at', '<=', now()->subDays(365));
    }

    /**
     * Record an audit log entry safely without breaking primary business logic.
     */
    public static function record(
        string $action,
        string $description,
        ?string $entityType = 'Product',
        ?int $entityId = null,
        ?string $entityIdentifier = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ?self {
        try {
            $user = null;
            try {
                $user = auth()->user() ?? (request() instanceof Request ? request()->user() : null);
            } catch (\Throwable) {
                // CLI / seeder / non-HTTP context
            }

            $userName = 'System / Background Task';
            if ($user) {
                $fullName = trim("{$user->first_name} {$user->last_name}");
                $userName = $fullName ?: ($user->name ?? $user->email);
            }

            $ip = null;
            try {
                if (request() instanceof Request) {
                    $rawIp = request()->ip();
                    $ip = $rawIp ? substr((string) $rawIp, 0, 45) : null;
                }
            } catch (\Throwable) {
                // Ignore if not in HTTP context
            }

            return self::create([
                'user_id' => $user?->id,
                'user_name' => $userName,
                'user_email' => $user?->email,
                'action' => strtoupper($action),
                'entity_type' => $entityType ?? 'Product',
                'entity_id' => $entityId,
                'entity_identifier' => $entityIdentifier ? substr((string) $entityIdentifier, 0, 255) : null,
                'description' => $description,
                'old_values' => self::sanitizePayload($oldValues),
                'new_values' => self::sanitizePayload($newValues),
                'ip_address' => $ip,
            ]);
        } catch (\Throwable $e) {
            // Fail-safe: write to standard application log without interrupting core operations
            Log::warning('ActivityLog recording failed: '.$e->getMessage(), [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);

            return null;
        }
    }

    /**
     * Sanitize values to ensure sensitive keys (passwords, tokens) are never persisted in audit logs.
     */
    protected static function sanitizePayload(?array $payload): ?array
    {
        if (! $payload) {
            return null;
        }

        $sensitive = [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'secret',
            'remember_token',
        ];

        $cleaned = [];
        foreach ($payload as $key => $val) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $cleaned[$key] = '[REDACTED]';
            } elseif (is_array($val)) {
                $cleaned[$key] = self::sanitizePayload($val);
            } else {
                $cleaned[$key] = $val;
            }
        }

        return $cleaned;
    }
}
