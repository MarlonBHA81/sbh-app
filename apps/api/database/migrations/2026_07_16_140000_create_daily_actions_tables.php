<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Challenge + Streak engine (V1 · PROGRESS). A small daily action
 * (<15 min) whose completion awards XP and advances the member's streak.
 * Streak state lives on the profile so the streak chip can read it cheaply.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_actions', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('daily_action_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_action_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->date('completed_on');
            $table->timestamps();

            // One completion per profile per day.
            $table->unique(['profile_id', 'completed_on']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedInteger('streak_count')->default(0)->after('xp_total');
            $table->date('streak_last_day')->nullable()->after('streak_count');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['streak_count', 'streak_last_day']);
        });
        Schema::dropIfExists('daily_action_completions');
        Schema::dropIfExists('daily_actions');
    }
};
