<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('restrict');
            $table->foreignId('location_id')->constrained('locations')->onDelete('restrict');
            $table->foreignId('shift_id')->constrained('shifts')->onDelete('restrict');
            $table->date('work_date')->comment('Tanggal mulai shift yang berlaku');

            // Check In Data
            $table->dateTime('check_in_at')->nullable();
            $table->decimal('check_in_lat', 10, 8)->nullable();
            $table->decimal('check_in_lng', 11, 8)->nullable();
            $table->decimal('check_in_accuracy', 8, 2)->nullable();
            $table->decimal('check_in_distance', 8, 2)->nullable();
            $table->string('check_in_photo_path', 255)->nullable();

            // Check Out Data
            $table->dateTime('check_out_at')->nullable();
            $table->decimal('check_out_lat', 10, 8)->nullable();
            $table->decimal('check_out_lng', 11, 8)->nullable();
            $table->decimal('check_out_accuracy', 8, 2)->nullable();
            $table->decimal('check_out_distance', 8, 2)->nullable();
            $table->string('check_out_photo_path', 255)->nullable();

            // Status & Kalkulasi
            $table->enum('status', [
                'on_time',
                'late',
                'early_leave',
                'absent',
                'needs_review',
                'corrected',
            ])->default('on_time');

            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason', 255)->nullable();

            // Review & Verifikasi Admin
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->onDelete('set null');
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            // Metadata Teknis
            $table->string('client_ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'work_date', 'shift_id'], 'uk_attendance_employee_workdate_shift');
            $table->index(['work_date', 'status']);
            $table->index(['is_flagged', 'work_date']);
            $table->index(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
