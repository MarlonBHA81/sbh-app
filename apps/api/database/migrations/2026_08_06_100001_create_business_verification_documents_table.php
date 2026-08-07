<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The uploaded documents attached to a business-verification submission. Files
 * are stored on the private disk (config('media.private_disk')) and are only
 * ever streamed to an admin reviewer through a gated, ownership-checked route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_verification_documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('business_verification_id')->constrained()->cascadeOnDelete();
            // id_document | cipc | bbee
            $table->string('type');
            $table->string('disk');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_verification_documents');
    }
};
