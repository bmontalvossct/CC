<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// 1. Direct static file in public/ (CSS, JS, images, fonts)
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri) && !is_dir(__DIR__.'/public'.$uri)) {
    return false;
}

// 2. Direct storage files (student photos, course modules) without requiring Windows symlinks
if (str_starts_with($uri, '/storage/')) {
    $storageFile = __DIR__.'/storage/app/public/'.substr($uri, 9);
    if (file_exists($storageFile) && !is_dir($storageFile)) {
        $mime = mime_content_type($storageFile) ?: 'application/octet-stream';
        header('Content-Type: '.$mime);
        header('Content-Length: '.filesize($storageFile));
        readfile($storageFile);
        exit;
    }
}

// 3. Fallback to Laravel front controller
require_once __DIR__.'/public/index.php';
