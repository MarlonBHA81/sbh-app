<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->morphs('reactable');
            $table->enum('kind', ['like', 'up', 'down']);
            $table->enum('bucket', ['like', 'vote']);
            $table->timestamps();

            // Likes and votes are independent, so uniqueness is scoped per
            // bucket. up vs down are mutually exclusive within the 'vote'
            // bucket by virtue of a single row per (profile, reactable, vote).
            $table->unique(
                ['profile_id', 'reactable_type', 'reactable_id', 'bucket'],
                'reactions_profile_reactable_bucket_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
