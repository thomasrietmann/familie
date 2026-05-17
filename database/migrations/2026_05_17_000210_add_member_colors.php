<?php

use App\Support\MemberColorPalette;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('member_color')->default(MemberColorPalette::DEFAULT)->after('password');
        });

        Schema::table('children', function (Blueprint $table): void {
            $table->string('member_color')->default(MemberColorPalette::DEFAULT)->after('birthdate');
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table): void {
            $table->dropColumn('member_color');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('member_color');
        });
    }
};
