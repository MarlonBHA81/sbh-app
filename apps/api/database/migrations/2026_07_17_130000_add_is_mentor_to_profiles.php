<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mentor matching (V2 · CONNECT). An opt-in flag marking a member as willing to
 * mentor others; the /mentors list ranks opted-in profiles by relevance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean('is_mentor')->default(false)->after('journey_stage');
            $table->index(['is_mentor', 'is_private']);
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex(['is_mentor', 'is_private']);
            $table->dropColumn('is_mentor');
        });
    }
};
