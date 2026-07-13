<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-created challenges: time-boxed XP competitions with their own
 * leaderboards. Participants opt in; ranking = XP earned inside the window.
 * Also: group conversations now require admin approval before going live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'ends_at']);
        });

        Schema::create('challenge_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at');

            $table->unique(['challenge_id', 'profile_id']);
            $table->index('profile_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            // DMs are auto-approved; new groups start pending (admin review).
            $table->string('approval_status', 10)->default('approved')->after('kind');
            $table->timestamp('approved_at')->nullable()->after('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approved_at']);
        });
        Schema::dropIfExists('challenge_participants');
        Schema::dropIfExists('challenges');
    }
};
