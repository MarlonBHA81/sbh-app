<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contribution-based recognition (V1 · BELONG). A post author can mark a
 * comment on their post as "helpful"; the answerer earns XP and the mark is
 * tallied on their profile as a visible contribution signal — so members
 * become known for helping, not for collecting followers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->timestamp('helpful_at')->nullable()->after('replies_count');
        });

        Schema::table('profiles', function (Blueprint $table) {
            // Number of this profile's comments currently marked helpful.
            $table->unsignedInteger('helpful_count')->default(0)->after('xp_total');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('helpful_at');
        });
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('helpful_count');
        });
    }
};
