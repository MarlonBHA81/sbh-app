<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A general, append-only audit log of security-sensitive user actions (login,
 * token issue/revoke, data export, account deletion, consent). Distinct from
 * moderation_actions, which is scoped to admin/moderation decisions. Supports
 * the POPIA "keep a record of processing" posture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Null-on-delete so the trail survives account deletion (the delete
            // itself is one of the events we log).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->nullableMorphs('target');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
