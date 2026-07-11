<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['personal', 'business']);
            $table->string('handle')->unique();
            $table->string('name');
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('category')->nullable();
            $table->string('website')->nullable();
            $table->string('location')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->char('geohash', 9)->nullable()->index();
            $table->char('country_code', 2)->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->unsignedInteger('followers_count')->default(0);
            $table->unsignedInteger('following_count')->default(0);
            $table->unsignedInteger('posts_count')->default(0);
            $table->unsignedInteger('xp_total')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
