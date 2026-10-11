<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Submit Absensi Pegawai (Check-in / Check-out)
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'pin' => ['required', 'string', 'regex:/^\d{6}$/'],
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0|max:10000',
            'selfie' => 'required|file|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        try {
            $result = $this->attendanceService->processAttendance(
                employeeId: (int) $validated['employee_id'],
                pin: $validated['pin'],
                latitude: (float) $validated['latitude'],
                longitude: (float) $validated['longitude'],
                accuracy: (float) $validated['accuracy'],
                selfiePhoto: $request->file('selfie'),
                clientIp: $request->ip() ?: '127.0.0.1',
                userAgent: $request->header('User-Agent')
            );

            if (! $result['success']) {
                $status = match ($result['code'] ?? '') {
                    'PIN_LOCKED' => 429,
                    'INVALID_PIN', 'OUT_OF_GEOFENCE', 'SCHEDULE_MISSING', 'SCHEDULE_AMBIGUOUS', 'OUTSIDE_ATTENDANCE_WINDOW' => 422,
                    'ALREADY_COMPLETED', 'SESSION_CONFLICT' => 409,
                    default => 400,
                };

                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['code'] ?? 'ATTENDANCE_FAILED',
                    'data' => $result['attendance'] ?? null,
                ], $status);
            }

            $responseData = array_merge(
                ['action' => $result['action'] ?? 'check_in'],
                $result['attendance']->toArray()
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $responseData,
            ], 201);

        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => 'INVALID_INPUT',
            ], 422);
        } catch (\Throwable $exception) {
            Log::error('Pemrosesan presensi gagal.', [
                'exception_class' => $exception::class,
                'employee_id' => $request->input('employee_id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat memproses presensi.',
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }
}
