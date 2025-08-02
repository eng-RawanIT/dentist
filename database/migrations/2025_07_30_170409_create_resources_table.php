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
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('resource_name');
            $table->enum('category', ['Books_and_References', 'Paper_lectures', 'Medical_instruments','General']);
            $table->foreignId('owner_student_id')->constrained('users')->onDelete('cascade');
            $table->date('loan_start_date')->nullable();
            $table->date('loan_end_date')->nullable();
            $table->enum('status', ['available', 'booked'])->default('available');
            $table->string('image_path')->nullable();
            $table->foreignId('booked_by_student_id')
                ->nullable()
                ->constrained('students')
                ->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
