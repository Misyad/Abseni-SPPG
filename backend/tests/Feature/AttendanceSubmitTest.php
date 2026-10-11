<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceSubmitTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private Shift $dayShift;

    private Shift $nightShift;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $division = Division::create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
        $this->employee = Employee::create([
            'division_id' => $division->id,
            'employee_code' => 'SPPG-001',
            'name' => 'Pegawai Uji',
            'pin_hash' => bcrypt('123456'),
            'is_active' => true,
        ]);
        Location::create([
            'code' => 'LOC-1',
            'name' => 'SPPG Pakiskembar 2',
            'address' => 'Pakiskembar',
            'latitude' => -7.96662040,
            'longitude' => 112.63263210,
            'radius_meters' => 50,
            'is_active' => true,
        ]);
        $this->dayShift = $this->createShift('DAY', '07:00:00', '15:00:00', false);
        $this->nightShift = $this->createShift('NIGHT', '19:00:00', '03:00:00', true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_check_in_boundaries_and_fifteen_minute_tolerance(): void
    {
        $cases = [
            ['2026-10-10 05:59:59', 422, 'OUTSIDE_ATTENDANCE_WINDOW', null],
            ['2026-10-10 06:00:00', 201, null, 'on_time'],
            ['2026-10-10 07:15:00', 201, null, 'on_time'],
            ['2026-10-10 07:15:01', 201, null, 'late'],
            ['2026-10-10 11:00:00', 201, null, 'late'],
            ['2026-10-10 11:00:01', 422, 'OUTSIDE_ATTENDANCE_WINDOW', null],
        ];

        foreach ($cases as $index => [$time, $status, $errorCode, $attendanceStatus]) {
            $employee = $index === 0 ? $this->employee : $this->newEmployee($index);
            Schedule::create(['employee_id' => $employee->id, 'shift_id' => $this->dayShift->id, 'schedule_date' => '2026-10-10']);
            Carbon::setTestNow($time);
            $response = $this->submit($employee);
            $response->assertStatus($status);

            if ($errorCode) {
                $response->assertJsonPath('error_code', $errorCode);
            } else {
                $response->assertJsonPath('data.status', $attendanceStatus);
            }
        }
    }

    public function test_check_out_window_boundaries_are_enforced(): void
    {
        Schedule::create(['employee_id' => $this->employee->id, 'shift_id' => $this->dayShift->id, 'schedule_date' => '2026-10-10']);
        Carbon::setTestNow('2026-10-10 07:00:00');
        $this->submit()->assertCreated();

        Carbon::setTestNow('2026-10-10 14:29:59');
        $this->submit()->assertUnprocessable()->assertJsonPath('error_code', 'OUTSIDE_ATTENDANCE_WINDOW');
        Carbon::setTestNow('2026-10-10 14:30:00');
        $this->submit()->assertCreated()->assertJsonPath('data.action', 'check_out');

        $attendance = Attendance::firstOrFail();
        $attendance->update(['check_out_at' => null, 'check_out_photo_path' => null]);
        Carbon::setTestNow('2026-10-10 19:00:00');
        $this->submit()->assertCreated()->assertJsonPath('data.action', 'check_out');

        $attendance->update(['check_out_at' => null, 'check_out_photo_path' => null]);
        Carbon::setTestNow('2026-10-10 19:00:01');
        $this->submit()->assertUnprocessable()->assertJsonPath('error_code', 'OUTSIDE_ATTENDANCE_WINDOW');
    }

    public function test_overnight_shift_keeps_start_date_across_jakarta_midnight(): void
    {
        Schedule::create(['employee_id' => $this->employee->id, 'shift_id' => $this->nightShift->id, 'schedule_date' => '2026-10-10']);

        Carbon::setTestNow(Carbon::parse('2026-10-10 19:03:00', 'Asia/Jakarta'));
        $this->submit()->assertCreated()->assertJsonPath('data.work_date', '2026-10-10');

        Carbon::setTestNow(Carbon::parse('2026-10-11 03:02:00', 'Asia/Jakarta'));
        $this->submit()->assertCreated()
            ->assertJsonPath('data.action', 'check_out')
            ->assertJsonPath('data.work_date', '2026-10-10');
    }

    public function test_missing_schedule_records_attempt_without_fake_attendance_or_selfie(): void
    {
        Carbon::setTestNow('2026-10-10 07:00:00');
        $this->submit()->assertUnprocessable()->assertJsonPath('error_code', 'SCHEDULE_MISSING');

        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseHas('attendance_attempts', [
            'employee_id' => $this->employee->id,
            'reason_code' => 'SCHEDULE_MISSING',
        ]);
        Storage::disk('private')->assertDirectoryEmpty('selfies');
    }

    public function test_open_attendance_is_resolved_before_overlapping_shift_check_in(): void
    {
        Schedule::create(['employee_id' => $this->employee->id, 'shift_id' => $this->nightShift->id, 'schedule_date' => '2026-10-09']);
        Carbon::setTestNow('2026-10-09 19:00:00');
        $this->submit()->assertCreated();

        Schedule::create(['employee_id' => $this->employee->id, 'shift_id' => $this->dayShift->id, 'schedule_date' => '2026-10-10']);
        Carbon::setTestNow('2026-10-10 06:30:00');
        $this->submit()->assertCreated()
            ->assertJsonPath('data.action', 'check_out')
            ->assertJsonPath('data.work_date', '2026-10-09');

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_completed_retry_does_not_create_duplicate_or_orphan_selfie(): void
    {
        Schedule::create(['employee_id' => $this->employee->id, 'shift_id' => $this->dayShift->id, 'schedule_date' => '2026-10-10']);
        Carbon::setTestNow('2026-10-10 07:00:00');
        $this->submit()->assertCreated();
        Carbon::setTestNow('2026-10-10 15:00:00');
        $this->submit()->assertCreated();
        $filesBeforeRetry = Storage::disk('private')->allFiles('selfies');

        $this->submit()->assertStatus(409)->assertJsonPath('error_code', 'ALREADY_COMPLETED');

        $this->assertDatabaseCount('attendances', 1);
        $this->assertSame($filesBeforeRetry, Storage::disk('private')->allFiles('selfies'));
    }

    private function createShift(string $code, string $start, string $end, bool $crossDay): Shift
    {
        return Shift::create([
            'code' => $code,
            'name' => $code,
            'start_time' => $start,
            'end_time' => $end,
            'check_in_window_start_minutes' => 60,
            'check_in_window_end_minutes' => 240,
            'late_tolerance_minutes' => 15,
            'check_out_window_start_minutes' => 30,
            'check_out_window_end_minutes' => 240,
            'is_cross_day' => $crossDay,
            'is_active' => true,
        ]);
    }

    private function newEmployee(int $index): Employee
    {
        return Employee::create([
            'division_id' => $this->employee->division_id,
            'employee_code' => 'SPPG-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Pegawai Uji '.$index,
            'pin_hash' => bcrypt('123456'),
            'is_active' => true,
        ]);
    }

    private function submit(?Employee $employee = null)
    {
        return $this->postJson('/api/v1/public/attendances/submit', [
            'employee_id' => ($employee ?? $this->employee)->id,
            'pin' => '123456',
            'latitude' => -7.96662040,
            'longitude' => 112.63263210,
            'accuracy' => 5,
            'selfie' => UploadedFile::fake()->image(uniqid('selfie-', true).'.jpg'),
        ]);
    }
}
