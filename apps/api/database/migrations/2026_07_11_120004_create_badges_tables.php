<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->enum('kind', ['category', 'verification', 'rank']);
            $table->timestamps();
        });

        Schema::create('profile_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at')->nullable();

            $table->unique(['profile_id', 'badge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_badges');
        Schema::dropIfExists('badges');
    }
};
