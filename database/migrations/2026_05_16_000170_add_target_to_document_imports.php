<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_imports', function (Blueprint $table): void {
            $table->string('target_type')->default('family')->after('status');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::table('document_imports', function (Blueprint $table): void {
            $table->dropIndex(['target_type', 'target_id']);
            $table->dropColumn(['target_type', 'target_id']);
        });
    }
};
