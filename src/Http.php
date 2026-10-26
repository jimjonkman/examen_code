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
        throw new HttpException($message, $status);
    }

    public static function company(): array
    {
        $slug = $_GET['company'] ?? '';
        if (!is_string($slug) || strlen($slug) > 80 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            self::error('Open het portaal via een geldige bedrijfslink.', 404);
        }
        $query = Database::connection()->prepare('SELECT id, name, slug FROM companies WHERE slug = ? AND is_active = 1');
        $query->execute([$slug]);
        $company = $query->fetch();
        if (!$company) {
            self::error('Dit bedrijfsportaal is niet beschikbaar.', 404);
        }
        return $company;
    }
}
