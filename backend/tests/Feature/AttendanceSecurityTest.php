<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AttendanceSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 07:00:00');
        $division = Division::create(['code' => 'OPS', 'name' => 'Ops', 'is_active' => true]);
        $this->employee = Employee::create([
            'division_id' => $division->id,
            'employee_code' => 'EMP-1',
            'name' => 'Pegawai',
            'pin_hash' => bcrypt('123456'),
            'is_active' => true,
        ]);
        Location::create([
            'code' => 'SEC-LOC',
            'name' => 'Lokasi Uji Keamanan',
            'address' => 'Pakiskembar',
            'latitude' => -7.96662040,
            'longitude' => 112.63263210,
            'radius_meters' => 50,
            'is_active' => true,
        ]);
        $this->shift = Shift::create([
            'code' => 'DAY', 'name' => 'Day', 'start_time' => '07:00:00', 'end_time' => '15:00:00',
            'check_in_window_start_minutes' => 60, 'check_in_window_end_minutes' => 240,
            'late_tolerance_minutes' => 15, 'check_out_window_start_minutes' => 30,
            'check_out_window_end_minutes' => 240, 'is_cross_day' => false, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pin_precheck_rejects_missing_schedule_before_camera_flow(): void
    {
        $this->postJson('/api/v1/public/employees/verify-pin', [
            'employee_id' => $this->employee->id,
            'pin' => '123456',
        ])->assertUnprocessable()->assertJsonPath('error_code', 'SCHEDULE_MISSING');

        $this->assertDatabaseHas('attendance_attempts', [
            'employee_id' => $this->employee->id,
            'reason_code' => 'SCHEDULE_MISSING',
        ]);
    }

    public function test_pin_precheck_returns_expected_action_for_valid_window(): void
    {
        Schedule::create(['employee_id' => $this->employee->id, 'shift_id' => $this->shift->id, 'schedule_date' => '2026-10-10']);

        $this->postJson('/api/v1/public/employees/verify-pin', [
            'employee_id' => $this->employee->id,
            'pin' => '123456',
        ])->assertOk()
            ->assertJsonPath('data.expected_action', 'check_in')
            ->assertJsonPath('data.work_date', '2026-10-10');
    }

    public function test_pin_endpoint_is_rate_limited_by_employee_and_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->postJson('/api/v1/public/employees/verify-pin', [
                    'employee_id' => $this->employee->id,
                    'pin' => '000000',
                ]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/v1/public/employees/verify-pin', [
                'employee_id' => $this->employee->id,
                'pin' => '000000',
            ])->assertTooManyRequests();
    }

    public function test_pin_must_be_exactly_six_numeric_digits_at_every_write_boundary(): void
    {
        $invalidPins = ['12345', '1234567', 'abcdef'];

        foreach ($invalidPins as $invalidPin) {
            $this->postJson('/api/v1/public/employees/verify-pin', [
                'employee_id' => $this->employee->id,
                'pin' => $invalidPin,
            ])->assertUnprocessable()->assertJsonValidationErrors('pin');

            $this->postJson('/api/v1/public/attendances/submit', [
                'employee_id' => $this->employee->id,
                'pin' => $invalidPin,
                'latitude' => -7.96662040,
                'longitude' => 112.63263210,
                'accuracy' => 5,
                'selfie' => UploadedFile::fake()->image('invalid-pin.jpg'),
            ])->assertUnprocessable()->assertJsonValidationErrors('pin');
        }

        $superadmin = Admin::create(['name' => 'Superadmin PIN', 'email' => 'pin@example.test', 'password' => 'secret', 'role' => 'superadmin', 'is_active' => true]);
        Sanctum::actingAs($superadmin);

        foreach ($invalidPins as $index => $invalidPin) {
            $this->postJson('/api/v1/admin/employees', [
                'division_id' => $this->employee->division_id,
                'employee_code' => 'INVALID-PIN-'.$index,
                'name' => 'Pegawai PIN Tidak Valid',
                'pin' => $invalidPin,
                'reason' => 'Uji kebijakan PIN',
            ])->assertUnprocessable()->assertJsonValidationErrors('pin');

            $this->postJson('/api/v1/admin/employees/'.$this->employee->id.'/reset-pin', [
                'new_pin' => $invalidPin,
                'reason' => 'Uji kebijakan PIN',
            ])->assertUnprocessable()->assertJsonValidationErrors('new_pin');
        }
    }

    public function test_admin_routes_require_authentication_and_role(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();

        $operator = Admin::create(['name' => 'Operator', 'email' => 'operator@example.test', 'password' => 'secret', 'role' => 'operator', 'is_active' => true]);
        Sanctum::actingAs($operator);
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->postJson('/api/v1/admin/attendances/1/correct', [
            'status' => 'on_time', 'reason' => 'Tidak berwenang',
        ])->assertForbidden();
        $this->getJson('/api/v1/admin/employees')->assertForbidden();
    }

    public function test_supervisor_can_manage_schedule_but_not_reset_pin(): void
    {
        $supervisor = Admin::create(['name' => 'Supervisor', 'email' => 'supervisor@example.test', 'password' => 'secret', 'role' => 'supervisor', 'is_active' => true]);
        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/admin/schedules', [
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'schedule_date' => '2026-10-10',
            'reason' => 'Penugasan operasional',
        ])->assertCreated();
        $this->assertDatabaseHas('audit_logs', ['action' => 'CREATE_SCHEDULE']);

        $this->postJson('/api/v1/admin/employees/'.$this->employee->id.'/reset-pin', [
            'new_pin' => '654321', 'reason' => 'Tidak berwenang',
        ])->assertForbidden();
    }

    public function test_superadmin_reset_pin_is_audited_without_pin_or_hash(): void
    {
        $superadmin = Admin::create(['name' => 'Superadmin', 'email' => 'root@example.test', 'password' => 'secret', 'role' => 'superadmin', 'is_active' => true]);
        Sanctum::actingAs($superadmin);

        $response = $this->postJson('/api/v1/admin/employees/'.$this->employee->id.'/reset-pin', [
            'new_pin' => '654321',
            'reason' => 'Permintaan pegawai',
        ])->assertOk();

        $this->assertStringNotContainsString('654321', $response->getContent());
        $response->assertJsonMissingPath('data.pin_hash');
        $audit = AuditLog::where('action', 'RESET_PIN')->firstOrFail();
        $encoded = json_encode([$audit->old_values, $audit->new_values], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('654321', $encoded);
        $this->assertStringNotContainsString('pin_hash', $encoded);
    }

    public function test_private_selfie_proxy_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/media/selfie/999/check_in')->assertUnauthorized();
    }

    public function test_operator_cannot_access_private_selfie_but_supervisor_and_superadmin_can(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('selfies/rbac.jpg', 'private-image');
        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'location_id' => Location::firstOrFail()->id,
            'shift_id' => $this->shift->id,
            'work_date' => '2026-10-10',
            'check_in_at' => Carbon::now(),
            'check_in_photo_path' => 'selfies/rbac.jpg',
            'status' => 'on_time',
        ]);

        $operator = Admin::create(['name' => 'Operator Media', 'email' => 'operator-media@example.test', 'password' => 'secret', 'role' => 'operator', 'is_active' => true]);
        Sanctum::actingAs($operator);
        $this->get('/api/v1/admin/media/selfie/'.$attendance->id.'/check_in')->assertForbidden();

        foreach (['supervisor', 'superadmin'] as $role) {
            $admin = Admin::create([
                'name' => ucfirst($role).' Media',
                'email' => $role.'-media@example.test',
                'password' => 'secret',
                'role' => $role,
                'is_active' => true,
            ]);
            Sanctum::actingAs($admin);
            $response = $this->get('/api/v1/admin/media/selfie/'.$attendance->id.'/check_in')
                ->assertOk();
            $cacheControl = $response->headers->get('Cache-Control', '');
            $this->assertStringContainsString('private', $cacheControl);
            $this->assertStringContainsString('no-store', $cacheControl);
            $this->assertStringContainsString('max-age=0', $cacheControl);
        }
    }

    public function test_selfie_is_deleted_when_database_write_fails_after_storage(): void
    {
        Storage::fake('private');
        Log::spy();
        Schedule::create(['employee_id' => $this->employee->id, 'shift_id' => $this->shift->id, 'schedule_date' => '2026-10-10']);
        Attendance::creating(function (): never {
            throw new RuntimeException('Forced attendance failure');
        });

        try {
            $response = $this->postJson('/api/v1/public/attendances/submit', [
                'employee_id' => $this->employee->id,
                'pin' => '123456',
                'latitude' => -7.96662040,
                'longitude' => 112.63263210,
                'accuracy' => 5,
                'selfie' => UploadedFile::fake()->image('rollback.jpg'),
            ]);
        } finally {
            Attendance::flushEventListeners();
        }

        $response->assertInternalServerError()->assertJsonPath('error_code', 'SERVER_ERROR');
        $this->assertDatabaseCount('attendances', 0);
        Storage::disk('private')->assertDirectoryEmpty('selfies');
        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                'Pemrosesan presensi gagal.',
                Mockery::on(function (array $context): bool {
                    $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                    return array_keys($context) === ['exception_class', 'employee_id']
                        && $context['exception_class'] === RuntimeException::class
                        && $context['employee_id'] === $this->employee->id
                        && ! str_contains($encoded, '123456')
                        && ! preg_match('/pin|password|token|secret/i', $encoded);
                })
            );
    }

    public function test_private_selfie_path_is_not_exposed_by_attendance_response(): void
    {
        Storage::fake('private');
        Schedule::create(['employee_id' => $this->employee->id, 'shift_id' => $this->shift->id, 'schedule_date' => '2026-10-10']);

        $this->postJson('/api/v1/public/attendances/submit', [
            'employee_id' => $this->employee->id,
            'pin' => '123456',
            'latitude' => -7.96662040,
            'longitude' => 112.63263210,
            'accuracy' => 5,
            'selfie' => UploadedFile::fake()->image('private.jpg'),
        ])->assertCreated()
            ->assertJsonPath('data.has_check_in_selfie', true)
            ->assertJsonMissingPath('data.check_in_photo_path')
            ->assertJsonMissingPath('data.check_out_photo_path');
    }

    public function test_failed_audit_write_rolls_back_employee_mutation(): void
    {
        $superadmin = Admin::create(['name' => 'Superadmin', 'email' => 'rollback@example.test', 'password' => 'secret', 'role' => 'superadmin', 'is_active' => true]);
        Sanctum::actingAs($superadmin);
        $originalName = $this->employee->name;

        AuditLog::creating(function (): never {
            throw new RuntimeException('Forced audit failure');
        });

        $this->withoutExceptionHandling();
        try {
            $this->putJson('/api/v1/admin/employees/'.$this->employee->id, [
                'division_id' => $this->employee->division_id,
                'employee_code' => $this->employee->employee_code,
                'name' => 'Perubahan Harus Rollback',
                'is_active' => true,
                'reason' => 'Uji rollback audit',
            ]);
            $this->fail('Forced audit failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced audit failure', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }

        $this->assertSame($originalName, $this->employee->fresh()->name);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'UPDATE_EMPLOYEE']);
    }
}
