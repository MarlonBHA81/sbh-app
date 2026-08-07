<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business profile's request to be verified (ID / CIPC / B-BBEE). One row per
 * submission — a rejected business can resubmit, so history is preserved and the
 * latest row is the current state. Documents live in a child table on the
 * private disk; approving a submission flips profiles.is_verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_verifications', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            // pending | reviewing | approved | rejected
            $table->string('status')->default('pending');
            // Legal/registered details captured at submission for the reviewer.
            $table->string('legal_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            // Rejection reason or approval note, shown back to the member.
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_verifications');
    }
};
