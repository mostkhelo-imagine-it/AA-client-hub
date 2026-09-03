<?php
/**
 * Router for PHP's built-in dev server ONLY — `php -S` doesn't read
 * .htaccess (that's an Apache thing), so without this, every URL except
 * "/" 404s directly instead of reaching index.php. Not used in
 * production: cPanel/Apache uses public/.htaccess instead, which does
 * the same job via mod_rewrite.
 *
 * Usage (from the repo root):
 *   php -S localhost:8000 -t public public/router.php
 */

$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $path;

// Let real static files (CSS, images) through as-is.
if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
