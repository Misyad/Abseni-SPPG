<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceAttempt;
use App\Models\Employee;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AttendanceService
{
    public function __construct(
        private AttendanceWindowResolver $windowResolver
    ) {}

    /**
     * Memproses permohonan presensi pegawai secara atomik.
     */
    public function processAttendance(
        int $employeeId,
        string $pin,
        float $latitude,
        float $longitude,
        float $accuracy,
        UploadedFile $selfiePhoto,
        string $clientIp,
        ?string $userAgent = null
    ): array {
        $newPhotoPath = null;

        try {
            return DB::transaction(function () use (
                $employeeId,
                $pin,
                $latitude,
                $longitude,
                $accuracy,
                $selfiePhoto,
                $clientIp,
                $userAgent,
                &$newPhotoPath
            ) {
                $employee = Employee::whereKey($employeeId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $employee) {
                    return [
                        'success' => false,
                        'code' => 'INVALID_PIN',
                        'message' => 'Identitas atau PIN tidak valid.',
                    ];
                }

                $now = Carbon::now();
                if ($employee->locked_until && $now->isBefore($employee->locked_until)) {
                    return [
                        'success' => false,
                        'code' => 'PIN_LOCKED',
                        'message' => 'Verifikasi tidak dapat diproses sementara. Coba lagi nanti.',
                    ];
                }

                if (! $employee->verifyPin($pin)) {
                    $attempts = $employee->failed_pin_attempts + 1;
                    $employee->update([
                        'failed_pin_attempts' => $attempts,
                        'locked_until' => $attempts >= 5 ? $now->copy()->addMinutes(15) : null,
                    ]);

                    return [
                        'success' => false,
                        'code' => $attempts >= 5 ? 'PIN_LOCKED' : 'INVALID_PIN',
                        'message' => $attempts >= 5
                            ? 'Verifikasi tidak dapat diproses sementara. Coba lagi nanti.'
                            : 'Identitas atau PIN tidak valid.',
                    ];
                }

                if ($employee->failed_pin_attempts > 0 || $employee->locked_until) {
                    $employee->update(['failed_pin_attempts' => 0, 'locked_until' => null]);
                }

                $resolved = $this->windowResolver->resolve($employeeId, $now, true);
                if (isset($resolved['error_code'])) {
                    if ($resolved['error_code'] !== 'ALREADY_COMPLETED') {
                        AttendanceAttempt::create([
                            'employee_id' => $employeeId,
                            'reason_code' => $resolved['error_code'],
                            'message' => $resolved['message'],
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'accuracy' => $accuracy,
                            'client_ip' => $clientIp,
                            'user_agent' => $userAgent,
                            'attempted_at' => $now,
                        ]);
                    }

                    return [
                        'success' => false,
                        'code' => $resolved['error_code'],
                        'message' => $resolved['message'],
                    ];
                }

                $schedule = $resolved['schedule'];
                $shift = $schedule->shift;
                $workDate = $resolved['work_date'];
                $action = $resolved['action'];

                if ($action === 'check_out' && $now->isBefore($resolved['window_start'])) {
                    AttendanceAttempt::create([
                        'employee_id' => $employeeId,
                        'reason_code' => 'OUTSIDE_ATTENDANCE_WINDOW',
                        'message' => 'Saat ini berada di luar jendela check-in atau check-out yang diizinkan.',
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'accuracy' => $accuracy,
                        'client_ip' => $clientIp,
                        'user_agent' => $userAgent,
                        'attempted_at' => $now,
                    ]);

                    return [
                        'success' => false,
                        'code' => 'OUTSIDE_ATTENDANCE_WINDOW',
                        'message' => 'Saat ini berada di luar jendela check-in atau check-out yang diizinkan.',
                    ];
                }

                $location = Location::where('is_active', true)->orderBy('id')->first();

                if (! $location) {
                    throw new RuntimeException('Master lokasi SPPG belum dikonfigurasi.');
                }

                $distance = $location->calculateDistanceMeters($latitude, $longitude);
                $isMarginalGps = false;
                $flagReason = null;

                if ($distance > $location->radius_meters) {
                    if ($distance > ($location->radius_meters + 15)) {
                        return [
                            'success' => false,
                            'code' => 'OUT_OF_GEOFENCE',
                            'message' => 'Lokasi berada di luar radius presensi yang diizinkan.',
                        ];
                    }

                    $isMarginalGps = true;
                    $flagReason = "Lokasi marginal ({$distance}m dari pusat, radius maksimum {$location->radius_meters}m).";
                }

                $attendance = Attendance::where('employee_id', $employeeId)
                    ->where('work_date', $workDate)
                    ->where('shift_id', $shift->id)
                    ->lockForUpdate()
                    ->first();

                if ($action === 'check_out' && (! $attendance || $attendance->check_out_at)) {
                    return [
                        'success' => false,
                        'code' => $attendance?->check_out_at ? 'ALREADY_COMPLETED' : 'SESSION_CONFLICT',
                        'message' => $attendance?->check_out_at
                            ? 'Presensi untuk shift ini sudah lengkap.'
                            : 'Sesi check-in tidak ditemukan dan perlu diperiksa Administrator.',
                    ];
                }

                if ($action === 'check_in' && $attendance) {
                    return [
                        'success' => false,
                        'code' => $attendance->check_out_at ? 'ALREADY_COMPLETED' : 'SESSION_CONFLICT',
                        'message' => 'Sesi presensi untuk shift ini sudah tersedia.',
                    ];
                }

                $newPhotoPath = $selfiePhoto->store('selfies/'.$now->format('Y/m'), 'private');
                if (! $newPhotoPath) {
                    throw new RuntimeException('Berkas selfie gagal disimpan.');
                }

                if ($action === 'check_in') {
                    $lateTolerance = $resolved['shift_start']->copy()->addMinutes($shift->late_tolerance_minutes);
                    $lateMinutes = $now->isAfter($lateTolerance)
                        ? (int) floor($resolved['shift_start']->diffInMinutes($now))
                        : 0;
                    $status = $lateMinutes > 0 ? 'late' : 'on_time';

                    if ($isMarginalGps) {
                        $status = 'needs_review';
                    }

                    $attendance = Attendance::create([
                        'employee_id' => $employeeId,
                        'location_id' => $location->id,
                        'shift_id' => $shift->id,
                        'work_date' => $workDate,
                        'check_in_at' => $now,
                        'check_in_lat' => $latitude,
                        'check_in_lng' => $longitude,
                        'check_in_accuracy' => $accuracy,
                        'check_in_distance' => $distance,
                        'check_in_photo_path' => $newPhotoPath,
                        'status' => $status,
                        'late_minutes' => $lateMinutes,
                        'is_flagged' => $isMarginalGps,
                        'flag_reason' => $flagReason,
                        'client_ip' => $clientIp,
                        'user_agent' => $userAgent,
                    ]);
                } else {
                    $earlyLeaveMinutes = $now->isBefore($resolved['shift_end'])
                        ? (int) floor($now->diffInMinutes($resolved['shift_end']))
                        : 0;
                    $status = $attendance->status;

                    if ($earlyLeaveMinutes > 0 && $status === 'on_time') {
                        $status = 'early_leave';
                    }
                    if ($isMarginalGps) {
                        $status = 'needs_review';
                    }

                    $attendance->update([
                        'check_out_at' => $now,
                        'check_out_lat' => $latitude,
                        'check_out_lng' => $longitude,
                        'check_out_accuracy' => $accuracy,
                        'check_out_distance' => $distance,
                        'check_out_photo_path' => $newPhotoPath,
                        'status' => $status,
                        'early_leave_minutes' => $earlyLeaveMinutes,
                        'is_flagged' => $attendance->is_flagged || $isMarginalGps,
                        'flag_reason' => $flagReason ?: $attendance->flag_reason,
                    ]);
                }

                return [
                    'success' => true,
                    'action' => $action,
                    'message' => $action === 'check_in'
                        ? 'Presensi Masuk (Check-in) berhasil dicatat.'
                        : 'Presensi Pulang (Check-out) berhasil dicatat.',
                    'attendance' => $attendance->fresh(['employee', 'shift', 'location']),
                ];
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath && ! Storage::disk('private')->delete($newPhotoPath)) {
                Log::critical('Gagal membersihkan selfie setelah transaksi presensi rollback.', [
                    'path' => $newPhotoPath,
                    'employee_id' => $employeeId,
                    'exception' => $exception::class,
                ]);

                throw new RuntimeException('Transaksi gagal dan pembersihan berkas memerlukan intervensi.', previous: $exception);
            }

            throw $exception;
        }
    }
}
