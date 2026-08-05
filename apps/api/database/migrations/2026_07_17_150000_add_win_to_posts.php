<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wins / success stories (V2 · BELONG). A lightweight flag letting a member
 * celebrate a win, surfaced in a dedicated feed and a Home card so the
 * community can cheer real milestones on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_win')->default(false)->after('answered_at');

            $table->index(['is_win', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['is_win', 'published_at']);
            $table->dropColumn('is_win');
        });
    }
};
