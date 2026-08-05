<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-day view rollups for stores and products (Shop P4 · vendor analytics).
 * Mirrors post_stats_daily: one row per subject per UTC day, upsert-incremented
 * by the shop "seen" endpoint (deduped per viewer per day).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'date']);
        });

        Schema::create('product_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stats_daily');
        Schema::dropIfExists('store_stats_daily');
    }
};
