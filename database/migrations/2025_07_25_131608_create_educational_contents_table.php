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
        Schema::create('educational_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', [ 'article', 'pdf', 'link', 'image']);
            $table->longText('text_content')->nullable(); // For internal article text
            $table->string('content_url')->nullable(); // For external URLs
            $table->string('file_path')->nullable(); // For uploaded files
            $table->timestamp('published_at')->nullable();
            $table->foreignId('stage_id')->constrained('stages')->onDelete('cascade');
            $table->double('appropriate_rating');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('educational_contents');
    }
};
