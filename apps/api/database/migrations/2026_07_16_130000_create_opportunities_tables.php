<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opportunities (V1 · GROW) — tenders, funding, grants, procurement and
 * programmes brought to members instead of them searching. Admin-curated to
 * start (Filament); the schema already supports member submission later via a
 * `created_by` + a future `status` column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('type', 32);
            $table->string('title');
            $table->text('description');
            $table->string('organisation')->nullable();
            $table->string('url')->nullable();
            $table->string('industry')->nullable();
            $table->string('province')->nullable();
            $table->string('amount')->nullable();
            $table->date('closes_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'closes_at']);
            $table->index(['type', 'is_published']);
        });

        Schema::create('opportunity_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['opportunity_id', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_saves');
        Schema::dropIfExists('opportunities');
    }
};
