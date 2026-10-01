<?php

namespace App\Enums;

enum ArtworkSource: string
{
    case Embedded = 'embedded';
    case Fetched = 'fetched';
    case Uploaded = 'uploaded';
    case Manual = 'manual';

    /**
     * Whether this source is a machine-detected one that re-scans, re-extraction
     * and the online MusicBrainz backfill are allowed to overwrite.
     *
     * Embedded and Fetched are the auto tier. Uploaded and Manual are user
     * decisions and are permanent until the user removes or resets the artwork,
     * which is what makes a picked cover survive every later scan.
     */
    public function isAuto(): bool
    {
        return $this === self::Embedded || $this === self::Fetched;
    }
}
