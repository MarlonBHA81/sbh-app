<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commerce depth: per-store VAT (South-African inclusive), product sale prices,
 * and the coupon/VAT/discount breakdown recorded on each order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('is_vat_registered')->default(false)->after('policies');
            // VAT rate in basis points (1500 = 15%). SA standard rate default.
            $table->unsignedInteger('vat_rate_bp')->default(1500)->after('is_vat_registered');
            $table->string('vat_number')->nullable()->after('vat_rate_bp');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_price_cents')->nullable()->after('price_cents');
            $table->timestamp('sale_ends_at')->nullable()->after('sale_price_cents');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_cents')->default(0)->after('total_cents');
            // Inclusive VAT portion recorded within total_cents (not added on top).
            $table->unsignedBigInteger('vat_cents')->default(0)->after('discount_cents');
            $table->unsignedInteger('vat_rate_bp')->default(0)->after('vat_cents');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['is_vat_registered', 'vat_rate_bp', 'vat_number']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sale_price_cents', 'sale_ends_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['discount_cents', 'vat_cents', 'vat_rate_bp']);
        });
    }
};
