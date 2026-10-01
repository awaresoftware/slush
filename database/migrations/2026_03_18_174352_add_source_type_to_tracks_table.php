<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->string('source_type')->default('uploaded')->after('genre');
            $table->foreignId('linked_folder_id')->nullable()->after('source_type')->constrained('linked_folders')->cascadeOnDelete();
        });

        DB::table('tracks')->whereNull('source_type')->update(['source_type' => 'uploaded']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropForeign(['linked_folder_id']);
            $table->dropColumn(['source_type', 'linked_folder_id']);
        });
    }
};
