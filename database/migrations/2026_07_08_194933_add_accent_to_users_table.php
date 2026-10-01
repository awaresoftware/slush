<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the accent colour preference and move every user to the new Auto
     * theme default. Frappé remains selectable in Appearance settings.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('accent')->nullable()->default('music-red')->after('theme');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('theme')->nullable()->default('system')->change();
        });

        DB::table('users')->update([
            'theme' => 'system',
            'accent' => 'music-red',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->update(['theme' => 'catppuccin-frappe']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('theme')->nullable()->default('catppuccin-frappe')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('accent');
        });
    }
};
