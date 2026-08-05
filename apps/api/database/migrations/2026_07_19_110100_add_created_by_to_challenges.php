<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facilitator-created challenges (Roles P2). Records which profile created a
 * challenge; null for the existing admin-authored ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->foreignId('created_by_profile_id')->nullable()->after('is_active')
                ->constrained('profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_profile_id');
        });
    }
};
