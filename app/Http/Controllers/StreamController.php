<?php

namespace App\Http\Controllers;

use App\Enums\SourceType;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StreamController extends Controller
{
    /**
     * Stream an audio track to the authenticated owner.
     */
    public function __invoke(Track $track): BinaryFileResponse|JsonResponse
    {
        Gate::authorize('stream', $track);

        if ($track->source_type === SourceType::Uploaded) {
            $absolutePath = Storage::disk('public')->path($track->file_path);
        } else {
            $track->loadMissing('linkedFolder');

            if (! $track->linkedFolder || ! str_starts_with($track->file_path, $track->linkedFolder->path)) {
                abort(403);
            }

            $absolutePath = $track->file_path;
        }

        if (! file_exists($absolutePath)) {
            $track->update(['is_available' => false]);

            return response()->json(['error' => 'Track file not found.'], 404);
        }

        return response()->file($absolutePath, ['Content-Disposition' => 'inline'])->setAutoEtag();
    }
}
