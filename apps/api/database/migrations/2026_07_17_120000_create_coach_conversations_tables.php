<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Business Coach v1 (V2 · LEARN). A lightweight persisted chat between a
 * member and the coach so the conversation survives reload. One conversation
 * per profile for v1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique('profile_id');
        });

        Schema::create('coach_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16); // user | assistant
            $table->text('body');
            $table->timestamps();

            $table->index(['coach_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_messages');
        Schema::dropIfExists('coach_conversations');
    }
};
