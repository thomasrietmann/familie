<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_sender_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('email', 254)->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consented_at');
            $table->string('verification_hash', 64)->nullable();
            $table->timestamp('verification_expires_at')->nullable();
            $table->unsignedTinyInteger('verification_attempts')->default(0);
            $table->timestamps();
        });
        Schema::create('calendar_mail_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('delivery_key', 64)->unique();
            $table->foreignId('family_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('result_code')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('retry_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('calendar_mail_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('series_key', 64);
            $table->string('occurrence_key', 64);
            $table->foreignId('family_event_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->dateTime('revision_at');
            $table->boolean('cancelled')->default(false);
            $table->boolean('was_imported')->default(false);
            $table->timestamps();
            $table->unique(['family_id', 'series_key', 'occurrence_key'], 'calendar_mail_event_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_mail_events');
        Schema::dropIfExists('calendar_mail_receipts');
        Schema::dropIfExists('calendar_sender_addresses');
    }
};
