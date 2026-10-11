<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';

    protected $fillable = [
        'employee_id',
        'location_id',
        'shift_id',
        'work_date',
        'check_in_at',
        'check_in_lat',
        'check_in_lng',
        'check_in_accuracy',
        'check_in_distance',
        'check_in_photo_path',
        'check_out_at',
        'check_out_lat',
        'check_out_lng',
        'check_out_accuracy',
        'check_out_distance',
        'check_out_photo_path',
        'status',
        'late_minutes',
        'early_leave_minutes',
        'is_flagged',
        'flag_reason',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'client_ip',
        'user_agent',
    ];

    protected $hidden = [
        'check_in_photo_path',
        'check_out_photo_path',
    ];

    protected $appends = [
        'has_check_in_selfie',
        'has_check_out_selfie',
    ];

    public function getHasCheckInSelfieAttribute(): bool
    {
        return filled($this->check_in_photo_path);
    }

    public function getHasCheckOutSelfieAttribute(): bool
    {
        return filled($this->check_out_photo_path);
    }

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'check_in_lat' => 'float',
            'check_in_lng' => 'float',
            'check_in_accuracy' => 'float',
            'check_in_distance' => 'float',
            'check_out_lat' => 'float',
            'check_out_lng' => 'float',
            'check_out_accuracy' => 'float',
            'check_out_distance' => 'float',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'is_flagged' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
