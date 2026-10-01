<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->update(['theme' => 'catppuccin-frappe']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('theme')->nullable()->default('catppuccin-frappe')->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->update(['theme' => 'spotify-dark']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('theme')->nullable()->default('spotify-dark')->change();
        });
    }
};
