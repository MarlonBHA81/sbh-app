<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->enum('kind', ['dm', 'group']);
            $table->string('title', 80)->nullable();
            $table->string('avatar_path')->nullable();
            $table->text('rules')->nullable();
            $table->foreignId('created_by_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->unsignedInteger('messages_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
