<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store products (Shop P1) — digital downloads, courses and services. Price is
 * stored in minor units (cents); checkout/delivery arrive in Phase 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->default('digital_download');
            $table->string('title');
            $table->text('description');
            $table->unsignedBigInteger('price_cents')->nullable();
            $table->string('currency', 3)->default('ZAR');
            $table->string('cover_path')->nullable();
            // Delivered on purchase (Phase 2); never exposed publicly here.
            $table->string('download_path')->nullable();
            $table->string('external_url')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
