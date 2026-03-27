<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves files from storage/app/public/ when the storage symlink cannot be
 * created (e.g., shared hosting environments that disable symlink()).
 *
 * Only active when APP_STORAGE_MODE=controller (set by installer on fallback).
 * On VPS/dedicated where public/storage symlink exists, these routes are
 * never registered and add zero overhead.
 */
class StorageController extends Controller
{
    public function serve(Request $request, string $path): StreamedResponse
    {
        // Prevent path traversal
        $path = ltrim($path, '/');
        if (str_contains($path, '..')) {
            abort(403);
        }

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        return response()->stream(function () use ($path) {
            $stream = Storage::disk('public')->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
