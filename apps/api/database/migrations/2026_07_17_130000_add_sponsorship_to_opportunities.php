<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Value-aligned monetisation (V3 · GROW) — lets an opportunity be marked as
 * sponsored/partner-featured. This is metrics-first, exactly like the rest of
 * the ad system: NO price, NO budget, NO spend. A sponsored opportunity simply
 * carries a partner label, sorts ahead of the organic feed, and its reach is
 * measured through the existing ad_events pipeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->boolean('is_sponsored')->default(false)->after('is_official');
            $table->string('sponsor_name')->nullable()->after('is_sponsored');
            $table->string('sponsor_url')->nullable()->after('sponsor_name');

            $table->index(['is_sponsored', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['is_sponsored', 'is_published']);
            $table->dropColumn(['is_sponsored', 'sponsor_name', 'sponsor_url']);
        });
    }
};
