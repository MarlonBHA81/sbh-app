<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business Journey stage (V1) — where a member is in their journey, captured at
 * signup so the app can personalise Home, opportunities and learning. Stored as
 * a nullable slug string (validated in the request against Profile::JOURNEY_STAGES),
 * mirroring how `category` is held.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('journey_stage', 40)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('journey_stage');
        });
    }
};
