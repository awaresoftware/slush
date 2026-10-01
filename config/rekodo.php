<?php

return [
    'scan_batch_size' => (int) env('SCAN_BATCH_SIZE', 200),

    /*
    |----------------------------------------------------------------------
    | Folder Scanning
    |----------------------------------------------------------------------
    |
    | The extensions a folder scan treats as playable audio. Anything else in
    | the tree is skipped, so this list is the only thing deciding what enters
    | the library — an unlisted format is not imported, silently.
    |
    | `.m4p` is deliberately absent. Those are DRM'd iTunes Store purchases:
    | ffprobe reads their duration but their tags are scrambled, so getID3
    | returns no title or artist and they would land as "Unknown Artist" under
    | the folder name. Add it here if you want them anyway.
    |
    */

    'audio_extensions' => array_values(array_filter(array_map(
        fn (string $extension): string => strtolower(trim($extension)),
        explode(',', (string) env('SCAN_AUDIO_EXTENSIONS', 'mp3,wav,flac,m4a,aac,ogg'))
    ))),

    /*
    |----------------------------------------------------------------------
    | Waveform
    |----------------------------------------------------------------------
    |
    | The player draws a waveform from a short amplitude envelope stored per
    | track, not from decoded audio, so the file is decoded exactly once — when
    | its metadata is extracted. See App\Audio\WaveformPeaks.
    |
    | `ffmpeg_path` is resolved from the environment first so a host install
    | outside the PATH Herd hands the queue worker still works. When the
    | binary is missing, extraction is skipped and the player falls back to its
    | plain scrubber; nothing fails and nothing is left half-written.
    |
    */

    'waveform' => [
        'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),

        'ffprobe_path' => env('FFPROBE_PATH', 'ffprobe'),

        // Envelope resolution. 200 buckets sits around 2-3px per bar across the
        // widest scrubber in the app; more costs payload for no visible gain.
        'buckets' => (int) env('WAVEFORM_BUCKETS', 200),

        // Decoding rate for the envelope. An amplitude envelope does not need
        // the top of the spectrum, and 8kHz caps a long file's piped output at
        // roughly 1MB a minute instead of 10MB.
        'sample_rate' => 8000,
    ],
];
