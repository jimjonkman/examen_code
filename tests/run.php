<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

// Deze tests gebruiken geen database, SMTP of echte sollicitantgegevens.
$tests = [];
$failures = [];

function check(bool $condition, string $message = 'Verwachting niet gehaald.'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejects(callable $action, string $class = Throwable::class, ?int $status = null): void
{
    try {
        $action();
    } catch (Throwable $exception) {
        check($exception instanceof $class, 'Onverwacht exceptiontype: ' . $exception::class);
        if ($status !== null) {
            check($exception instanceof HttpException && $exception->status === $status, 'Onverwachte HTTP-status.');
        }
        return;
    }
    throw new RuntimeException('De onveilige invoer is ten onrechte geaccepteerd.');
}

$tests['SVG accepteert eenvoudige geometrie en Nederlandse tekst'] = static function (): void {
    $safe = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 40"><rect width="100" height="40" fill="#123456"/><text x="5" y="25">Voorbeeld é</text></svg>';
    $result = Uploads::sanitizeSvg($safe);
    check(str_contains($result, '<rect') && str_contains($result, 'Voorbeeld é'));
};

foreach ([
    'script' => '<script>alert(1)</script>',
    'eventhandler' => '<rect onload="alert(1)" width="10" height="10"/>',
    'extern bestand' => '<image href="https://example.invalid/track.png"/>',
    'foreignObject' => '<foreignObject><div>HTML</div></foreignObject>',
    'externe CSS' => '<rect fill="url(https://example.invalid/track.svg)"/>',
    'style-attribuut' => '<rect style="fill:red"/>',
] as $name => $content) {
    $tests['SVG weigert ' . $name] = static function () use ($content): void {
        rejects(static fn (): string => Uploads::sanitizeSvg('<svg xmlns="http://www.w3.org/2000/svg">' . $content . '</svg>'), HttpException::class, 422);
    };
}

$tests['SVG weigert XML-entities voordat XML wordt verwerkt'] = static function (): void {
    rejects(static fn (): string => Uploads::sanitizeSvg('<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///private">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>'), HttpException::class, 422);
};
$tests['SVG weigert ongeldige XML'] = static function (): void {
    rejects(static fn (): string => Uploads::sanitizeSvg('<svg><rect></svg>'), HttpException::class, 422);
};
$tests['Documentpad blijft binnen privéopslag'] = static function (): void {
    $expected = $GLOBALS['config']['root'] . '/storage/uploads/42/safe-document.pdf';
    check(Uploads::absolute('42/safe-document.pdf') === $expected);
    foreach (['../private.pdf', '42/../../private.pdf', '42\private.pdf', '/42/private.pdf', '42/sub/private.pdf', "42/a\0.pdf"] as $path) {
        rejects(static fn (): string => Uploads::absolute($path), RuntimeException::class);
    }
};

$GLOBALS['config']['key'] = 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
$tests['E-mailversleuteling bewaart inhoud en gebruikt unieke nonces'] = static function (): void {
    $message = ['subject' => 'Uitnodiging voor José', 'body' => 'Synthetisch eenmalig geheim: example-only-token'];
    $first = Mailer::encrypt($message);
    $second = Mailer::encrypt($message);
    check($first !== $second, 'Herhaalde berichten mogen geen gelijke ciphertext hebben.');
    check(!str_contains($first, 'example-only-token'));
    check(Mailer::decrypt($first) === $message);
};
$tests['E-mailversleuteling detecteert gewijzigde payload'] = static function (): void {
    $encoded = Mailer::encrypt(['body' => 'Synthetische inhoud']);
    $bytes = base64_decode($encoded, true);
    check(is_string($bytes));
    $position = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 1;
    $bytes[$position] = chr(ord($bytes[$position]) ^ 1);
    rejects(static fn (): array => Mailer::decrypt(base64_encode($bytes)), RuntimeException::class);
};
$tests['E-mailversleuteling weigert verkeerde sleutel en beschadigd formaat'] = static function (): void {
    $encoded = Mailer::encrypt(['body' => 'Synthetische inhoud']);
    $original = $GLOBALS['config']['key'];
    try {
        $GLOBALS['config']['key'] = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        rejects(static fn (): array => Mailer::decrypt($encoded), RuntimeException::class);
    } finally {
        $GLOBALS['config']['key'] = $original;
    }
    rejects(static fn (): array => Mailer::decrypt('%%%invalid%%%'), RuntimeException::class);
    rejects(static fn (): array => Mailer::decrypt(base64_encode('short')), RuntimeException::class);
};

$tests['Bedrijfsslug sluit paden, URL en ongeldige tekens uit'] = static function (): void {
    foreach (['demo', 'bedrijf-42', str_repeat('a', 80)] as $slug) {
        check(Theme::validSlug($slug));
    }
    foreach (['', '../', 'a/b', 'a\\b', 'Demo', '-demo', 'demo-', 'a--b', 'https://example.invalid', str_repeat('a', 81)] as $slug) {
        check(!Theme::validSlug($slug), 'Ongeldige slug geaccepteerd.');
    }
    rejects(static fn (): array => Theme::read('../'), DomainException::class);
};
$tests['Thema negeert onveilige waarden en kapotte JSON'] = static function (): void {
    // Tijdelijke themamap met unieke naam; geen bestaand bedrijfsbestand wordt gewijzigd.
    $slug = 'test-' . bin2hex(random_bytes(8));
    $directory = dirname(__DIR__) . '/public/themes/' . $slug;
    check(mkdir($directory, 0755, true), 'Testmap kon niet worden aangemaakt.');
    $file = $directory . '/theme.json';
    try {
        file_put_contents($file, json_encode([
            'primary_color' => '#123ABC', 'secondary_color' => 'url(javascript:alert(1))',
            'font_family' => '<script>', 'logo' => '../private.svg',
            'font_file' => 'https://example.invalid/font.ttf',
        ], JSON_THROW_ON_ERROR));
        $theme = Theme::read($slug);
        check($theme['primary_color'] === '#123abc');
        check((bool) preg_match('/^#[a-f0-9]{6}$/D', $theme['secondary_color']));
        check($theme['font_family'] === 'system-ui');
        check($theme['logo_url'] === null && $theme['font_url'] === null);
        file_put_contents($file, '{broken json');
        $neutral = Theme::read($slug);
        check($neutral['font_family'] === 'system-ui' && $neutral['logo_url'] === null);
    } finally {
        if (is_file($file)) {
            unlink($file);
        }
        rmdir($directory);
    }
};
$tests['Een veilig SVG-logo blijft na uploadsanitatie een geldig themabestand'] = static function (): void {
    $slug = 'test-' . bin2hex(random_bytes(8));
    $directory = dirname(__DIR__) . '/public/themes/' . $slug;
    check(mkdir($directory, 0755, true));
    try {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><defs><clipPath id="cut"><circle cx="10" cy="10" r="8"/></clipPath></defs><rect width="20" height="20" fill="#123456" clip-path="url(#cut)"/></svg>';
        file_put_contents($directory . '/logo.svg', Uploads::sanitizeSvg($svg));
        file_put_contents($directory . '/theme.json', json_encode(['logo' => 'logo.svg'], JSON_THROW_ON_ERROR));
        $theme = Theme::read($slug);
        check($theme['logo_url'] === '/themes/' . $slug . '/logo.svg', 'Upload- en leesvalidatie verschillen.');
    } finally {
        foreach (['logo.svg', 'theme.json'] as $name) {
            if (is_file($directory . '/' . $name)) {
                unlink($directory . '/' . $name);
            }
        }
        rmdir($directory);
    }
};
$tests['Uitnodigingslink houdt token buiten de HTTP-query'] = static function (): void {
    $token = str_repeat('a', 64);
    $url = Http::tokenUrl('/set-password.html', 'voorbeeld-bedrijf', $token);
    $parts = parse_url($url);
    check(($parts['fragment'] ?? '') === 'token=' . $token);
    check(($parts['query'] ?? '') === 'company=voorbeeld-bedrijf');
    check(!str_contains($parts['query'] ?? '', 'token'));
};
$tests['CSRF accepteert uitsluitend de actuele sessietoken'] = static function (): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $_SESSION['csrf_token'];
    Http::csrf();
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';
    rejects(static fn () => Http::csrf(), HttpException::class, 419);
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    rejects(static fn () => Http::csrf(), HttpException::class, 419);
};
$tests['Admin-testaccount weigert niet-lokale omgevingen vóór een databaseverbinding'] = static function (): void {
    $original = $GLOBALS['config'];
    try {
        // Een fout in de guard mag nooit de bestaande applicatiedatabase benaderen.
        $GLOBALS['config']['database'] = ['host' => '127.0.0.1', 'port' => 1, 'name' => 'unused', 'username' => 'unused', 'password' => ''];
        foreach (['production', 'staging', ''] as $environment) {
            $GLOBALS['config']['environment'] = $environment;
            try {
                LocalTestAdmin::seed();
                throw new LogicException('Het testaccount is buiten de lokale omgeving toegestaan.');
            } catch (RuntimeException $exception) {
                check($exception::class === RuntimeException::class
                    && str_contains($exception->getMessage(), 'APP_ENV=local'), 'De lokale guard moet weigeren voordat de database wordt geopend.');
            }
        }
    } finally {
        $GLOBALS['config'] = $original;
    }
};

foreach ($tests as $name => $test) {
    try {
        $test();
        echo '[OK] ' . $name . PHP_EOL;
    } catch (Throwable $exception) {
        $failures[] = $name;
        fwrite(STDERR, '[FAIL] ' . $name . ': ' . $exception->getMessage() . PHP_EOL);
    }
}
echo (count($tests) - count($failures)) . '/' . count($tests) . ' controles geslaagd.' . PHP_EOL;
exit($failures === [] ? 0 : 1);
