<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_topic', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();

            $table->unique(['post_id', 'topic_id']);
            $table->index(['topic_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_topic');
    }
};
