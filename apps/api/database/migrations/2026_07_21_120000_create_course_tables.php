<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course content (Shop P3). A `course` product carries an ordered
 * modules → lessons tree; a lesson may hold rich text, an embedded video URL
 * and/or a gated attachment. Per-buyer progress is tracked in
 * course_lesson_progress. Read access is gated by the shop Purchase entitlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'position']);
        });

        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('course_module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('video_url')->nullable();
            $table->string('attachment_path')->nullable(); // private "local" disk
            $table->unsignedInteger('minutes')->nullable();
            $table->boolean('is_preview')->default(false); // visible without purchase
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['course_module_id', 'position']);
        });

        Schema::create('course_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['course_lesson_id', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lesson_progress');
        Schema::dropIfExists('course_lessons');
        Schema::dropIfExists('course_modules');
    }
};
