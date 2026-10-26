<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
try {
    if (in_array('--seed', $argv, true) && $GLOBALS['config']['environment'] !== 'local') {
        throw new RuntimeException('Voorbeeldaccounts zijn alleen toegestaan met APP_ENV=local.');
    }
    $database = Database::connection();
    if ($database->query('SHOW TABLES')->fetch()) {
        throw new RuntimeException('De database is niet leeg; bestaande data wordt niet overschreven.');
    }
    $database->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
    if (in_array('--seed', $argv, true)) {
        $database->exec(file_get_contents(dirname(__DIR__) . '/database/seed.sql'));
    }
    echo "Database ingericht." . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Installatie mislukt: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
