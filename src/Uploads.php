<?php
declare(strict_types=1);

final class Uploads
{
    public static function document(array $file, string $companyId): array
    {
        self::check($file, 5 * 1024 * 1024);
        if (!preg_match('/^[0-9]+$/D', $companyId)) {
            throw new RuntimeException('Ongeldige opslaglocatie.');
        }
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if ($extension === 'pdf') {
            if ($mime !== 'application/pdf' || file_get_contents($file['tmp_name'], false, null, 0, 5) !== '%PDF-') {
                Http::error('Het PDF-bestand is ongeldig.', 422);
            }
        } elseif ($extension === 'docx') {
            if (!in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream'], true)) {
                Http::error('Het Word-bestand is ongeldig.', 422);
            }
            self::checkDocx($file['tmp_name']);
        } else {
            Http::error('Upload alleen een PDF- of DOCX-bestand (maximaal 5 MB).', 422);
        }
        $relative = $companyId . '/' . bin2hex(random_bytes(20)) . '.' . $extension;
        $target = self::absolute($relative);
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true) && !is_dir(dirname($target))) {
            throw new RuntimeException('Uploadmap niet beschikbaar.');
        }
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Document kon niet worden opgeslagen.');
        }
        chmod($target, 0600);
        $name = mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', basename((string) $file['name'])) ?: 'document.' . $extension, 0, 180);
        return ['path' => $relative, 'name' => $name];
    }

    public static function absolute(string $path): string
    {
        if (!preg_match('~^[a-zA-Z0-9_-]+/[a-zA-Z0-9_.-]+$~D', $path) || str_contains($path, '..')) {
            throw new RuntimeException('Ongeldig documentpad.');
        }
        $storage = $GLOBALS['config']['root'] . '/storage';
        $absolute = $storage . '/uploads/' . $path;
        foreach ([$storage, $storage . '/uploads', dirname($absolute), $absolute] as $location) {
            if (is_link($location)) {
                throw new RuntimeException('Ongeldige documentopslag.');
            }
        }
        return $absolute;
    }

    public static function remove(string $path): void
    {
        $absolute = self::absolute($path);
        if (is_file($absolute)) {
            unlink($absolute);
        }
    }

    public static function themeAsset(array $file, string $slug, string $kind): string
    {
        $directory = self::themeDirectory($slug);
        if (!in_array($kind, ['font', 'logo'], true)) {
            throw new RuntimeException('Ongeldig themabestandtype.');
        }
        self::check($file, $kind === 'font' ? 1024 * 1024 : 2 * 1024 * 1024);
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $content = file_get_contents($file['tmp_name']);
        if (!is_string($content)) {
            throw new RuntimeException('Het themabestand kon niet worden gelezen.');
        }
        if ($kind === 'font') {
            $valid = ($extension === 'woff2' && substr($content, 0, 4) === 'wOF2') || ($extension === 'ttf' && substr($content, 0, 4) === "\x00\x01\x00\x00");
            if (!$valid || !in_array($mime, ['font/ttf', 'font/sfnt', 'application/x-font-ttf', 'application/font-sfnt', 'font/woff2', 'application/font-woff', 'application/octet-stream'], true)) {
                Http::error('Upload een geldig TTF- of WOFF2-lettertype.', 422);
            }
        } elseif ($extension === 'svg') {
            if (!in_array($mime, ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain'], true)) {
                Http::error('Het SVG-bestand is ongeldig.', 422);
            }
            $content = self::sanitizeSvg($content);
        } else {
            $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
            if (!isset($types[$extension]) || $mime !== $types[$extension] || @getimagesize($file['tmp_name']) === false) {
                Http::error('Upload een PNG-, JPG-, SVG- of WEBP-logo.', 422);
            }
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo[0] > 6000 || $imageInfo[1] > 6000) {
                Http::error('Het logo mag maximaal 6000 × 6000 pixels zijn.', 422);
            }
        }
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Themamap niet beschikbaar.');
        }
        $name = $kind . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
        if (file_put_contents($directory . '/' . $name, $content, LOCK_EX) === false) {
            if (is_file($directory . '/' . $name)) {
                unlink($directory . '/' . $name);
            }
            throw new RuntimeException('Thema kon niet worden opgeslagen.');
        }
        return $name;
    }

    public static function removeThemeAsset(string $slug, string $name): void
    {
        if (!preg_match('/^(?:logo|font)-[a-f0-9]{24}\.(?:png|jpe?g|webp|svg|ttf|woff2)$/D', $name)) {
            throw new RuntimeException('Ongeldig themabestandpad.');
        }
        $directory = self::themeDirectory($slug);
        $path = $directory . '/' . $name;
        if (is_link($path)) {
            throw new RuntimeException('Ongeldig themabestandpad.');
        }
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Het tijdelijke themabestand kon niet worden verwijderd.');
        }
        if (is_dir($directory) && count(scandir($directory) ?: []) === 2) {
            rmdir($directory);
        }
    }

    private static function themeDirectory(string $slug): string
    {
        if (!Theme::validSlug($slug)) {
            Http::error('Ongeldige bedrijfscode.', 422);
        }
        $public = $GLOBALS['config']['root'] . '/public';
        $directory = $public . '/themes/' . $slug;
        foreach ([$public, $public . '/themes', $directory] as $location) {
            if (is_link($location)) {
                throw new RuntimeException('Ongeldige themamap.');
            }
        }
        return $directory;
    }

    public static function sanitizeSvg(string $content): string
    {
        // SVG is actieve XML: een beperkte vormtaal voorkomt scripts en externe verzoeken.
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $content)) {
            Http::error('Dit SVG-bestand bevat niet-toegestane XML.', 422);
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$document->loadXML($content, LIBXML_NONET | LIBXML_NOBLANKS) || $document->documentElement?->localName !== 'svg') {
                Http::error('Het SVG-bestand is ongeldig.', 422);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $elements = ['svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'title', 'desc', 'defs', 'linearGradient', 'radialGradient', 'stop', 'clipPath'];
        $attributes = ['xmlns', 'viewBox', 'width', 'height', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'd', 'points', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-rule', 'clip-rule', 'opacity', 'fill-opacity', 'stroke-opacity', 'transform', 'id', 'offset', 'stop-color', 'stop-opacity', 'gradientUnits', 'gradientTransform', 'font-size', 'font-family', 'font-weight', 'text-anchor', 'dominant-baseline', 'clip-path'];
        foreach ($document->getElementsByTagName('*') as $node) {
            if (!in_array($node->localName, $elements, true) || !in_array($node->namespaceURI, [null, 'http://www.w3.org/2000/svg'], true)) {
                Http::error('Het SVG-logo bevat niet-toegestane elementen.', 422);
            }
            foreach ($node->attributes as $attribute) {
                $value = $attribute->value;
                if (!in_array($attribute->name, $attributes, true) || preg_match('/javascript:|data:|https?:|@import|expression\s*\(/i', $value) || (str_contains(strtolower($value), 'url(') && !preg_match('/^url\(#[A-Za-z0-9_-]+\)$/D', $value))) {
                    // Alleen de vaste SVG-naamruimte mag een http-waarde bevatten.
                    if (!($attribute->name === 'xmlns' && $value === 'http://www.w3.org/2000/svg')) {
                        Http::error('Het SVG-logo bevat niet-toegestane attributen.', 422);
                    }
                }
            }
        }
        return $document->saveXML($document->documentElement);
    }

    private static function check(array $file, int $maximum): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            Http::error('Het bestand is niet ontvangen. Upload het opnieuw.', 422);
        }
        $size = filesize($file['tmp_name']);
        if ($size === false || $size < 1 || $size > $maximum) {
            Http::error('Het bestand is leeg of groter dan toegestaan.', 422);
        }
    }

    private static function checkDocx(string $path): void
    {
        try {
            $archive = new PharData($path, 0, null, Phar::ZIP);
            if (!isset($archive['[Content_Types].xml'], $archive['word/document.xml'])) {
                Http::error('Dit bestand is geen geldig DOCX-document.', 422);
            }
            $total = 0;
            foreach (new RecursiveIteratorIterator($archive) as $entry) {
                $total += $entry->getSize();
                if ($total > 20 * 1024 * 1024) {
                    Http::error('Het uitgepakte Word-bestand is te groot.', 422);
                }
            }
        } catch (HttpException $exception) {
            throw $exception;
        } catch (Throwable) {
            Http::error('Het Word-bestand kan niet worden gelezen, probeer het opnieuw, of gebruik een PDF-bestand.', 422);
        }
    }
}
