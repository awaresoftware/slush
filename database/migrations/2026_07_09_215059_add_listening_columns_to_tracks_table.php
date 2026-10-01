<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->unsignedInteger('play_count')->default(0)->after('is_available');
            $table->timestamp('last_played_at')->nullable()->after('play_count');
            $table->timestamp('favorited_at')->nullable()->after('last_played_at');
            $table->text('lyrics')->nullable()->after('favorited_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn(['play_count', 'last_played_at', 'favorited_at', 'lyrics']);
        });
    }
};
