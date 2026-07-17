<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goals (V3 · PROGRESS) — member-set goals and milestones tracked on their
 * business dashboard. One list per profile; completing a goal is a real,
 * self-declared win (never invented for the user).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('target')->nullable();
            $table->date('due_on')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_done')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['profile_id', 'is_done']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
