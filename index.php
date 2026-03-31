<?php

/**
 * Shared-hosting bootstrap shim.
 *
 * When a host's web root is the project root (e.g. public_html/) rather
 * than project/public/, this file routes all PHP requests through Laravel's
 * real front controller while keeping asset paths correct.
 *
 * On VPS / dedicated installs where the web root is already pointed at
 * public/, this file is never reached and has no effect.
 */

// Resolve the real public directory relative to this shim.
$publicPath = __DIR__.'/public';

// Re-point Laravel's document root so that asset() and url() helpers
// continue to generate correct paths.
$_SERVER['DOCUMENT_ROOT'] = $publicPath;

// Hand off to the real front controller.
require $publicPath.'/index.php';
