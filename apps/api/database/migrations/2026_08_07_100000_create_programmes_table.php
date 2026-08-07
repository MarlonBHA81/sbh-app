<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An ESD (Enterprise & Supplier Development) programme run by a corporate
 * sponsor (a profile of kind=corporate). The umbrella under which cohorts of
 * suppliers are onboarded, developed and reported on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            // The corporate profile that owns/runs this programme.
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // enterprise_development | supplier_development
            $table->string('type')->default('supplier_development');
            // draft | active | closed
            $table->string('status')->default('draft');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedBigInteger('budget_cents')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes');
    }
};
