<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAttempt;
use App\Models\Division;
use App\Models\Employee;
use App\Services\AttendanceWindowResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicEmployeeController extends Controller
{
    public function __construct(
        private AttendanceWindowResolver $windowResolver
    ) {}

    public function index(): JsonResponse
    {
        $divisions = Division::where('is_active', true)
            ->with(['employees' => function ($query) {
                $query->where('is_active', true)
                    ->select('id', 'division_id', 'employee_code', 'name');
            }])
            ->select('id', 'code', 'name', 'description')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar pegawai aktif berhasil diambil',
            'data' => $divisions,
        ]);
    }

    public function verifyPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'pin' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        return DB::transaction(function () use ($request, $validated) {
            $employee = Employee::whereKey($validated['employee_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            $now = Carbon::now();

            if (! $employee || ($employee->locked_until && $now->isBefore($employee->locked_until))) {
                return $this->invalidPinResponse($employee?->locked_until ? 429 : 422);
            }

            if (! $employee->verifyPin($validated['pin'])) {
                $attempts = $employee->failed_pin_attempts + 1;
                $employee->update([
                    'failed_pin_attempts' => $attempts,
                    'locked_until' => $attempts >= 5 ? $now->copy()->addMinutes(15) : null,
                ]);

                return $this->invalidPinResponse($attempts >= 5 ? 429 : 422);
            }

            if ($employee->failed_pin_attempts > 0 || $employee->locked_until) {
                $employee->update(['failed_pin_attempts' => 0, 'locked_until' => null]);
            }

            $resolved = $this->windowResolver->resolve($employee->id, $now, true);
            if (isset($resolved['error_code']) || ($resolved['action'] === 'check_out' && $now->isBefore($resolved['window_start']))) {
                $errorCode = $resolved['error_code'] ?? 'OUTSIDE_ATTENDANCE_WINDOW';
                $message = $resolved['message'] ?? 'Saat ini berada di luar jendela check-in atau check-out yang diizinkan.';

                if ($errorCode !== 'ALREADY_COMPLETED') {
                    AttendanceAttempt::create([
                        'employee_id' => $employee->id,
                        'reason_code' => $errorCode,
                        'message' => $message,
                        'client_ip' => $request->ip() ?: '127.0.0.1',
                        'user_agent' => $request->header('User-Agent'),
                        'attempted_at' => $now,
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'error_code' => $errorCode,
                ], $errorCode === 'ALREADY_COMPLETED' ? 409 : 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'PIN valid',
                'data' => [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'expected_action' => $resolved['action'],
                    'work_date' => $resolved['work_date'],
                    'shift' => $resolved['schedule']->shift,
                ],
            ]);
        });
    }

    private function invalidPinResponse(int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $status === 429
                ? 'Verifikasi tidak dapat diproses sementara. Coba lagi nanti.'
                : 'Identitas atau PIN tidak valid.',
            'error_code' => $status === 429 ? 'PIN_LOCKED' : 'INVALID_PIN',
        ], $status);
    }
}
