<?php
declare(strict_types=1);

/**
 * Loads .env (a plain KEY=VALUE file, one per line, # for comments) into
 * getenv()/$_ENV without requiring composer or the vlucas/phpdotenv
 * package — keeps the app deployable via a plain File Manager zip upload
 * with zero dependencies.
 */
function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

load_env(dirname(__DIR__) . '/.env');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_URL', getenv('APP_URL') ?: '');
define('WEBHOOK_SECRET', getenv('WEBHOOK_SECRET') ?: '');

if (APP_ENV === 'local') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}
