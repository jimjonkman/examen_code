<?php

declare(strict_types=1);

final class Theme
{
    public static function read(string $slug): array
    {
        $directory = self::directory($slug);
        $theme = self::defaults();
        $file = $directory . '/theme.json';
        if (is_file($file) && filesize($file) <= 16384) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $theme = self::validate($decoded, $directory, false);
            }
        }
        $theme['logo_url'] = $theme['logo'] ? '/themes/' . rawurlencode($slug) . '/' . rawurlencode($theme['logo']) : null;
        $theme['font_url'] = $theme['font_file'] ? '/themes/' . rawurlencode($slug) . '/' . rawurlencode($theme['font_file']) : null;
        return $theme;
    }

    public static function save(string $slug, array $values): array
    {
        $directory = self::directory($slug);
        $theme = self::validate($values, $directory, true);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('De themamap kon niet worden aangemaakt.');
        }
        // Een vervangend bestand voorkomt dat gelijktijdige lezers onvolledige JSON krijgen.
        $temporary = $directory . '/.theme-' . bin2hex(random_bytes(8)) . '.tmp';
        try {
            if (file_put_contents($temporary, json_encode($theme, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n", LOCK_EX) === false) {
                throw new RuntimeException('Het thema kon niet worden opgeslagen.');
            }
            if (!rename($temporary, $directory . '/theme.json')) {
                throw new RuntimeException('Het thema kon niet worden vervangen.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
        return self::read($slug);
    }

    public static function rename(string $oldSlug, string $newSlug): void
    {
        if ($oldSlug === $newSlug) {
            return;
        }
        $oldDirectory = self::directory($oldSlug);
        $newDirectory = self::directory($newSlug);
        // De unieke database-slug is al gecontroleerd. Een achtergebleven map na
        // een mislukte wijziging mag een nieuwe poging niet permanent blokkeren.
        if (!is_dir($newDirectory) && !mkdir($newDirectory, 0755, true) && !is_dir($newDirectory)) {
            throw new RuntimeException('De nieuwe themamap kon niet worden aangemaakt.');
        }
        $theme = self::read($oldSlug);
        foreach (['logo', 'font_file'] as $key) {
            if ($theme[$key] && !copy($oldDirectory . '/' . $theme[$key], $newDirectory . '/' . $theme[$key])) {
                throw new RuntimeException('Een themabestand kon niet worden gekopieerd.');
            }
        }
        self::save($newSlug, $theme);
        // De oude map blijft bestaan als herstelkopie; de database bepaalt welke slug actief is.
    }

    public static function validSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) && strlen($slug) <= 80;
    }

    private static function directory(string $slug): string
    {
        if (!self::validSlug($slug)) {
            throw new DomainException('Gebruik voor de slug alleen kleine letters, cijfers en enkele koppeltekens.', 422);
        }
        $base = dirname(__DIR__) . '/public/themes';
        $directory = $base . '/' . $slug;
        // Een beheerder mag nooit via een symlink buiten de toegestane themamap schrijven.
        if (is_link(dirname($base)) || is_link($base) || is_link($directory)) {
            throw new RuntimeException('De themamap is ongeldig.');
        }
        return $directory;
    }

    private static function defaults(): array
    {
        return ['primary_color' => '#263444', 'secondary_color' => '#52657b', 'font_family' => 'system-ui', 'font_file' => null, 'logo' => null];
    }

    private static function validate(array $values, string $directory, bool $strict): array
    {
        $result = self::defaults();
        foreach (['primary_color', 'secondary_color'] as $key) {
            if (isset($values[$key]) && is_string($values[$key]) && preg_match('/^#[a-fA-F0-9]{6}$/D', $values[$key])) {
                $result[$key] = strtolower((string) $values[$key]);
            } elseif ($strict && isset($values[$key]) && $values[$key] !== '') {
                throw new DomainException('Kleuren moeten zes hexadecimale cijfers bevatten, bijvoorbeeld #263444.', 422);
            }
        }
        if (isset($values['font_family']) && is_string($values['font_family']) && preg_match('/^[\pL\pN][\pL\pN .,_-]{0,79}$/uD', $values['font_family'])) {
            $result['font_family'] = (string) $values['font_family'];
        } elseif ($strict && !empty($values['font_family'])) {
            throw new DomainException('De lettertypenaam is ongeldig.', 422);
        }
        foreach (['logo', 'font_file'] as $key) {
            $filename = $values[$key] ?? null;
            if ($filename === null || $filename === '') {
                continue;
            }
            if (is_string($filename) && self::validAsset($directory, $filename, $key)) {
                $result[$key] = $filename;
            } elseif ($strict) {
                throw new DomainException('Een themabestand heeft een ongeldig type, ontbreekt of is onveilig.', 422);
            }
        }
        return $result;
    }

    private static function validAsset(string $directory, string $filename, string $kind): bool
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,120}$/D', $filename) || str_contains($filename, '..')) {
            return false;
        }
        $path = $directory . '/' . $filename;
        if (!is_file($path) || is_link($path) || filesize($path) > ($kind === 'logo' ? 2 * 1024 * 1024 : 1024 * 1024)) {
            return false;
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($kind === 'font_file') {
            $magic = file_get_contents($path, false, null, 0, 4);
            return ($extension === 'woff2' && $magic === 'wOF2' && in_array($mime, ['font/woff2', 'application/font-woff', 'application/octet-stream'], true))
                || ($extension === 'ttf' && in_array($magic, ["\x00\x01\x00\x00", 'true'], true) && in_array($mime, ['font/sfnt', 'font/ttf', 'application/x-font-ttf', 'application/font-sfnt', 'application/octet-stream'], true));
        }
        $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
        if (isset($types[$extension])) {
            return $mime === $types[$extension] && @getimagesize($path) !== false;
        }
        if ($extension !== 'svg' || !in_array($mime, ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain'], true)) {
            return false;
        }
        $contents = (string) file_get_contents($path);
        try {
            // Dezelfde beperkte SVG-taal bij uploaden én uitlezen voorkomt uiteenlopende regels.
            Uploads::sanitizeSvg($contents);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
