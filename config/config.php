<?php
declare(strict_types=1);

// Een klein .env-bestand houdt productiegeheimen buiten de broncode.
$environmentFile = dirname(__DIR__) . '/.env';
if (is_file($environmentFile)) {
    foreach (file($environmentFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (preg_match('/^[A-Z][A-Z0-9_]*$/D', $key) && getenv($key) === false) {
            $value = trim($value);
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            putenv($key . '=' . $value);
        }
    }
}

return [
    'root' => dirname(__DIR__),
    'app_url' => rtrim(getenv('APP_URL') ?: 'http://127.0.0.1:8080', '/'),
    'environment' => getenv('APP_ENV') ?: 'production',
    'key' => getenv('APP_KEY') ?: '',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Europe/Amsterdam',
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_DATABASE') ?: 'application_portal',
        'username' => getenv('DB_USERNAME') ?: 'portal',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'session_secure' => filter_var(getenv('SESSION_SECURE') ?: 'true', FILTER_VALIDATE_BOOL),
    'retention_days' => max(1, (int) (getenv('RETENTION_DAYS') ?: 28)),
];
