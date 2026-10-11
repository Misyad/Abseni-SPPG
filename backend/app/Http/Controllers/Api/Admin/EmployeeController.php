<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    private const AUDIT_FIELDS = ['division_id', 'employee_code', 'name', 'phone_number', 'is_active'];

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Employee::with('division')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'division_id' => 'required|exists:divisions,id',
            'employee_code' => 'required|string|max:50|unique:employees,employee_code',
            'name' => 'required|string|max:150',
            'pin' => ['required', 'string', 'regex:/^\d{6}$/'],
            'phone_number' => 'nullable|string|max:30',
            'is_active' => 'sometimes|boolean',
            'reason' => 'required|string|min:3|max:1000',
        ]);

        $employee = DB::transaction(function () use ($request, $validated) {
            $employee = Employee::create([
                'division_id' => $validated['division_id'],
                'employee_code' => $validated['employee_code'],
                'name' => $validated['name'],
                'pin_hash' => Hash::make($validated['pin']),
                'phone_number' => $validated['phone_number'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $this->audit($request, $employee, 'CREATE_EMPLOYEE', null, $employee->only(self::AUDIT_FIELDS), $validated['reason']);

            return $employee;
        });

        return response()->json([
            'success' => true,
            'message' => 'Pegawai berhasil ditambahkan',
            'data' => $employee->load('division'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'division_id' => 'required|exists:divisions,id',
            'employee_code' => 'required|string|max:50|unique:employees,employee_code,'.$id,
            'name' => 'required|string|max:150',
            'phone_number' => 'nullable|string|max:30',
            'is_active' => 'sometimes|boolean',
            'reason' => 'required|string|min:3|max:1000',
        ]);

        $employee = DB::transaction(function () use ($request, $id, $validated) {
            $employee = Employee::lockForUpdate()->findOrFail($id);
            $oldValues = $employee->only(self::AUDIT_FIELDS);
            $employee->update(collect($validated)->except('reason')->all());
            $this->audit($request, $employee, 'UPDATE_EMPLOYEE', $oldValues, $employee->fresh()->only(self::AUDIT_FIELDS), $validated['reason']);

            return $employee;
        });

        return response()->json([
            'success' => true,
            'message' => 'Data pegawai berhasil diperbarui',
            'data' => $employee->load('division'),
        ]);
    }

    public function resetPin(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'new_pin' => ['required', 'string', 'regex:/^\d{6}$/'],
            'reason' => 'required|string|min:3|max:1000',
        ]);

        DB::transaction(function () use ($request, $id, $validated) {
            $employee = Employee::lockForUpdate()->findOrFail($id);
            $oldValues = [
                'failed_pin_attempts' => $employee->failed_pin_attempts,
                'locked_until' => $employee->locked_until,
            ];

            $employee->update([
                'pin_hash' => Hash::make($validated['new_pin']),
                'failed_pin_attempts' => 0,
                'locked_until' => null,
            ]);

            $this->audit($request, $employee, 'RESET_PIN', $oldValues, [
                'pin_reset' => true,
                'failed_pin_attempts' => 0,
                'locked_until' => null,
            ], $validated['reason']);
        });

        return response()->json([
            'success' => true,
            'message' => 'PIN pegawai berhasil direset',
        ]);
    }

    private function audit(Request $request, Employee $employee, string $action, ?array $oldValues, ?array $newValues, string $reason): void
    {
        AuditLog::logAction(
            adminId: $request->user()->id,
            auditableType: Employee::class,
            auditableId: $employee->id,
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
            reason: $reason,
            ipAddress: $request->ip() ?: '127.0.0.1',
            userAgent: $request->header('User-Agent')
        );
    }
}
