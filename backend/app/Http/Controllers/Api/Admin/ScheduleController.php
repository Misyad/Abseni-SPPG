<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Schedule::with(['employee.division', 'shift']);

        if ($request->filled('schedule_date')) {
            $query->where('schedule_date', $request->query('schedule_date'));
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('schedule_date')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'shift_id' => 'required|integer|exists:shifts,id',
            'schedule_date' => 'required|date_format:Y-m-d',
            'notes' => 'nullable|string|max:255',
            'reason' => 'required|string|min:3|max:1000',
        ]);

        [$schedule, $created] = DB::transaction(function () use ($request, $validated) {
            Employee::whereKey($validated['employee_id'])->lockForUpdate()->firstOrFail();
            $schedule = Schedule::where('employee_id', $validated['employee_id'])
                ->where('schedule_date', $validated['schedule_date'])
                ->lockForUpdate()
                ->first();
            $oldValues = $schedule?->toArray();
            $created = ! $schedule;

            $schedule ??= new Schedule([
                'employee_id' => $validated['employee_id'],
                'schedule_date' => $validated['schedule_date'],
            ]);
            $schedule->fill([
                'shift_id' => $validated['shift_id'],
                'notes' => $validated['notes'] ?? null,
            ])->save();

            AuditLog::logAction(
                $request->user()->id,
                Schedule::class,
                $schedule->id,
                $created ? 'CREATE_SCHEDULE' : 'UPDATE_SCHEDULE',
                $oldValues,
                $schedule->fresh()->toArray(),
                $validated['reason'],
                $request->ip() ?: '127.0.0.1',
                $request->header('User-Agent')
            );

            return [$schedule, $created];
        });

        return response()->json([
            'success' => true,
            'message' => 'Penugasan jadwal berhasil disimpan',
            'data' => $schedule->load(['employee', 'shift']),
        ], $created ? 201 : 200);
    }
}
