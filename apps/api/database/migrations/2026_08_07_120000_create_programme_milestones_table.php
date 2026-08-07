<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Development deliverables tracked per supplier enrolment (e.g. "obtain tax
 * clearance", "complete financial-management course"). Each has a simple
 * pending → complete lifecycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_milestones', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('supplier_enrolment_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->timestamp('due_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['supplier_enrolment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_milestones');
    }
};
