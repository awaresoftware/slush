<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store each track's amplitude envelope as a JSON array of 0-100 values.
     *
     * A `text` column rather than `json` because nothing ever queries inside the
     * array, and SQLite's json type would add a CHECK constraint that buys
     * nothing here. Nullable throughout: a track with no envelope still plays
     * and the player draws its plain scrubber, so a missing ffmpeg never
     * produces a broken row.
     *
     * Existing rows stay null. `tracks:recompute-peaks` backfills them.
     */
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->text('peaks')->nullable()->after('lyrics');
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn('peaks');
        });
    }
};
