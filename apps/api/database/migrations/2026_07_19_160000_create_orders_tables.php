<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace orders + entitlements (Shop P2). An order is created pending at
 * checkout and marked paid by the PayFast ITN; a paid order grants a Purchase
 * (entitlement) per product, which gates digital delivery / course access.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('reference')->unique(); // m_payment_id sent to PayFast
            $table->foreignId('buyer_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('pending'); // pending|paid|cancelled|failed
            $table->unsignedBigInteger('total_cents');
            $table->string('currency', 3)->default('ZAR');
            $table->unsignedBigInteger('platform_fee_cents')->default(0);
            $table->unsignedBigInteger('vendor_amount_cents')->default(0);
            $table->string('pf_payment_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_profile_id', 'status']);
            $table->index(['store_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title'); // snapshot
            $table->unsignedBigInteger('unit_cents');
            $table->string('kind', 16)->default('item'); // item|bump|upsell
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['buyer_profile_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
