<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->unsignedInteger('min_xp');
            // Emoji icon shown next to the rank.
            $table->string('icon')->nullable();
            $table->unsignedTinyInteger('position');
            // Each rank is mirrored by a 'rank' kind badge that is attached to
            // profiles when they reach the rank.
            $table->foreignId('badge_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->foreignId('rank_id')->nullable()->after('xp_total')
                ->constrained('ranks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rank_id');
        });

        Schema::dropIfExists('ranks');
    }
};
