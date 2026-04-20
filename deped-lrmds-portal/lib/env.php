<?php
/**
 * DepEd LRMDS – lib/env.php
 *
 * Lightweight .env file loader.
 * Reads key=value pairs from the project root .env file
 * and puts them into $_ENV so any PHP file can call env().
 *
 * Usage (at the top of any PHP file):
 *   require_once __DIR__ . '/lib/env.php';
 *   $host = env('DB_HOST', 'localhost');
 */

function load_env(string $path): void
{
    if (!file_exists($path)) {
        // .env missing — warn clearly so developers know what to do
        trigger_error(
            '.env file not found at ' . $path . '. ' .
            'Copy .env.example to .env and fill in your values.',
            E_USER_WARNING
        );
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (str_starts_with(trim($line), '#')) continue;

        // Split on first = only
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;

        $key   = trim($parts[0]);
        $value = trim($parts[1]);

        // Strip inline comments  (e.g.  KEY=value  # comment)
        if (str_contains($value, ' #')) {
            $value = trim(explode(' #', $value, 2)[0]);
        }

        // Strip surrounding quotes if present
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Only set if not already defined by the server environment
        if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

/**
 * Get an environment variable with an optional default.
 *
 * @param  string $key
 * @param  string|null $default
 * @return string
 */
function env(string $key, ?string $default = null): string
{
    $value = $_ENV[$key] ?? getenv($key) ?? $default;
    return (string) $value;
}

// Auto-load from project root when this file is included
load_env(dirname(__DIR__) . '/.env');