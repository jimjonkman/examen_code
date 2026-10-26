<?php
declare(strict_types=1);

// Alleen public/ wordt bereikbaar, ook bij het starten van PHP vanuit de projectmap.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
if (preg_match('~(?:^|/)[.]|\\\\|\x00~', $path) || str_contains($path, '..')) {
    http_response_code(404);
    exit('Niet gevonden.');
}
if (str_starts_with($path, '/api/')) {
    require __DIR__ . '/api/index.php';
    return true;
}
if ($path === '/') {
    $path = '/index.html';
}
$file = realpath(__DIR__ . '/public' . $path);
$root = realpath(__DIR__ . '/public') . DIRECTORY_SEPARATOR;
if ($file && str_starts_with($file, $root) && is_file($file) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['html', 'css', 'js', 'json', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'woff', 'woff2', 'ttf', 'ico'], true)) {
    return false;
}
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Deze pagina bestaat niet.';
return true;
