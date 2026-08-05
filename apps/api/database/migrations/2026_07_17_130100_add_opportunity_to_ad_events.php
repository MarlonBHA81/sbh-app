<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the metrics-first ad_events pipeline (V3 · GROW) so sponsored
 * opportunities record impressions and clicks alongside campaigns and sponsor
 * slots. Still metrics-only — an ad_event never carries money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_events', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->nullable()->after('ad_slot_id')
                ->constrained()->cascadeOnDelete();

            $table->index(['opportunity_id', 'kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ad_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opportunity_id');
        });
    }
};
