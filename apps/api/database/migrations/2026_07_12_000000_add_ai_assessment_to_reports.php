<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // Advisory AI moderation assessment, populated asynchronously when
            // the AI gateway is enabled. Null when AI is disabled or pending.
            $table->json('ai_assessment')->nullable()->after('resolution_note');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('ai_assessment');
        });
    }
};
