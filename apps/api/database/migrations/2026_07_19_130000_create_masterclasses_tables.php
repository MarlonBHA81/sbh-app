<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Masterclasses & cohorts (V3 · LEARN) — longer, cohort-based programmes layered
 * above the V2 bite-size lessons. A masterclass runs over a period with an
 * optional seat cap; members enrol into the cohort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masterclasses', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('title');
            $table->text('description');
            $table->string('facilitator_name')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'ends_at']);
        });

        Schema::create('masterclass_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('masterclass_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();

            $table->unique(['masterclass_id', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masterclass_participants');
        Schema::dropIfExists('masterclasses');
    }
};
