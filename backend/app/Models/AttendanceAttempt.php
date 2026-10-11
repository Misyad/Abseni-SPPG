<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'reason_code',
        'message',
        'latitude',
        'longitude',
        'accuracy',
        'client_ip',
        'user_agent',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy' => 'float',
            'attempted_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
