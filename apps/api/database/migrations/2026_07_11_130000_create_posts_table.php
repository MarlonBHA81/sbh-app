<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->enum('visibility', ['public', 'followers'])->default('public');
            $table->enum('status', ['draft', 'scheduled', 'published'])->default('published');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->char('geohash', 9)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('city')->nullable();
            $table->boolean('sensitive')->default(false);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('reposts_count')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('upvotes_count')->default(0);
            $table->unsignedInteger('downvotes_count')->default(0);
            $table->double('score')->default(0)->index();
            $table->foreignId('parent_post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['profile_id', 'status', 'published_at']);
            $table->index('parent_post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
