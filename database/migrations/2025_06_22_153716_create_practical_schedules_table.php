<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('practical_schedules', function (Blueprint $table) {
            $table->id();
            $table->enum('days',['Sunday','Monday','Tuesday','Wednesday','Thursday']);
            $table->foreignId('stage_id')
                ->constrained('stages')
                ->onDelete('cascade');
            $table->foreignId('supervisor_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->string('location');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('year',['fourth-year','fifth-year']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practical_schedule');
    }
};
