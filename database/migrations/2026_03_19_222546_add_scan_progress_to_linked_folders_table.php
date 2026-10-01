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
        Schema::table('linked_folders', function (Blueprint $table) {
            $table->unsignedInteger('scanned_count')->default(0)->after('unavailable_count');
            $table->unsignedInteger('total_count')->default(0)->after('scanned_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('linked_folders', function (Blueprint $table) {
            $table->dropColumn(['scanned_count', 'total_count']);
        });
    }
};
