<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Shift::all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, 'unique:shifts,code');
        $shift = DB::transaction(function () use ($request, $validated) {
            $shift = Shift::create(collect($validated)->except('reason')->all());
            $this->audit($request, $shift, 'CREATE_SHIFT', null, $shift->toArray(), $validated['reason']);

            return $shift;
        });

        return response()->json(['success' => true, 'message' => 'Master shift berhasil dibuat', 'data' => $shift], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validatePayload($request, 'unique:shifts,code,'.$id);
        $shift = DB::transaction(function () use ($request, $id, $validated) {
            $shift = Shift::lockForUpdate()->findOrFail($id);
            $oldValues = $shift->toArray();
            $shift->update(collect($validated)->except('reason')->all());
            $this->audit($request, $shift, 'UPDATE_SHIFT', $oldValues, $shift->fresh()->toArray(), $validated['reason']);

            return $shift;
        });

        return response()->json(['success' => true, 'message' => 'Master shift berhasil diperbarui', 'data' => $shift]);
    }

    private function validatePayload(Request $request, string $codeRule): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', $codeRule],
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s|different:start_time',
            'check_in_window_start_minutes' => 'sometimes|integer|min:0|max:1440',
            'check_in_window_end_minutes' => 'sometimes|integer|min:0|max:1440',
            'late_tolerance_minutes' => 'sometimes|integer|min:0|max:1440',
            'check_out_window_start_minutes' => 'sometimes|integer|min:0|max:1440',
            'check_out_window_end_minutes' => 'sometimes|integer|min:0|max:1440',
            'is_cross_day' => 'required|boolean',
            'is_active' => 'sometimes|boolean',
            'reason' => 'required|string|min:3|max:1000',
        ]);

        $mustCrossDay = $validated['end_time'] < $validated['start_time'];
        if ((bool) $validated['is_cross_day'] !== $mustCrossDay) {
            throw ValidationException::withMessages([
                'is_cross_day' => 'Nilai is_cross_day harus konsisten dengan start_time dan end_time.',
            ]);
        }

        return $validated;
    }

    private function audit(Request $request, Shift $shift, string $action, ?array $oldValues, ?array $newValues, string $reason): void
    {
        AuditLog::logAction($request->user()->id, Shift::class, $shift->id, $action, $oldValues, $newValues, $reason, $request->ip() ?: '127.0.0.1', $request->header('User-Agent'));
    }
}
