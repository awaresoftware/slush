<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A playlist with rules is smart: its tracks are resolved from the rules
     * at read time instead of the pivot table.
     */
    public function up(): void
    {
        Schema::table('playlists', function (Blueprint $table) {
            $table->json('rules')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('playlists', function (Blueprint $table) {
            $table->dropColumn('rules');
        });
    }
};
