<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'auditable_type',
        'auditable_id',
        'action',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public static function logAction(
        int $adminId,
        string $auditableType,
        int $auditableId,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        string $reason,
        string $ipAddress,
        ?string $userAgent = null
    ): self {
        return static::create([
            'admin_id' => $adminId,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }
}
