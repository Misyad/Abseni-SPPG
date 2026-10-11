<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('shift_id')->constrained('shifts')->onDelete('restrict');
            $table->date('schedule_date')->comment('Tanggal mulai kerja');
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'schedule_date'], 'uk_employee_schedule_date');
            $table->index(['schedule_date', 'shift_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
