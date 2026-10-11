<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin lokal opsional; jangan pernah menyediakan password default di repository.
        if ($adminPassword = env('SEED_ADMIN_PASSWORD')) {
            Admin::updateOrCreate(
                ['email' => env('SEED_ADMIN_EMAIL', 'admin@sppg.local')],
                [
                    'name' => 'Administrator SPPG',
                    'password' => Hash::make($adminPassword),
                    'role' => 'superadmin',
                    'is_active' => true,
                ]
            );
        }

        // 2. Master Lokasi SPPG Pakiskembar 2
        $location = Location::updateOrCreate(
            ['code' => 'LOC-PKB2'],
            [
                'name' => 'SPPG Pakiskembar 2',
                'address' => 'Jl. Raya Pakiskembar No. 2, Pakis, Malang',
                'latitude' => -7.96662040,
                'longitude' => 112.63263210,
                'radius_meters' => 50,
                'is_active' => true,
            ]
        );

        // 3. Master Divisi
        $divDapur = Division::updateOrCreate(['code' => 'DIV-DAPUR'], ['name' => 'Dapur & Pengolahan', 'is_active' => true]);
        $divPemorsian = Division::updateOrCreate(['code' => 'DIV-PORS'], ['name' => 'Pemorsian & Pengemasan', 'is_active' => true]);
        $divDistribusi = Division::updateOrCreate(['code' => 'DIV-DIST'], ['name' => 'Distribusi Gizi', 'is_active' => true]);
        $divKebersihan = Division::updateOrCreate(['code' => 'DIV-KEBER'], ['name' => 'Kebersihan & Sanitas', 'is_active' => true]);

        // 4. Master Shift
        $shiftPagi = Shift::updateOrCreate(
            ['code' => 'SHIFT-PAGI'],
            [
                'name' => 'Shift Pagi (07:00 - 15:00)',
                'start_time' => '07:00:00',
                'end_time' => '15:00:00',
                'check_in_window_start_minutes' => 60,
                'check_in_window_end_minutes' => 240,
                'late_tolerance_minutes' => 15,
                'check_out_window_start_minutes' => 30,
                'check_out_window_end_minutes' => 240,
                'is_cross_day' => false,
                'is_active' => true,
            ]
        );

        $shiftMalam = Shift::updateOrCreate(
            ['code' => 'SHIFT-MALAM'],
            [
                'name' => 'Shift Malam (19:00 - 03:00)',
                'start_time' => '19:00:00',
                'end_time' => '03:00:00',
                'check_in_window_start_minutes' => 60,
                'check_in_window_end_minutes' => 240,
                'late_tolerance_minutes' => 15,
                'check_out_window_start_minutes' => 30,
                'check_out_window_end_minutes' => 240,
                'is_cross_day' => true,
                'is_active' => true,
            ]
        );

        // 5. Sample Pegawai lokal opsional; PIN wajib disuntikkan lewat environment.
        $employeePin = env('SEED_EMPLOYEE_PIN');
        if (! $employeePin) {
            return;
        }
        if (! preg_match('/^\d{6}$/', $employeePin)) {
            throw new \InvalidArgumentException('SEED_EMPLOYEE_PIN harus berupa tepat 6 digit numerik.');
        }

        $emp1 = Employee::updateOrCreate(
            ['employee_code' => 'SPPG-PKB-001'],
            [
                'division_id' => $divDapur->id,
                'name' => 'Ahmad Fauzi',
                'pin_hash' => Hash::make($employeePin),
                'phone_number' => null,
                'is_active' => true,
            ]
        );

        $emp2 = Employee::updateOrCreate(
            ['employee_code' => 'SPPG-PKB-002'],
            [
                'division_id' => $divPemorsian->id,
                'name' => 'Budi Santoso',
                'pin_hash' => Hash::make($employeePin),
                'phone_number' => null,
                'is_active' => true,
            ]
        );

        $emp3 = Employee::updateOrCreate(
            ['employee_code' => 'SPPG-PKB-003'],
            [
                'division_id' => $divDistribusi->id,
                'name' => 'Chandra Wijaya',
                'pin_hash' => Hash::make($employeePin),
                'phone_number' => null,
                'is_active' => true,
            ]
        );

        // 6. Penugasan Jadwal Hari Ini
        $today = Carbon::now()->toDateString();
        Schedule::updateOrCreate(
            ['employee_id' => $emp1->id, 'schedule_date' => $today],
            ['shift_id' => $shiftPagi->id, 'notes' => 'Jadwal Reguler Dapur']
        );
        Schedule::updateOrCreate(
            ['employee_id' => $emp2->id, 'schedule_date' => $today],
            ['shift_id' => $shiftMalam->id, 'notes' => 'Jadwal Shift Malam Pemorsian']
        );
    }
}
