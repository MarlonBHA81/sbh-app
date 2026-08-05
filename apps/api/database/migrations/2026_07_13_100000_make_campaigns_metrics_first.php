<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaigns are metrics-first: budgets become optional (a campaign runs for
 * its duration and simply reports performance), and outbound link clicks are
 * tracked separately from post-open clicks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('budget_cents')->nullable()->change();
            $table->unsignedBigInteger('link_clicks_count')->default(0)->after('clicks_count');
        });

        // Widen the event kind from enum(impression, click) so link_click
        // (and future kinds) fit; validation lives in TrackAdRequest.
        Schema::table('ad_events', function (Blueprint $table) {
            $table->string('kind', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('link_clicks_count');
        });
    }
};
