<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xp_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('action_key');
            // Points actually awarded by this entry (may be negative).
            $table->integer('points');
            // Optional subject the award is bound to (post, comment, follow…).
            $table->nullableMorphs('subject');
            // Ledger rows are immutable; only a creation timestamp is kept.
            $table->timestamp('created_at')->nullable();

            $table->index(['profile_id', 'created_at']);
            $table->index('action_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_ledger');
    }
};
