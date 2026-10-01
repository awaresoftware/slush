<?php

namespace App\Audio;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Builds a short amplitude envelope for a track so the player can draw a
 * waveform without decoding audio in the browser.
 *
 * One ffmpeg pass decodes to 8kHz mono s16le and the samples are folded into
 * fixed buckets, keeping the largest magnitude in each. Memory stays flat
 * because stdout is consumed in chunks as ffmpeg produces it and only the
 * running bucket maxima are retained.
 *
 * ponytail: per-bucket max ignores peaks between the samples that land in a
 * bucket, so a track with a single sharp transient between two quiet moments
 * draws slightly fuller than it sounds. That is fine for a seek surface and
 * costs one pass; the upgrade path is RMS-per-bucket, which smooths the
 * envelope but drops exactly the transients that make a waveform recognisable.
 */
class WaveformPeaks
{
    /**
     * Cache the probe result: the binary cannot appear mid-request, and the
     * extraction job checks availability on every track it touches.
     */
    private static ?bool $available = null;

    /**
     * Whether an ffmpeg binary is usable.
     */
    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        return self::$available = self::runs([self::binary('ffprobe_path'), '-version'])
            && self::runs([self::binary('ffmpeg_path'), '-version']);
    }

    /**
     * Forget the memoised probe so the next call re-checks the binary.
     *
     * Only tests need this: nothing changes about the host's ffmpeg halfway
     * through a request.
     */
    public static function forgetProbe(): void
    {
        self::$available = null;
    }

    /**
     * Run a command and report success, not its exit code.
     *
     * Process::run() returns the exit code, and a missing binary exits 127 —
     * which is truthy, so the result must be compared to zero rather than used
     * as a boolean or an absent ffmpeg looks installed.
     *
     * @param  list<string>  $command
     */
    private static function runs(array $command): bool
    {
        return (new Process($command))->setTimeout(10)->run() === 0;
    }

    /**
     * The envelope for a track, or null when it cannot be produced.
     *
     * Returning null is a normal outcome, not an error: an unreachable file, an
     * unsupported codec or a missing ffmpeg all leave the track playable with a
     * plain scrubber.
     *
     * @return list<int>|null Envelope values scaled 0-100.
     */
    public static function for(string $absolutePath, ?int $duration = null, ?int $buckets = null): ?array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        if (! self::available()) {
            return null;
        }

        $buckets ??= (int) config('rekodo.waveform.buckets', 200);
        $sampleRate = (int) config('rekodo.waveform.sample_rate', 8000);

        $duration ??= self::probeDuration($absolutePath);

        if ($duration === null || $duration <= 0) {
            return null;
        }

        $samplesPerBucket = max(1, (int) ceil(($duration * $sampleRate) / $buckets));

        $peaks = array_fill(0, $buckets, 0);
        $seen = 0;
        $sample = 0;
        $carry = '';

        $process = new Process([
            self::binary('ffmpeg_path'),
            '-v', 'quiet',
            '-nostdin',
            '-i', $absolutePath,
            '-ac', '1',
            '-ar', (string) $sampleRate,
            '-f', 's16le',
            '-',
        ]);

        // A long file is legitimately slow to decode; the job timeout, not this,
        // is the bound that matters.
        $process->setTimeout(null);

        $exit = $process->run(
            function (string $type, string $buffer) use (&$peaks, &$seen, &$sample, &$carry, $samplesPerBucket): void {
                if ($type !== Process::OUT) {
                    return;
                }

                // Chunk boundaries can split a 16-bit sample, so an odd-length
                // chunk has its trailing byte held over to join the next one.
                // The length has to be tested rather than assumed: holding a byte
                // from an even-length chunk would drop it and shift every
                // sample after it.
                $data = $carry.$buffer;
                $usable = strlen($data) - (strlen($data) % 2);
                $carry = substr($data, $usable);
                $data = substr($data, 0, $usable);

                if ($data === '') {
                    return;
                }

                // unpack() decodes the whole chunk in C; a per-sample substr loop
                // over a 90-minute file runs to tens of millions of iterations.
                foreach (unpack('s*', $data) as $value) {
                    $bucket = intdiv($sample, $samplesPerBucket);

                    if ($bucket >= count($peaks)) {
                        // ffmpeg emitted more samples than the duration implied
                        // (a bad duration tag). The surplus is folded into the
                        // last bucket rather than dropped, so the tail of the
                        // waveform still carries some signal.
                        $bucket = count($peaks) - 1;
                    }

                    $magnitude = (int) round(abs($value) / 32768 * 100);

                    if ($magnitude > $peaks[$bucket]) {
                        $peaks[$bucket] = $magnitude;
                    }

                    $sample++;
                    $seen++;
                }
            }
        );

        if ($exit !== 0 || $seen === 0) {
            Log::warning('Waveform extraction produced no audio.', [
                'path' => $absolutePath,
                'exit' => $exit,
                'error' => mb_substr(trim($process->getErrorOutput()), 0, 300),
            ]);

            return null;
        }

        return $peaks;
    }

    /**
     * Read a file's duration in whole seconds, or null when ffprobe cannot.
     */
    public static function probeDuration(string $absolutePath): ?int
    {
        $process = new Process([
            self::binary('ffprobe_path'),
            '-v', 'quiet',
            '-show_entries', 'format=duration',
            '-of', 'csv=p=0',
            $absolutePath,
        ]);

        $process->setTimeout(30);

        if ($process->run() !== 0) {
            return null;
        }

        $seconds = (float) trim($process->getOutput());

        return $seconds > 0 ? (int) round($seconds) : null;
    }

    /**
     * A configured binary path, or the bare name to be resolved from the PATH.
     */
    private static function binary(string $key): string
    {
        $path = (string) config('rekodo.waveform.'.$key, 'ffmpeg');

        return $path !== '' ? $path : 'ffmpeg';
    }
}
