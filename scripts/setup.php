<?php
/*
<!-- hier ben ik weer, voor geheugensteuntje van mijzelf :) -->
*/
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
if (count($argv) !== 1) {
    fwrite(STDERR, 'Gebruik: php scripts/setup.php (zonder aanvullende opties).' . PHP_EOL);
    exit(1);
}
try {
    $database = Database::connection();
    if ($database->query('SHOW TABLES')->fetch()) {
        throw new RuntimeException('De database is niet leeg; bestaande data wordt niet overschreven.');
    }
    $database->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
    echo "Database ingericht." . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Installatie mislukt: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

// Richt alleen de tabellen in; de eerste beheerder maak je met create-admin.php.
