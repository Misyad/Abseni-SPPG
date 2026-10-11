<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_concurrent_check_in_creates_only_one_attendance_and_selfie(): void
    {
        $this->assertSame('mysql', config('database.default'), 'Tes concurrency wajib memakai koneksi MySQL.');
        $this->assertTrue(function_exists('pcntl_fork'), 'Tes concurrency wajib memakai ekstensi pcntl.');

        $version = (string) DB::selectOne('SELECT VERSION() AS version')->version;
        $this->assertMatchesRegularExpression('/^8\.0\./', $version, 'Tes concurrency wajib memakai MySQL 8.0.');

        $table = DB::selectOne(
            "SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances'"
        );
        $this->assertNotNull($table, 'Tabel attendances wajib tersedia sebelum tes concurrency.');
        $this->assertSame('InnoDB', $table->engine, 'Tabel attendances wajib memakai InnoDB.');

        Storage::disk('private')->deleteDirectory('selfies');
        Carbon::setTestNow('2026-10-10 07:00:00');

        $division = Division::create(['code' => 'CONC', 'name' => 'Concurrency', 'is_active' => true]);
        $employee = Employee::create([
            'division_id' => $division->id,
            'employee_code' => 'CONC-001',
            'name' => 'Pegawai Concurrent',
            'pin_hash' => bcrypt('123456'),
            'is_active' => true,
        ]);
        Location::create([
            'code' => 'CONC-LOC',
            'name' => 'Lokasi Concurrent',
            'address' => 'Pakiskembar',
            'latitude' => -7.96662040,
            'longitude' => 112.63263210,
            'radius_meters' => 50,
            'is_active' => true,
        ]);
        $shift = Shift::create([
            'code' => 'CONC-SHIFT',
            'name' => 'Shift Concurrent',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'check_in_window_start_minutes' => 60,
            'check_in_window_end_minutes' => 240,
            'late_tolerance_minutes' => 15,
            'check_out_window_start_minutes' => 30,
            'check_out_window_end_minutes' => 240,
            'is_cross_day' => false,
            'is_active' => true,
        ]);
        Schedule::create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'schedule_date' => '2026-10-10']);

        $barrier = storage_path('framework/testing/concurrency-'.uniqid('', true));
        mkdir($barrier, 0777, true);
        $children = [];

        for ($index = 0; $index < 2; $index++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('Gagal membuat proses uji concurrency.');
            }

            if ($pid === 0) {
                try {
                    DB::purge();
                    file_put_contents($barrier.'/ready-'.$index, '1');
                    while (! file_exists($barrier.'/go')) {
                        usleep(1000);
                    }

                    $response = $this->postJson('/api/v1/public/attendances/submit', [
                        'employee_id' => $employee->id,
                        'pin' => '123456',
                        'latitude' => -7.96662040,
                        'longitude' => 112.63263210,
                        'accuracy' => 5,
                        'selfie' => UploadedFile::fake()->image('concurrent-'.$index.'.jpg'),
                    ]);
                    file_put_contents($barrier.'/status-'.$index, (string) $response->getStatusCode());
                    exit(0);
                } catch (\Throwable $exception) {
                    file_put_contents($barrier.'/error-'.$index, (string) $exception);
                    exit(1);
                }
            }

            $children[] = $pid;
        }

        $deadline = microtime(true) + 10;
        while ((! file_exists($barrier.'/ready-0') || ! file_exists($barrier.'/ready-1')) && microtime(true) < $deadline) {
            usleep(1000);
        }
        $bothChildrenReady = file_exists($barrier.'/ready-0') && file_exists($barrier.'/ready-1');
        file_put_contents($barrier.'/go', '1');

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        $this->assertTrue($bothChildrenReady, 'Kedua proses anak wajib mencapai barrier concurrency.');

        DB::purge();
        $statuses = [
            (int) file_get_contents($barrier.'/status-0'),
            (int) file_get_contents($barrier.'/status-1'),
        ];
        sort($statuses);

        $this->assertSame([201, 422], $statuses);
        $this->assertFileDoesNotExist($barrier.'/error-0');
        $this->assertFileDoesNotExist($barrier.'/error-1');
        $this->assertSame(1, Attendance::where('employee_id', $employee->id)->count());
        $this->assertCount(1, Storage::disk('private')->allFiles('selfies'));

        foreach (glob($barrier.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($barrier);
    }
}
