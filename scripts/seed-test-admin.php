<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
if (count($argv) !== 1) {
    fwrite(STDERR, 'Gebruik: php scripts/seed-test-admin.php (zonder aanvullende opties).' . PHP_EOL);
    exit(1);
}

try {
    if (LocalTestAdmin::seed()) {
        echo 'Lokaal admin-testaccount aangemaakt bij Scabro: test@admin.nl / 1234.' . PHP_EOL;
    } else {
        echo 'Het adminaccount test@admin.nl bestaat al bij Scabro. Het account en wachtwoord zijn niet gewijzigd.' . PHP_EOL;
    }
    echo 'Inloggen: ' . Http::url('/login.html', ['company' => 'scabro']) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Admin-testaccount niet aangemaakt: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
