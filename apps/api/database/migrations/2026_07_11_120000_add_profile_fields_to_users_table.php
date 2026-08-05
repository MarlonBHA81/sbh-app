<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale')->default('en')->nullable()->after('remember_token');
            $table->string('timezone')->default('Africa/Johannesburg')->nullable()->after('locale');
            $table->boolean('is_admin')->default(false)->after('timezone');
            $table->timestamp('banned_at')->nullable()->after('is_admin');
            $table->string('ban_reason')->nullable()->after('banned_at');
            $table->json('settings')->nullable()->after('ban_reason');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locale', 'timezone', 'is_admin', 'banned_at', 'ban_reason', 'settings']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
