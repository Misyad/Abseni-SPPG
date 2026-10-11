<?php

use App\Http\Controllers\Api\Admin\AdminAttendanceController;
use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\DivisionController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\LocationController;
use App\Http\Controllers\Api\Admin\MediaProxyController;
use App\Http\Controllers\Api\Admin\ScheduleController;
use App\Http\Controllers\Api\Admin\ShiftController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\PublicEmployeeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('public')->group(function () {
        Route::get('/employees', [PublicEmployeeController::class, 'index']);
        Route::post('/employees/verify-pin', [PublicEmployeeController::class, 'verifyPin'])
            ->middleware('throttle:employee-pin');
        Route::post('/attendances/submit', [AttendanceController::class, 'submit'])
            ->middleware('throttle:attendance-submit');
    });

    Route::prefix('admin')->group(function () {
        Route::post('/auth/login', [AdminAuthController::class, 'login'])
            ->middleware('throttle:admin-login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/auth/me', [AdminAuthController::class, 'me']);
            Route::post('/auth/logout', [AdminAuthController::class, 'logout']);

            Route::middleware('admin.role:operator,supervisor,superadmin')->group(function () {
                Route::get('/dashboard', [AdminAttendanceController::class, 'dashboard']);
                Route::get('/attendances', [AdminAttendanceController::class, 'index']);
                Route::get('/attendances/{id}', [AdminAttendanceController::class, 'show']);
            });

            Route::middleware('admin.role:supervisor,superadmin')->group(function () {
                Route::get('/media/selfie/{id}/{type}', [MediaProxyController::class, 'viewSelfie'])
                    ->whereIn('type', ['check_in', 'check_out']);
                Route::post('/attendances/{id}/correct', [AdminAttendanceController::class, 'correct']);
                Route::post('/attendances/{id}/resolve-flag', [AdminAttendanceController::class, 'resolveFlag']);
                Route::apiResource('/schedules', ScheduleController::class)->only(['index', 'store']);
            });

            Route::middleware('admin.role:superadmin')->group(function () {
                Route::apiResource('/divisions', DivisionController::class)->only(['index', 'store', 'update', 'destroy']);
                Route::apiResource('/employees', EmployeeController::class)->only(['index', 'store', 'update']);
                Route::post('/employees/{id}/reset-pin', [EmployeeController::class, 'resetPin']);
                Route::apiResource('/locations', LocationController::class)->only(['index', 'update']);
                Route::apiResource('/shifts', ShiftController::class)->only(['index', 'store', 'update']);
                Route::get('/audit-logs', [AuditLogController::class, 'index']);
            });
        });
    });
});
