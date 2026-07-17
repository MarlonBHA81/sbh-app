<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wellness & resilience space (V3 · BELONG) — a calm, supportive corner of SBH.
 *
 * `wellness_resources` are admin-curated prompts/reads (mirrors the opportunity
 * curation pattern). `wellness_checkins` are a member's private "how are you
 * doing?" logs — visible only to the member, never gamified, never ranked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wellness_resources', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('category', 32)->default('reflection');
            $table->string('title');
            $table->text('body');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'position']);
        });

        Schema::create('wellness_checkins', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            // 1 (finding it hard) … 5 (doing well). Self-reported, private.
            $table->unsignedTinyInteger('mood');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wellness_checkins');
        Schema::dropIfExists('wellness_resources');
    }
};
