<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the metrics-first ad_events pipeline (Shop P4) so sponsored masterclass
 * rooms record impressions and clicks alongside campaigns, slots and sponsored
 * opportunities. Still metrics-only — an ad_event never carries money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_events', function (Blueprint $table) {
            $table->foreignId('masterclass_id')->nullable()->after('opportunity_id')
                ->constrained()->cascadeOnDelete();

            $table->index(['masterclass_id', 'kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ad_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('masterclass_id');
        });
    }
};
