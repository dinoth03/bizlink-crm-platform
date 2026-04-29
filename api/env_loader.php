<?php
/**
 * Environment Variables Loader
 * Loads variables from .env file if it exists
 */

function loadEnvFile($filePath = null) {
    if ($filePath === null) {
        $filePath = dirname(__FILE__) . '/../.env';
    }

    if (!file_exists($filePath)) {
        return false;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments and empty lines
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            $value = trim($value, '\'"');

            // Set to environment
            if (!getenv($key)) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }

    return true;
}

// Load .env file on initialization
loadEnvFile();

?>
