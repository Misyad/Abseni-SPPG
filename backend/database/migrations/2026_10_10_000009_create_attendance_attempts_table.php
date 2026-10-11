<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('restrict');
            $table->string('reason_code', 50);
            $table->string('message', 255);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('attempted_at');
            $table->timestamps();

            $table->index(['employee_id', 'attempted_at']);
            $table->index(['reason_code', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_attempts');
    }
};
