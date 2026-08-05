<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live room chat (ask #4). Each masterclass room gets an attached group
 * conversation; enrolled members become participants and use the existing
 * realtime messaging + reactions stack. Nulled (not cascaded) if the
 * conversation is ever deleted so the room itself survives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('masterclasses', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('created_by')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('masterclasses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
        });
    }
};
