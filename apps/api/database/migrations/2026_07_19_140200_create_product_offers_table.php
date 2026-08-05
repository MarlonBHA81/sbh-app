<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product offer relations (Shop P1) — cross-sells shown on the product page, plus
 * order bumps and upsells that activate at checkout in Phase 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('kind', 16); // cross_sell | bump | upsell
            $table->unsignedBigInteger('discount_cents')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'related_product_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_offers');
    }
};
