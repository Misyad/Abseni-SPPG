<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAttendanceController extends Controller
{
    /**
     * Ringkasan Dashboard Metrik Kehadiran
     */
    public function dashboard(Request $request): JsonResponse
    {
        $date = $request->query('date', Carbon::now()->toDateString());

        $totalScheduled = DB::table('schedules')->where('schedule_date', $date)->count();
        $onTimeCount = Attendance::where('work_date', $date)->where('status', 'on_time')->count();
        $lateCount = Attendance::where('work_date', $date)->where('status', 'late')->count();
        $needsReviewCount = Attendance::where('work_date', $date)->where('is_flagged', true)->count();
        $attendanceAttemptCount = DB::table('attendance_attempts')
            ->whereDate('attempted_at', $date)
            ->whereIn('reason_code', ['SCHEDULE_MISSING', 'SCHEDULE_AMBIGUOUS', 'OUTSIDE_ATTENDANCE_WINDOW'])
            ->count();

        $recentAttendances = Attendance::with(['employee', 'shift', 'location'])
            ->where('work_date', $date)
            ->latest('updated_at')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'metrics' => [
                    'total_scheduled' => $totalScheduled,
                    'on_time' => $onTimeCount,
                    'late' => $lateCount,
                    'needs_review' => $needsReviewCount,
                    'attendance_attempts' => $attendanceAttemptCount,
                ],
                'recent' => $recentAttendances,
            ],
        ]);
    }

    /**
     * Daftar Riwayat Absensi Terfilter
     */
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with(['employee.division', 'shift', 'location', 'reviewer']);

        if ($request->has('work_date')) {
            $query->where('work_date', $request->query('work_date'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->boolean('is_flagged')) {
            $query->where('is_flagged', true);
        }

        if ($request->has('division_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('division_id', $request->query('division_id'));
            });
        }

        $attendances = $query->orderBy('work_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $attendances,
        ]);
    }

    /**
     * Detail Absensi Spesifik
     */
    public function show(int $id): JsonResponse
    {
        $attendance = Attendance::with(['employee.division', 'shift', 'location', 'reviewer'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $attendance,
        ]);
    }

    /**
     * Koreksi Manual Absensi dengan Log Audit
     */
    public function correct(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:on_time,late,early_leave,absent,corrected,needs_review',
            'check_in_at' => 'nullable|date',
            'check_out_at' => 'nullable|date',
            'reason' => 'required|string|min:5',
        ]);

        $attendance = DB::transaction(function () use ($request, $id, $validated) {
            $attendance = Attendance::lockForUpdate()->findOrFail($id);
            $oldValues = $attendance->toArray();

            $attendance->update([
                'status' => $validated['status'],
                'check_in_at' => $validated['check_in_at'] ?? $attendance->check_in_at,
                'check_out_at' => $validated['check_out_at'] ?? $attendance->check_out_at,
                'is_flagged' => false,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => Carbon::now(),
                'review_notes' => $validated['reason'],
            ]);

            AuditLog::logAction(
                adminId: $request->user()->id,
                auditableType: Attendance::class,
                auditableId: $attendance->id,
                action: 'CORRECT_ATTENDANCE',
                oldValues: $oldValues,
                newValues: $attendance->fresh()->toArray(),
                reason: $validated['reason'],
                ipAddress: $request->ip() ?: '127.0.0.1',
                userAgent: $request->header('User-Agent')
            );

            return $attendance;
        });

        return response()->json([
            'success' => true,
            'message' => 'Koreksi absensi berhasil disimpan.',
            'data' => $attendance->fresh(['employee', 'shift', 'location', 'reviewer']),
        ]);
    }

    /**
     * Memverifikasi / Resolusi Status Flagged (needs_review)
     */
    public function resolveFlag(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'target_status' => 'required|in:on_time,late,early_leave,absent,corrected',
            'notes' => 'required|string|min:3',
        ]);

        $attendance = DB::transaction(function () use ($request, $id, $validated) {
            $attendance = Attendance::lockForUpdate()->findOrFail($id);
            $oldValues = $attendance->toArray();

            $attendance->update([
                'status' => $validated['target_status'],
                'is_flagged' => false,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => Carbon::now(),
                'review_notes' => $validated['notes'],
            ]);

            AuditLog::logAction(
                adminId: $request->user()->id,
                auditableType: Attendance::class,
                auditableId: $attendance->id,
                action: 'RESOLVE_FLAG',
                oldValues: $oldValues,
                newValues: $attendance->fresh()->toArray(),
                reason: $validated['notes'],
                ipAddress: $request->ip() ?: '127.0.0.1',
                userAgent: $request->header('User-Agent')
            );

            return $attendance;
        });

        return response()->json([
            'success' => true,
            'message' => 'Status peninjauan absensi berhasil diperbarui.',
            'data' => $attendance->fresh(['employee', 'shift', 'location', 'reviewer']),
        ]);
    }
}
