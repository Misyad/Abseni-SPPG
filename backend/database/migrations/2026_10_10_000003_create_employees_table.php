<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->onDelete('restrict');
            $table->string('employee_code', 50)->unique();
            $table->string('name', 150);
            $table->string('pin_hash', 255)->comment('Bcrypt hash dari PIN numerik');
            $table->string('phone_number', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('failed_pin_attempts')->default(0);
            $table->dateTime('locked_until')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'division_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
