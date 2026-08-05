<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xp_actions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            // Points may be negative (e.g. penalties), so a signed integer.
            $table->integer('points');
            // Maximum number of times this action may award XP per (UTC) day.
            // Null means no cap.
            $table->unsignedInteger('daily_cap')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_actions');
    }
};
