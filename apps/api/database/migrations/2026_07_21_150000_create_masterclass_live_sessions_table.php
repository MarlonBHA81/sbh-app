<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live-streaming sessions for masterclass rooms (ask #4). The host streams via
 * RTMP to the provider (stream_key + ingest_url are host-only secrets); members
 * watch the HLS playback_url in-app. Status is flipped by the provider webhook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masterclass_live_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('masterclass_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('status', 16)->default('idle'); // idle|active|ended
            $table->string('provider')->nullable();
            $table->string('provider_stream_id')->nullable()->index();
            $table->string('stream_key')->nullable();   // host-only secret
            $table->string('ingest_url')->nullable();    // host-only (RTMP)
            $table->string('playback_id')->nullable();
            $table->string('playback_url')->nullable();  // HLS .m3u8
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['masterclass_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masterclass_live_sessions');
    }
};
