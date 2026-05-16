<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imported_event_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->string('category')->nullable();
            $table->string('suggested_owner_type')->nullable();
            $table->unsignedBigInteger('suggested_owner_id')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->index(['family_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_event_suggestions');
    }
};
