<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Official opportunity ingestion (V3 · GROW) — extends V1 Opportunities with a
 * provenance layer so admins can record where an opportunity came from and mark
 * trusted ones as "Official". `source_ref` is reserved for a future importer to
 * de-duplicate against an external record (see the ImportOfficialOpportunities
 * console stub). No third-party scraping ships here — schema + admin flow only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            // Human-readable origin, e.g. "eTenders", "SEDA", "Manual".
            $table->string('source')->nullable()->after('url');
            // Canonical link to the original listing (distinct from the apply URL).
            $table->string('source_url')->nullable()->after('source');
            // Stable external id for de-duplication by a future importer.
            $table->string('source_ref')->nullable()->after('source_url');
            // Admin-verified as coming from an official/trusted source.
            $table->boolean('is_official')->default(false)->after('source_ref');

            $table->index(['is_official', 'is_published']);
            $table->unique(['source', 'source_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_ref']);
            $table->dropIndex(['is_official', 'is_published']);
            $table->dropColumn(['source', 'source_url', 'source_ref', 'is_official']);
        });
    }
};
