<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Business Brief (V2 · Feature 7). `brief_items` holds admin-curated
 * briefing snippets (tips, regulation, news, resources) optionally targeted at an
 * industry; `daily_briefs` caches one personalised headline per profile per day so
 * the AI call runs at most once a day (and is pre-warmed by `briefs:generate`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brief_items', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('kind', 32);
            $table->string('title');
            $table->text('body');
            $table->string('industry')->nullable();
            $table->string('url')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'industry']);
        });

        Schema::create('daily_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->date('brief_date');
            $table->text('headline');
            $table->timestamps();

            $table->unique(['profile_id', 'brief_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_briefs');
        Schema::dropIfExists('brief_items');
    }
};
