<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a business profile whose CIPC registration has been confirmed — the
 * source of the "CIPC verified" sticker. Separate from is_verified (the manual
 * document review): a business can be CIPC-registered without a full review,
 * and vice versa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->timestamp('cipc_verified_at')->nullable()->after('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('cipc_verified_at');
        });
    }
};
