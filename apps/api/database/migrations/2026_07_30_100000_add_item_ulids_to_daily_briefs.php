<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache the per-member AI-curated brief item selection alongside the headline,
 * so the daily AI curation runs at most once per member per day (mirrors the
 * headline caching). Null/empty falls back to the live industry-match query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_briefs', function (Blueprint $table) {
            $table->json('item_ulids')->nullable()->after('headline');
        });
    }

    public function down(): void
    {
        Schema::table('daily_briefs', function (Blueprint $table) {
            $table->dropColumn('item_ulids');
        });
    }
};
