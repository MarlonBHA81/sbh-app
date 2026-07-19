<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-user business profiles (Roles P4). Links users to a (business) profile
 * with a role so a team can manage/post under one business identity. Personal
 * profiles stay single-owner (they simply get no rows here). The profile's
 * existing `user_id` remains the creator/owner and is backfilled as an owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('poster');
            $table->timestamps();

            $table->unique(['profile_id', 'user_id']);
        });

        // Backfill: every existing business profile's creator becomes an owner.
        $businesses = DB::table('profiles')->where('kind', 'business')->get(['id', 'user_id']);
        $now = now();

        foreach ($businesses as $profile) {
            DB::table('profile_members')->insert([
                'profile_id' => $profile->id,
                'user_id' => $profile->user_id,
                'role' => 'owner',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_members');
    }
};
