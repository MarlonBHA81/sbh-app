<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->morphs('mentionable');
            $table->foreignId('mentioned_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('mentioner_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['mentionable_type', 'mentionable_id', 'mentioned_profile_id'],
                'mentions_mentionable_profile_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentions');
    }
};
