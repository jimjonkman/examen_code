<?php
declare(strict_types=1);

final class Http
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $data, 'error' => null], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function error(string $message, int $status = 400): never
    {
        // Exceptions rollen eerst de transactie terug; een directe exit zou dat omzeilen.
        throw new HttpException($message, $status);
    }

    public static function input(): array
    {
        if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            try {
                $value = json_decode(file_get_contents('php://input') ?: '{}', true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                self::error('Het verzoek bevat geen geldige JSON.', 400);
            }
            if (!is_array($value) || array_is_list($value) && $value !== []) {
                self::error('Verwacht een JSON-object.', 400);
            }
            return $value;
        }
        return $_POST;
    }

    public static function company(): array
    {
        $slug = $_GET['company'] ?? '';
        if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 80) {
            self::error('Open het portaal via een geldige bedrijfslink.', 404);
        }
        $query = Database::connection()->prepare('SELECT * FROM companies WHERE slug = ? AND is_active = 1');
        $query->execute([$slug]);
        $company = $query->fetch();
        if (!$company) {
            self::error('Dit bedrijfsportaal is niet beschikbaar.', 404);
        }
        return $company;
    }

    public static function currentUser(): ?array
    {
        if (!isset($_SESSION['user_id'], $_SESSION['company_id'])) {
            return null;
        }
        $query = Database::connection()->prepare('SELECT id, company_id, role, name, email, department, must_change_password, email_verified_at FROM users WHERE id = ? AND company_id = ? AND is_active = 1');
        $query->execute([$_SESSION['user_id'], $_SESSION['company_id']]);
        return $query->fetch() ?: null;
    }

    public static function requireUser(array $roles): array
    {
        $company = self::company();
        $user = self::currentUser();
        if ($user === null) {
            self::error('Log eerst in om verder te gaan.', 401);
        }
        if ((int) $user['company_id'] !== (int) $company['id'] || !in_array($user['role'], $roles, true)) {
            self::error('Je hebt geen toegang tot dit onderdeel.', 403);
        }
        if ((bool) $user['must_change_password']) {
            self::error('Stel eerst een nieuw wachtwoord in via de toegangslink.', 403);
        }
        return $user;
    }

    public static function csrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            self::error('Je sessie is verlopen. Vernieuw de pagina en probeer opnieuw.', 419);
        }
    }

    public static function limit(string $scope, int $maximum, int $seconds): void
    {
        // Alleen een hash van het IP-adres wordt bewaard; proxyheaders zijn niet vertrouwd.
        $slug = $_GET['company'] ?? '';
        if (!is_string($slug)) {
            self::error('Open het portaal via een geldige bedrijfslink.', 404);
        }
        $key = hash('sha256', $scope . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli') . '|' . $slug);
        $start = gmdate('Y-m-d H:i:s', time() - $seconds);
        $query = Database::connection()->prepare('INSERT INTO rate_limits (rate_key, window_start, hits) VALUES (?, UTC_TIMESTAMP(), 1) ON DUPLICATE KEY UPDATE hits = IF(window_start < ?, 1, hits + 1), window_start = IF(window_start < ?, UTC_TIMESTAMP(), window_start)');
        $query->execute([$key, $start, $start]);
        $query = Database::connection()->prepare('SELECT hits FROM rate_limits WHERE rate_key = ?');
        $query->execute([$key]);
        if ((int) $query->fetchColumn() > $maximum) {
            header('Retry-After: ' . $seconds);
            self::error('Te veel pogingen. Probeer het later opnieuw.', 429);
        }
    }

    public static function url(string $path, array $query = []): string
    {
        return $GLOBALS['config']['app_url'] . '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query) : '');
    }

    public static function tokenUrl(string $path, string $slug, string $token): string
    {
        // Het fragment blijft in de browser en verschijnt niet in HTTP-toegangslogs.
        return self::url($path, ['company' => $slug]) . '#token=' . rawurlencode($token);
    }

    public static function string(array $input, string $key): string
    {
        if (isset($input[$key]) && !is_string($input[$key])) {
            self::error('Een invoerveld heeft een ongeldig formaat.', 422);
        }
        return $input[$key] ?? '';
    }
}
