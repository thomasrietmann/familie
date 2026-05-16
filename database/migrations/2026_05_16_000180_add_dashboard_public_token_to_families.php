<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->string('dashboard_public_token')->nullable()->unique()->after('public_token_enabled');
            $table->boolean('dashboard_public_token_enabled')->default(false)->after('dashboard_public_token');
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->dropUnique(['dashboard_public_token']);
            $table->dropColumn(['dashboard_public_token', 'dashboard_public_token_enabled']);
        });
    }
};
