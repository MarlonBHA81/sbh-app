<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resource Library (V2 · LEARN) — admin-curated templates, checklists,
 * toolkits and AI prompts brought to members instead of them searching. Each
 * resource points at an external URL or file link. Members can bookmark them,
 * exactly like opportunities.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('type', 32);
            $table->string('category', 64);
            $table->string('title');
            $table->text('description');
            $table->string('url', 500);
            $table->string('industry')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'category']);
            $table->index(['type', 'is_published']);
        });

        Schema::create('resource_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['resource_id', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_saves');
        Schema::dropIfExists('resources');
    }
};
