<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

try {
    if ($GLOBALS['config']['environment'] !== 'local') {
        throw new RuntimeException('Voorbeeldwerktijden zijn alleen toegestaan met APP_ENV=local.');
    }
    $created = Database::transaction(static function (PDO $database): int {
        // Dit commando geldt alleen voor de medewerkers uit seed-demo-accounts.php.
        $employees = $database->query("SELECT u.id, u.company_id FROM users u
            JOIN companies c ON c.id = u.company_id
            WHERE c.slug IN ('scabro', 'demo') AND c.is_active = 1
            AND u.role = 'employee' AND u.is_active = 1
            AND u.email IN (CONCAT('employee1@', c.slug, '.test'), CONCAT('employee2@', c.slug, '.test'),
                CONCAT('employee3@', c.slug, '.test'), CONCAT('employee4@', c.slug, '.test'))
            ORDER BY u.company_id, u.id FOR UPDATE")->fetchAll();
        if (!$employees) {
            throw new RuntimeException('Maak eerst de lokale voorbeeldmedewerkers met php scripts/seed-demo-accounts.php.');
        }
        $lookup = $database->prepare('SELECT id FROM working_hours WHERE company_id = ? AND employee_id = ? LIMIT 1');
        $insert = $database->prepare("INSERT INTO working_hours (company_id, employee_id, weekday, start_time, end_time) VALUES (?, ?, ?, '09:00:00', '17:00:00')");
        $created = 0;
        foreach ($employees as $employee) {
            $lookup->execute([$employee['company_id'], $employee['id']]);
            // Ook een gedeeltelijk of aangepast rooster blijft volledig intact.
            if ($lookup->fetchColumn()) {
                continue;
            }
            for ($weekday = 1; $weekday <= 5; $weekday++) {
                $insert->execute([$employee['company_id'], $employee['id'], $weekday]);
            }
            $created++;
        }
        return $created;
    });
    echo $created . ' voorbeeldmedewerkers kregen maandag t/m vrijdag 09:00-17:00 (Europe/Amsterdam).' . PHP_EOL;
    echo 'Bestaande roosters zijn overgeslagen; opnieuw uitvoeren wijzigt deze niet.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Voorbeeldwerktijden niet aangemaakt: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
