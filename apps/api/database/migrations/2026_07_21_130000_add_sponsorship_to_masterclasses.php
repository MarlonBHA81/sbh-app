<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sponsored / branded masterclass rooms (Shop P4 · ask #3). A masterclass can
 * carry its own branding (colours, logo, banner) and, when sponsored, a brand's
 * name / link / blurb. Sponsorship is metrics-first — impressions and clicks are
 * tracked via ad_events; no money is attached here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('masterclasses', function (Blueprint $table) {
            $table->string('brand_color', 9)->nullable()->after('facilitator_name');
            $table->string('accent_color', 9)->nullable()->after('brand_color');
            $table->string('logo_path')->nullable()->after('accent_color');
            $table->string('banner_path')->nullable()->after('logo_path');

            $table->boolean('is_sponsored')->default(false)->after('banner_path');
            $table->string('sponsor_name')->nullable()->after('is_sponsored');
            $table->string('sponsor_url')->nullable()->after('sponsor_name');
            $table->text('sponsor_blurb')->nullable()->after('sponsor_url');

            $table->index(['is_sponsored', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::table('masterclasses', function (Blueprint $table) {
            $table->dropIndex(['is_sponsored', 'is_published']);
            $table->dropColumn([
                'brand_color', 'accent_color', 'logo_path', 'banner_path',
                'is_sponsored', 'sponsor_name', 'sponsor_url', 'sponsor_blurb',
            ]);
        });
    }
};
