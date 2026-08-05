<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session recordings (ask #4). The provider records each live session and, when
 * the recording is ready, fires an asset webhook carrying its HLS playback URL —
 * stored here so members can watch the replay after the room goes offline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('masterclass_live_sessions', function (Blueprint $table) {
            $table->string('recording_playback_url')->nullable()->after('playback_url');
            $table->timestamp('recording_ready_at')->nullable()->after('recording_playback_url');
        });
    }

    public function down(): void
    {
        Schema::table('masterclass_live_sessions', function (Blueprint $table) {
            $table->dropColumn(['recording_playback_url', 'recording_ready_at']);
        });
    }
};
