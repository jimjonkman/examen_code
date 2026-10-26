<?php
declare(strict_types=1);

final class Theme
{
    public static function read(string $slug): array
    {
        if (strlen($slug) > 80 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            Http::error('Ongeldig bedrijf.', 404);
        }
        $directory = $GLOBALS['config']['root'] . '/public/themes/' . $slug;
        $file = $directory . '/theme.json';
        $values = [];
        if (is_file($file) && filesize($file) <= 16384) {
            $values = json_decode((string) file_get_contents($file), true);
            $values = is_array($values) ? $values : [];
        }
        $theme = ['primary_color' => '#263c3b', 'secondary_color' => '#b5d4bd', 'font_family' => '', 'logo_url' => null, 'font_url' => null];
        foreach (['primary_color', 'secondary_color'] as $key) {
            if (is_string($values[$key] ?? null) && preg_match('/^#[a-f0-9]{6}$/iD', $values[$key])) {
                $theme[$key] = $values[$key];
            }
        }
        if (is_string($values['font_family'] ?? null) && preg_match('/^[\p{L}\p{N}][\p{L}\p{N} .,_-]{0,79}$/uD', $values['font_family'])) {
            $theme['font_family'] = $values['font_family'];
        }
        foreach (['logo' => ['logo_url', 'png|jpe?g|webp'], 'font_file' => ['font_url', 'woff2?|ttf']] as $key => [$urlKey, $extensions]) {
            $name = $values[$key] ?? null;
            if (is_string($name) && preg_match('/^[a-z0-9][a-z0-9_.-]*\.(' . $extensions . ')$/iD', $name) && is_file($directory . '/' . $name)) {
                $theme[$urlKey] = '/themes/' . rawurlencode($slug) . '/' . rawurlencode($name);
            }
        }
        return $theme;
    }
}
