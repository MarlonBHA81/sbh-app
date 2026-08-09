<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member-facing TOTP two-factor auth (authenticator apps) for the consumer
 * SPA / mobile clients. Kept separate from the Filament admin MFA columns
 * (app_authentication_*) so the two flows are independent — an admin can hold
 * both. Secret + recovery codes are encrypted at the model layer; the
 * confirmed_at timestamp marks a completed enrolment (a challenge is only
 * required once 2FA is confirmed, not while it's mid-setup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
