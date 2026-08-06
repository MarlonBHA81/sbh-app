<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side, auditable record of a data subject's cookie/privacy consent
 * (POPIA/GDPR). The web banner still gates cookies client-side; this persists an
 * immutable record of each choice — version, decision, and request context — so
 * consent is provable, not just a localStorage flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // e.g. "cookie" — leaves room for other consent types later.
            $table->string('type')->default('cookie');
            // Policy version the choice was made against.
            $table->string('policy_version');
            // accepted | rejected (mirrors the web ConsentChoice).
            $table->string('choice');
            // Optional per-category grants for future granular consent.
            $table->json('categories')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
