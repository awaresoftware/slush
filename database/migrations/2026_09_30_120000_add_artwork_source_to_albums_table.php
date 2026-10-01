<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record where each album's cover art came from.
     *
     * Existing rows are backfilled to 'fetched': the only writers that exist
     * today are embedded extraction and the MusicBrainz/Cover Art Archive
     * fetch, and neither can be told apart retroactively, so Fetched is the
     * honest neutral label. It also matters that the value is an *auto*-tier
     * one — the manual-wins guard in Album::storeCoverArt can then never trip
     * on a legacy row and refuse a legitimate refresh.
     */
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->string('artwork_source')->nullable()->after('cover_art_path');
        });

        DB::table('albums')
            ->whereNotNull('cover_art_path')
            ->update(['artwork_source' => 'fetched']);
    }

    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn('artwork_source');
        });
    }
};
