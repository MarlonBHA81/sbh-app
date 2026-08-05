<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen the columns that now hold encrypted values. Ciphertext is longer than
 * the plaintext and is NOT valid JSON, so:
 *  - settings.value moves from json → text (encrypted blobs aren't JSON),
 *  - webhook_endpoints.secret / header_value move from string(255) → text.
 * Existing plaintext rows survive: the encrypted casts read them as-is and
 * re-encrypt on the next write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->text('value')->nullable()->change();
        });

        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->text('secret')->nullable()->change();
            $table->text('header_value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->json('value')->nullable()->change();
        });

        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->string('secret')->nullable()->change();
            $table->string('header_value')->nullable()->change();
        });
    }
};
