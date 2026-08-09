<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the outcome of an automated CIPC registration lookup on a business
 * verification: the status (verified/not_found/unavailable), when it ran, and
 * the registered name CIPC returned. Kept as a plain string (not an enum) to
 * avoid MySQL truncation if the states grow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_verifications', function (Blueprint $table) {
            $table->string('cipc_status', 20)->nullable()->after('registration_number');
            $table->timestamp('cipc_checked_at')->nullable()->after('cipc_status');
            $table->string('cipc_registered_name')->nullable()->after('cipc_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('business_verifications', function (Blueprint $table) {
            $table->dropColumn(['cipc_status', 'cipc_checked_at', 'cipc_registered_name']);
        });
    }
};
