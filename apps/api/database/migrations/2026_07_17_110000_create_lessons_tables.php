<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bite-size learning modules (V2 · LEARN). Short ~5-minute lessons, optionally
 * grouped into simple tracks and tagged to a journey stage, that members read
 * and mark complete. Per-member completion is tracked and awards XP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_tracks', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('title');
            $table->string('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'position']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('lesson_track_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            // Either inline body (rich text / markdown) or an external link.
            $table->text('body')->nullable();
            $table->string('external_url', 500)->nullable();
            $table->unsignedSmallInteger('minutes')->default(5);
            $table->string('journey_stage', 32)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'position']);
            $table->index(['journey_stage', 'is_published']);
        });

        Schema::create('lesson_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['lesson_id', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_completions');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('lesson_tracks');
    }
};
