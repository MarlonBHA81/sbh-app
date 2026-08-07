<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen profiles.kind from enum(personal, business) to a string so the new
 * 'corporate' kind (ESD sponsors) fits. MySQL enforces the enum strictly and
 * truncates unknown values; kinds are validated by the Profile model consts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('kind', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->enum('kind', ['personal', 'business'])->change();
        });
    }
};
