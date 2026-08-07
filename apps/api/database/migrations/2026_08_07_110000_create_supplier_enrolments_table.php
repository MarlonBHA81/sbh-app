<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A supplier business enrolled in a cohort of an ESD programme. A rich pivot
 * with its own review state machine: invited/applied → accepted → active →
 * completed (or withdrawn/rejected). One row per (cohort, supplier).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_enrolments', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('cohort_id')->constrained('cohorts')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('status')->default('invited');
            $table->timestamp('enrolled_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cohort_id', 'profile_id']);
            $table->index(['cohort_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_enrolments');
    }
};
