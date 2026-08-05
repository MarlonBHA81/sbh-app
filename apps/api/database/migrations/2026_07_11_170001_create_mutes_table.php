<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('muter_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('muted_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['muter_profile_id', 'muted_profile_id']);
            $table->index('muted_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutes');
    }
};
