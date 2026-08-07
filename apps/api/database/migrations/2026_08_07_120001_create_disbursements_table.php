<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ED/SD spend recorded per supplier enrolment. disbursed_at distinguishes a
 * planned line (null) from an actual payment (set), so a programme can report
 * committed-vs-paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('supplier_enrolment_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('ZAR');
            $table->string('kind')->default('grant');
            $table->timestamp('disbursed_at')->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('supplier_enrolment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursements');
    }
};
