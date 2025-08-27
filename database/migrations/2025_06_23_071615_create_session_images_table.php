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
        Schema::create('session_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('sessions')
                ->onDelete('cascade');
            $table->enum('type',['before-treatment','after-treatment']);
            $table->string('image_url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_images');
    }
};
