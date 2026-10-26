<?php
declare(strict_types=1);

$GLOBALS['config'] = require dirname(__DIR__) . '/config/config.php';
date_default_timezone_set('UTC');
ini_set('display_errors', '0');
spl_autoload_register(static function (string $class): void {
    if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $class)) {
        $path = __DIR__ . '/' . $class . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store');
    if ($GLOBALS['config']['session_secure']) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    session_name('portal_session');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $GLOBALS['config']['session_secure'],
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    $sessionDirectory = $GLOBALS['config']['root'] . '/storage/runtime/sessions';
    if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) {
        throw new RuntimeException('Sessiemap is niet beschikbaar.');
    }
    session_save_path($sessionDirectory);
    if (!session_start()) {
        throw new RuntimeException('Sessie kon niet worden gestart.');
    }
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}
