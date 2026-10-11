<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->time('start_time')->comment('Jam mulai kerja (e.g. 19:00:00)');
            $table->time('end_time')->comment('Jam selesai kerja (e.g. 03:00:00)');
            $table->integer('check_in_window_start_minutes')->default(60);
            $table->integer('check_in_window_end_minutes')->default(240);
            $table->integer('late_tolerance_minutes')->default(15);
            $table->integer('check_out_window_start_minutes')->default(30);
            $table->integer('check_out_window_end_minutes')->default(240);
            $table->boolean('is_cross_day')->default(false)->comment('TRUE jika end_time < start_time (shift malam)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
