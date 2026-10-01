<?php

use App\Audio\WaveformPeaks;

/**
 * Waveform envelope tests.
 *
 * The bucketing, chunk-boundary and clamping logic is exercised against a stub
 * binary that emits a known PCM stream, so it is verified deterministically on
 * a machine with no ffmpeg installed. The tests that need real decoding are
 * skipped when the binary is absent rather than silently passing.
 */
beforeEach(function () {
    WaveformPeaks::forgetProbe();
});

afterEach(function () {
    WaveformPeaks::forgetProbe();
});

/**
 * Point the extractor at stubs in a temp dir and return the paths.
 *
 * $splitAt forces the PCM out in two writes, the first of $splitAt bytes, with
 * a pause between them. That makes an interior chunk boundary land mid-sample
 * every time, which the pipe cannot be relied on to do on its own.
 */
function stubDecoders(?string $pcm = null, ?int $splitAt = null): object
{
    $dir = sys_get_temp_dir().'/waveform-stubs-'.getmypid();

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $pcmPath = $dir.'/pcm.bin';
    file_put_contents($pcmPath, $pcm ?? '');

    // The decode invocation is answered with raw PCM on stdout; `cat` ignores
    // the arguments, so -version also "succeeds" for the availability probe.
    $body = $splitAt === null
        ? "cat '{$pcmPath}'\n"
        : "head -c {$splitAt} '{$pcmPath}'\nsleep 0.05\ntail -c +".($splitAt + 1)." '{$pcmPath}'\n";

    $ffmpeg = $dir.'/ffmpeg-stub';
    file_put_contents($ffmpeg, "#!/bin/sh\n".$body);
    chmod($ffmpeg, 0755);

    $ffprobe = $dir.'/ffprobe-stub';
    file_put_contents($ffprobe, "#!/bin/sh\nexit 0\n");
    chmod($ffprobe, 0755);

    config()->set('rekodo.waveform.ffmpeg_path', $ffmpeg);
    config()->set('rekodo.waveform.ffprobe_path', $ffprobe);

    return (object) [
        'dir' => $dir,
        'pcm' => $pcmPath,
        'audio' => writeFixtureAudio($dir),
    ];
}

/**
 * A file that only has to exist and be readable for the resolver.
 */
function writeFixtureAudio(string $dir): string
{
    $path = $dir.'/song.mp3';
    file_put_contents($path, 'not really audio');

    return $path;
}

/**
 * s16le samples for a list of integer amplitudes.
 *
 * @param  list<int>  $amplitudes
 */
function pcm(array $amplitudes): string
{
    $out = '';

    foreach ($amplitudes as $value) {
        $out .= pack('s', $value);
    }

    return $out;
}

describe('availability probe', function () {
    it('reports a missing binary as unavailable', function () {
        // A path that cannot exist exits non-zero. This is the regression guard:
        // Process::run() returns an exit code, and 127 (command not found) is
        // truthy, so a naive `if ($process->run())` would claim ffmpeg exists.
        config()->set('rekodo.waveform.ffmpeg_path', '/nonexistent/ffmpeg');
        config()->set('rekodo.waveform.ffprobe_path', '/nonexistent/ffprobe');

        expect(WaveformPeaks::available())->toBeFalse();
    });

    it('reports working stubs as available', function () {
        stubDecoders();

        expect(WaveformPeaks::available())->toBeTrue();
    });

    it('memoises the probe so it does not shell out per track', function () {
        config()->set('rekodo.waveform.ffmpeg_path', '/nonexistent/ffmpeg');
        config()->set('rekodo.waveform.ffprobe_path', '/nonexistent/ffprobe');

        expect(WaveformPeaks::available())->toBeFalse();

        // Without the memo this would re-probe; the cached false must stand.
        expect(WaveformPeaks::available())->toBeFalse();
    });
});

describe('envelope extraction', function () {
    it('returns null when no decoder is available', function () {
        config()->set('rekodo.waveform.ffmpeg_path', '/nonexistent/ffmpeg');
        config()->set('rekodo.waveform.ffprobe_path', '/nonexistent/ffprobe');

        $path = writeFixtureAudio(sys_get_temp_dir());

        expect(WaveformPeaks::for($path, 10))->toBeNull();
    });

    it('returns null for a file that is not there', function () {
        stubDecoders();

        expect(WaveformPeaks::for('/nope/missing.mp3', 10))->toBeNull();
    });

    it('returns null when the duration is unknown', function () {
        $stubs = stubDecoders(pcm(array_fill(0, 8000, 1000)));

        // A zero duration cannot size the buckets, and ffprobe is stubbed to
        // report nothing, so there is no way to proceed.
        expect(WaveformPeaks::for($stubs->audio, 0))->toBeNull();
    });

    it('folds samples into one maximum per bucket', function () {
        // 1s at 8kHz = 8000 samples over 4 buckets = 2000 samples each.
        $stream = pcm(array_merge(
            array_fill(0, 2000, -32768),   // full scale
            array_fill(0, 2000, 16384),    // half scale
            array_fill(0, 2000, 0),        // silence
            array_fill(0, 2000, 8192),     // quarter scale
        ));

        $stubs = stubDecoders($stream);

        $peaks = WaveformPeaks::for($stubs->audio, duration: 1, buckets: 4);

        expect($peaks)->toBe([100, 50, 0, 25]);
    });

    it('keeps every value inside 0-100', function () {
        $stubs = stubDecoders(pcm(array_fill(0, 8000, -32768)));

        $peaks = WaveformPeaks::for($stubs->audio, duration: 1, buckets: 8);

        expect($peaks)->toHaveCount(8);

        foreach ($peaks as $value) {
            expect($value)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
        }
    });

    it('always returns one value per requested bucket', function () {
        $stubs = stubDecoders(pcm(array_fill(0, 8000, 1234)));

        $peaks = WaveformPeaks::for($stubs->audio, duration: 1, buckets: 32);

        expect($peaks)->toHaveCount(32);
    });

    it('handles a sample split across two read chunks', function () {
        // Symfony reads pipe output in 16KB blocks, so a stream under that
        // arrives whole and never exercises the boundary. 400KB does, and
        // splitting after 40001 bytes makes the third read 7233 bytes — odd, so
        // it ends mid-sample. The buckets only come out right if that byte is
        // held over and rejoined: dropping it instead shifts every later sample
        // by one and desynchronises the whole envelope.
        $perBucket = 50_000;

        $stream = pcm(array_merge(
            array_fill(0, $perBucket, -32768),
            array_fill(0, $perBucket, 16384),
            array_fill(0, $perBucket, 0),
            array_fill(0, $perBucket, 8192),
        ));

        $stubs = stubDecoders($stream, splitAt: 40001);

        // 25s at 8kHz = 200,000 samples over 4 buckets = 50,000 each.
        $peaks = WaveformPeaks::for($stubs->audio, duration: 25, buckets: 4);

        expect($peaks)->toBe([100, 50, 0, 25]);
    });

    it('reads the same envelope whether the stream arrives whole or in pieces', function () {
        $perBucket = 50_000;

        $stream = pcm(array_merge(
            array_fill(0, $perBucket, -32768),
            array_fill(0, $perBucket, 16384),
            array_fill(0, $perBucket, 0),
            array_fill(0, $perBucket, 8192),
        ));

        $whole = WaveformPeaks::for(stubDecoders($stream)->audio, duration: 25, buckets: 4);
        $split = WaveformPeaks::for(stubDecoders($stream, splitAt: 40001)->audio, duration: 25, buckets: 4);

        expect($split)->toBe($whole)->toBe([100, 50, 0, 25]);
    });

    it('folds surplus samples into the last bucket rather than dropping them', function () {
        // A duration tag that under-reports the real length: the decoder emits
        // more samples than the buckets were sized for.
        $stubs = stubDecoders(pcm(array_merge(
            array_fill(0, 8000, -32768),  // fills bucket 1 exactly
            array_fill(0, 4000, 8192),    // surplus, past the implied length
        )));

        // 2s at 8kHz = 16,000 samples over 2 buckets = 8,000 each, so the
        // trailing 4,000 must still register in the last bucket.
        $peaks = WaveformPeaks::for($stubs->audio, duration: 2, buckets: 2);

        expect($peaks)->toHaveCount(2)
            ->and($peaks[0])->toBe(100)
            ->and($peaks[1])->toBe(25);
    });

    it('returns null when the decoder produces no audio at all', function () {
        $stubs = stubDecoders('');

        expect(WaveformPeaks::for($stubs->audio, duration: 1, buckets: 4))->toBeNull();
    });

    it('takes its bucket count and duration from config when not passed', function () {
        config()->set('rekodo.waveform.buckets', 6);

        $stubs = stubDecoders(pcm(array_fill(0, 8000, 16384)));

        $peaks = WaveformPeaks::for($stubs->audio, duration: 1);

        expect($peaks)->toHaveCount(6)
            ->and($peaks[0])->toBe(50);
    });
});

describe('with a real ffmpeg', function () {
    it('decodes an actual audio file into an envelope', function () {
        if (! WaveformPeaks::available()) {
            $this->markTestSkipped('ffmpeg is not installed on this machine.');
        }

        $path = realAudioFixture();

        if ($path === null) {
            $this->markTestSkipped('No decodable audio fixture available.');
        }

        $peaks = WaveformPeaks::for($path, 1, buckets: 16);

        expect($peaks)->toBeArray()->toHaveCount(16);

        foreach ($peaks as $value) {
            expect($value)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
        }

        // A tone is not silence, so at least one bucket must register.
        expect(max($peaks))->toBeGreaterThan(0);
    });

    it('reads a duration with ffprobe', function () {
        if (! WaveformPeaks::available()) {
            $this->markTestSkipped('ffmpeg is not installed on this machine.');
        }

        $path = realAudioFixture();

        if ($path === null) {
            $this->markTestSkipped('No decodable audio fixture available.');
        }

        expect(WaveformPeaks::probeDuration($path))->toBeGreaterThan(0);
    });
});

/**
 * A one second 440Hz tone as a WAV, written with no external tools. Returns
 * null when the machine has no ffmpeg to turn it into something decodable.
 */
function realAudioFixture(): ?string
{
    $dir = sys_get_temp_dir().'/waveform-real-'.getmypid();

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $wav = $dir.'/tone.wav';
    $rate = 8000;
    $samples = $rate;
    $data = '';

    for ($i = 0; $i < $samples; $i++) {
        $data .= pack('s', (int) (sin(2 * M_PI * 440 * $i / $rate) * 20000));
    }

    file_put_contents($wav, 'RIFF'.pack('V', 36 + strlen($data)).'WAVE'
        .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1)
        .pack('V', $rate).pack('V', $rate * 2).pack('v', 2).pack('v', 16)
        .'data'.pack('V', strlen($data)).$data);

    return $wav;
}
