<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per counted listen. Rows drive "recently played" ordering and
     * time-windowed stats; the denormalised counters on tracks keep whole-
     * library sorting cheap.
     */
    public function up(): void
    {
        Schema::create('track_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->timestamp('played_at');

            $table->index(['user_id', 'played_at']);
            $table->index(['track_id', 'played_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('track_plays');
    }
};
