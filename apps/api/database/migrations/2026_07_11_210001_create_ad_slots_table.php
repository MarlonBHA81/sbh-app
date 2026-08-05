<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_slots', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->enum('placement', ['right_rail', 'feed_inline']);
            $table->string('name');
            $table->string('sponsor_name');
            $table->string('sponsor_url');
            $table->string('image_path')->nullable();
            $table->string('body')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedTinyInteger('weight')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_slots');
    }
};
