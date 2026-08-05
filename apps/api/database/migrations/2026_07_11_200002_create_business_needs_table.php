<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_needs', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['offering', 'seeking']);
            $table->foreignId('business_category_id')->constrained()->cascadeOnDelete();
            $table->string('description', 500);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['kind', 'business_category_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_needs');
    }
};
