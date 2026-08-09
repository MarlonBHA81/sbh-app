<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen media.status from a MySQL enum to a string so the virus-scan
 * 'infected' state can be stored. MySQL strictly enforces enum() and truncates
 * unknown values (SQLite does not), so — mirroring the ad_events.kind /
 * profiles.kind precedent — the column becomes a plain string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('status', 20)->default('ready')->change();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->enum('status', ['processing', 'ready', 'failed'])->default('ready')->change();
        });
    }
};
