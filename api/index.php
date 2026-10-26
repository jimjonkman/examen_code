<?php
declare(strict_types=1);

try {
    require dirname(__DIR__) . '/src/bootstrap.php';
    $path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
    if (!in_array($path, ['/api/companies', '/api/theme.php'], true)) {
        Http::error('Dit API-adres bestaat niet.', 404);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        header('Allow: GET');
        Http::error('Deze methode is niet toegestaan.', 405);
    }
    if ($path === '/api/companies') {
        $companies = Database::connection()->query('SELECT name, slug FROM companies WHERE is_active = 1 ORDER BY name')->fetchAll();
        Http::json(['companies' => $companies]);
    }
    $company = Http::company();
    Http::json(['company' => $company, 'theme' => Theme::read($company['slug'])]);
} catch (Throwable $exception) {
    $status = $exception instanceof HttpException ? $exception->status : 503;
    if ($status === 503) {
        error_log($exception::class . ': portaalverzoek mislukt.');
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'data' => null, 'error' => ['message' => $status === 503 ? 'Het portaal is tijdelijk niet beschikbaar. Probeer het later opnieuw.' : $exception->getMessage()]], JSON_UNESCAPED_UNICODE);
}
