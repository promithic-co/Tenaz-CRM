<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersionController extends Controller
{
    /**
     * Return build/version metadata for the running application.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $token = config('credflow.version_endpoint_token');
        $tokenOk = $token && hash_equals((string) $token, (string) $request->header('X-Version-Token'));

        if (! $tokenOk && ! auth()->check()) {
            abort(404);
        }

        $sha = config('credflow.build.sha');
        $tag = config('credflow.build.tag');
        $buildTime = config('credflow.build.time');

        // Fallback: attempt to read from .git when available (often absent on production).
        if (! $sha && is_file(base_path('.git/HEAD'))) {
            $head = trim((string) file_get_contents(base_path('.git/HEAD')));

            if (str_starts_with($head, 'ref: ')) {
                $ref = trim(substr($head, 5));
                $refPath = base_path('.git/'.$ref);
                if (is_file($refPath)) {
                    $sha = trim((string) file_get_contents($refPath));
                }
            } elseif (preg_match('/^[0-9a-f]{7,40}$/i', $head)) {
                $sha = $head;
            }
        }

        // Fallback: derive an approximate "build time" from Vite manifest mtime.
        if (! $buildTime && is_file(public_path('build/manifest.json'))) {
            $ts = filemtime(public_path('build/manifest.json'));
            if ($ts) {
                $buildTime = gmdate('c', $ts);
            }
        }

        return response()->json([
            'app' => config('app.name'),
            'env' => config('app.env'),
            'sha' => $sha,
            'tag' => $tag,
            'build_time' => $buildTime,
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
