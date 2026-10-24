<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_imports', function (Blueprint $table): void {
            $table->string('file_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('document_imports')->whereNull('file_path')->update(['file_path' => '']);
        Schema::table('document_imports', function (Blueprint $table): void {
            $table->string('file_path')->nullable(false)->change();
        });
    }
};
