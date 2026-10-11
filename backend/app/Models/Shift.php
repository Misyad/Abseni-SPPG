<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $table = 'shifts';

    protected $fillable = [
        'code',
        'name',
        'start_time',
        'end_time',
        'check_in_window_start_minutes',
        'check_in_window_end_minutes',
        'late_tolerance_minutes',
        'check_out_window_start_minutes',
        'check_out_window_end_minutes',
        'is_cross_day',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'check_in_window_start_minutes' => 'integer',
            'check_in_window_end_minutes' => 'integer',
            'late_tolerance_minutes' => 'integer',
            'check_out_window_start_minutes' => 'integer',
            'check_out_window_end_minutes' => 'integer',
            'is_cross_day' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
