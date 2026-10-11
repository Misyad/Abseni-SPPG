<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Schedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AttendanceWindowResolver
{
    /**
     * @return array{schedule: Schedule, work_date: string, action: string, shift_start: CarbonInterface, shift_end: CarbonInterface, window_start: CarbonInterface, window_end: CarbonInterface}|array{error_code: string, message: string}
     */
    public function resolve(int $employeeId, CarbonInterface $now, bool $lockForUpdate = false): array
    {
        $query = Schedule::with('shift')
            ->where('employee_id', $employeeId)
            ->whereBetween('schedule_date', [
                $now->copy()->subDay()->toDateString(),
                $now->copy()->addDay()->toDateString(),
            ]);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $schedules = $query->get()->filter(fn (Schedule $schedule) => $schedule->shift?->is_active);
        $attendances = Attendance::where('employee_id', $employeeId)
            ->whereBetween('work_date', [
                $now->copy()->subDay()->toDateString(),
                $now->copy()->addDay()->toDateString(),
            ])
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->get()
            ->keyBy(fn (Attendance $attendance) => $attendance->work_date->format('Y-m-d').'|'.$attendance->shift_id);
        $hasOpenAttendance = $attendances->contains(fn (Attendance $attendance) => ! $attendance->check_out_at);

        $candidates = $schedules->map(function (Schedule $schedule) use ($now, $attendances, $hasOpenAttendance) {
            $shift = $schedule->shift;
            $workDate = $schedule->schedule_date->format('Y-m-d');
            $shiftStart = $now->copy()->setDateFrom($schedule->schedule_date)->setTimeFromTimeString($shift->start_time);
            $shiftEnd = $now->copy()->setDateFrom($schedule->schedule_date)->setTimeFromTimeString($shift->end_time);

            if ($shift->is_cross_day) {
                $shiftEnd->addDay();
            }

            $attendance = $attendances->get($workDate.'|'.$shift->id);
            if ($hasOpenAttendance && (! $attendance || $attendance->check_out_at)) {
                return null;
            }

            if ($attendance?->check_out_at) {
                $windowStart = $shiftStart->copy()->subMinutes($shift->check_in_window_start_minutes);
                $windowEnd = $shiftEnd->copy()->addMinutes($shift->check_out_window_end_minutes);
                $action = 'completed';
            } elseif ($attendance) {
                $windowStart = $shiftEnd->copy()->subMinutes($shift->check_out_window_start_minutes);
                $windowEnd = $shiftEnd->copy()->addMinutes($shift->check_out_window_end_minutes);
                $action = 'check_out';
            } else {
                $windowStart = $shiftStart->copy()->subMinutes($shift->check_in_window_start_minutes);
                $windowEnd = $shiftStart->copy()->addMinutes($shift->check_in_window_end_minutes);
                $action = 'check_in';
            }

            $isCandidate = $action === 'check_out'
                ? $now->lessThanOrEqualTo($windowEnd)
                : $now->betweenIncluded($windowStart, $windowEnd);

            return $isCandidate ? [
                'schedule' => $schedule,
                'work_date' => $workDate,
                'action' => $action,
                'shift_start' => $shiftStart,
                'shift_end' => $shiftEnd,
                'window_start' => $windowStart,
                'window_end' => $windowEnd,
            ] : null;
        })->filter()->values();

        $actionableCandidates = $candidates->where('action', '!=', 'completed')->values();
        if ($actionableCandidates->isNotEmpty()) {
            $candidates = $actionableCandidates;
        }

        return $this->singleCandidate($candidates, $schedules->isNotEmpty());
    }

    /**
     * @param  Collection<int, array{schedule: Schedule, work_date: string, action: string, shift_start: CarbonInterface, shift_end: CarbonInterface, window_start: CarbonInterface, window_end: CarbonInterface}>  $candidates
     */
    private function singleCandidate(Collection $candidates, bool $hasSchedules): array
    {
        if ($candidates->isEmpty()) {
            if (! $hasSchedules) {
                return [
                    'error_code' => 'SCHEDULE_MISSING',
                    'message' => 'Tidak ada jadwal kerja aktif. Silakan hubungi Administrator.',
                ];
            }

            return [
                'error_code' => 'OUTSIDE_ATTENDANCE_WINDOW',
                'message' => 'Saat ini berada di luar jendela check-in atau check-out yang diizinkan.',
            ];
        }

        if ($candidates->count() > 1) {
            return [
                'error_code' => 'SCHEDULE_AMBIGUOUS',
                'message' => 'Jadwal presensi ambigu dan perlu diperiksa Administrator.',
            ];
        }

        if ($candidates->first()['action'] === 'completed') {
            return [
                'error_code' => 'ALREADY_COMPLETED',
                'message' => 'Presensi untuk shift ini sudah lengkap.',
            ];
        }

        return $candidates->first();
    }
}
