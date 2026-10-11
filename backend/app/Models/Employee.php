<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'employees';

    protected $fillable = [
        'division_id',
        'employee_code',
        'name',
        'pin_hash',
        'phone_number',
        'is_active',
        'failed_pin_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'failed_pin_attempts' => 'integer',
            'locked_until' => 'datetime',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function verifyPin(string $pin): bool
    {
        return Hash::check($pin, $this->pin_hash);
    }

    public function setPinAttribute(string $pin): void
    {
        $this->attributes['pin_hash'] = Hash::make($pin);
    }
}
