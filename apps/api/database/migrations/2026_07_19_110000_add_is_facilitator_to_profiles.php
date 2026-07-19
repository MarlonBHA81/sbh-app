<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facilitator role (Roles P2). An admin-granted flag marking a member as a
 * trusted facilitator who can create challenges and own auto-approved Spaces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean('is_facilitator')->default(false)->after('is_mentor');
            $table->index('is_facilitator');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex(['is_facilitator']);
            $table->dropColumn('is_facilitator');
        });
    }
};
