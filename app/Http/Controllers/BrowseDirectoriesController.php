<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrowseDirectoriesController extends Controller
{
    /**
     * Return a JSON listing of directories within the given path.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $requestedPath = $request->query('path');

        $currentPath = $requestedPath ?? $this->defaultDirectory();

        $currentPath = rtrim($currentPath, '/') ?: '/';

        if (! is_dir($currentPath) || ! is_readable($currentPath)) {
            return response()->json([
                'message' => 'The path must be a readable directory.',
                'errors' => ['path' => ['The path must be a readable directory.']],
            ], 422);
        }

        $parentPath = ($currentPath === '/') ? null : dirname($currentPath);

        $entries = scandir($currentPath);

        $directories = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (str_starts_with($entry, '.')) {
                continue;
            }

            $fullPath = $currentPath.'/'.$entry;

            if (! is_dir($fullPath)) {
                continue;
            }

            $directories[] = [
                'name' => $entry,
                'path' => $fullPath,
            ];
        }

        usort($directories, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return response()->json([
            'current_path' => $currentPath,
            'parent_path' => $parentPath,
            'directories' => $directories,
        ]);
    }

    /**
     * Determine the default starting directory for the current process user.
     */
    private function defaultDirectory(): string
    {
        if (! empty($_SERVER['HOME'])) {
            return $_SERVER['HOME'];
        }

        if (function_exists('posix_getuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_getuid());

            if ($info && ! empty($info['dir'])) {
                return $info['dir'];
            }
        }

        return '/';
    }
}
