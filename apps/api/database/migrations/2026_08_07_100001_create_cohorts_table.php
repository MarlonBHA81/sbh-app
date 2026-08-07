<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cohort (intake) within a programme: a dated group of suppliers with an
 * optional capacity. Supplier enrolments (ESD-2) attach to a cohort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohorts', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('programme_id')->constrained('programmes')->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            // active | closed
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('programme_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohorts');
    }
};
