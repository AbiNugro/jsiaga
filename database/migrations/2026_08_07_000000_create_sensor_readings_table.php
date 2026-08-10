<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->decimal('distance', 8, 2)->nullable();
            $table->decimal('water_level', 5, 2)->nullable();
            $table->string('status', 16)->index();
            $table->decimal('temperature', 6, 2)->nullable();
            $table->decimal('humidity', 5, 2)->nullable();
            $table->integer('light')->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_readings');
    }
};
