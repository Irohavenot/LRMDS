<?php
function loadEnv($path)
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        // Split key=value
        [$name, $value] = explode('=', $line, 2);

        $name = trim($name);
        $value = trim($value);

        // Remove quotes if present
        $value = trim($value, "\"'");

        // Set environment variables
        putenv("$name=$value");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}