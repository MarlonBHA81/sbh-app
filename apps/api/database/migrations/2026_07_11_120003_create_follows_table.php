<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('followed_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->enum('state', ['accepted', 'pending'])->default('accepted');
            $table->timestamps();

            $table->unique(['follower_profile_id', 'followed_profile_id']);
            $table->index(['followed_profile_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
