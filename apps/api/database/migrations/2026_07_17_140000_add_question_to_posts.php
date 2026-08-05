<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ask-the-Community Q&A (V2 · CONNECT). A lightweight flag marking a post as a
 * question, plus an answered timestamp that is set when the author marks a
 * comment helpful (building on the V1 contribution-recognition feature).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_question')->default(false)->after('sensitive');
            $table->timestamp('answered_at')->nullable()->after('is_question');

            $table->index(['is_question', 'answered_at']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['is_question', 'answered_at']);
            $table->dropColumn(['is_question', 'answered_at']);
        });
    }
};
