<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound CRM/marketing webhooks. Contact + commerce activity is POSTed to
 * configured endpoints (Brevo, or any URL) so a CRM can sync contacts and run
 * automations. `format` selects the payload shape; `events` filters which
 * activity is delivered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('format', 16)->default('generic'); // generic | brevo
            // Generic: HMAC-signs with this secret. Provider mode: sent as a header.
            $table->string('secret')->nullable();
            $table->string('header_name')->nullable();
            $table->string('header_value')->nullable();
            $table->json('events')->nullable(); // null/[] = all events
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
