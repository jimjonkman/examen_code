<?php
declare(strict_types=1);

try {
    require dirname(__DIR__) . '/src/bootstrap.php';
    $path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'POST' && ($_POST['_method'] ?? '') === 'PATCH') {
        $method = 'PATCH';
    }
    $path = preg_replace('~^/api/auth/(login|logout|set-password|verify-email)\.php$~', '/api/auth/$1', $path);
    if (in_array($path, ['/api/companies', '/api/theme.php', '/api/session'], true) && $method !== 'GET') {
        header('Allow: GET');
        Http::error('Deze methode is niet toegestaan.', 405);
    }
    if (in_array($path, ['/api/auth/login', '/api/auth/logout', '/api/auth/set-password', '/api/auth/verify-email'], true) && $method !== 'POST') {
        header('Allow: POST');
        Http::error('Deze methode is niet toegestaan.', 405);
    }
    if ($method === 'GET' && $path === '/api/companies') {
        $companies = Database::connection()->query('SELECT name, slug FROM companies WHERE is_active = 1 ORDER BY name')->fetchAll();
        Http::json(['companies' => $companies]);
    }
    if ($method === 'GET' && $path === '/api/theme.php') {
        $company = Http::company();
        Http::json(['company' => ['id' => $company['id'], 'name' => $company['name'], 'slug' => $company['slug']], 'theme' => Theme::read($company['slug'])]);
    }
    if ($method === 'GET' && $path === '/api/session') {
        Auth::session();
    }
    if ($method === 'POST' && str_starts_with($path, '/api/auth/')) {
        match ($path) {
            '/api/auth/login' => Auth::login(),
            '/api/auth/logout' => Auth::logout(),
            '/api/auth/set-password' => Auth::setPassword(),
            '/api/auth/verify-email' => Auth::verifyEmail(),
            default => Http::error('Dit API-adres bestaat niet.', 404),
        };
    }
    if ($path === '/api/applications') {
        match ($method) {
            'GET' => ApplicationController::index(),
            'POST' => ApplicationController::create(),
            default => Http::error('Deze methode is niet toegestaan.', 405),
        };
    }
    if (preg_match('~^/api/applications/([1-9][0-9]*)$~D', $path, $matches)) {
        match ($method) {
            'GET' => ApplicationController::show((int) $matches[1]),
            'PATCH' => ApplicationController::review((int) $matches[1]),
            default => Http::error('Deze methode is niet toegestaan.', 405),
        };
    }
    if ($method === 'GET' && preg_match('~^/api/documents/([1-9][0-9]*)$~D', $path, $matches)) {
        ApplicationController::download((int) $matches[1]);
    }
    if ($method === 'DELETE' && $path === '/api/account') {
        Auth::deleteAccount();
    }
    if ($path === '/api/account/department') {
        match ($method) {
            'PATCH' => Auth::updateDepartment(),
            default => Http::error('Deze methode is niet toegestaan.', 405),
        };
    }
    Http::error('Dit API-adres bestaat niet.', 404);
} catch (HttpException $exception) {
    http_response_code($exception->status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'data' => null, 'error' => ['message' => $exception->getMessage()]], JSON_UNESCAPED_UNICODE);
} catch (DomainException $exception) {
    http_response_code(in_array($exception->getCode(), [400, 401, 403, 404, 409, 422], true) ? $exception->getCode() : 422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'data' => null, 'error' => ['message' => $exception->getMessage()]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    $reference = bin2hex(random_bytes(6));
    // Geen SQL, e-mails, formulierwaarden of tokens naar de logbestanden schrijven.
    error_log('Portal error ' . $reference . ' [' . get_class($exception) . ']');
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'data' => null, 'error' => ['message' => 'Het verzoek kon niet worden verwerkt. Probeer het later opnieuw.', 'reference' => $reference]], JSON_UNESCAPED_UNICODE);
}
