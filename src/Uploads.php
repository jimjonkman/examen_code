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
        return $GLOBALS['config']['root'] . '/storage/uploads/' . $path;
    }

    public static function remove(string $path): void
    {
        $absolute = self::absolute($path);
        // Gedeelde voorbeeldbestanden horen niet bij een individuele sollicitant.
        if (!str_starts_with($path, 'samples/') && is_file($absolute)) {
            unlink($absolute);
        }
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
            Http::error('Het Word-bestand kan niet worden gelezen.', 422);
        }
    }
}
