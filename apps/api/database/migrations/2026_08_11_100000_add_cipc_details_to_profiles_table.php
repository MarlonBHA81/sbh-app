<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the CIPC registration a business profile was created against: the raw
 * registration number and the registered company name CIPC returned. Populated
 * at creation time by the hard CIPC gate — a business profile can only be made
 * once CIPC confirms its number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('registration_number')->nullable()->after('cipc_verified_at');
            $table->string('cipc_registered_name')->nullable()->after('registration_number');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['registration_number', 'cipc_registered_name']);
        });
    }
};
