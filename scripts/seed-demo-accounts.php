<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

try {
    if ($GLOBALS['config']['environment'] !== 'local') {
        throw new RuntimeException('Voorbeeldaccounts zijn alleen toegestaan met APP_ENV=local.');
    }
    $created = Database::transaction(static function (PDO $database): int {
        // Gebruik uitsluitend de twee lokale voorbeeldbedrijven uit seed.sql.
        $companies = $database->query("SELECT id, slug FROM companies WHERE slug IN ('scabro', 'demo') AND is_active = 1 ORDER BY id FOR UPDATE")->fetchAll();
        if (!$companies) {
            throw new RuntimeException('Importeer eerst de voorbeeldbedrijven met php scripts/setup.php --seed in een lege database.');
        }
        $hash = password_hash('DemoPortal!2026', PASSWORD_BCRYPT);
        $lookup = $database->prepare('SELECT id FROM users WHERE company_id = ? AND email = ?');
        $insert = $database->prepare("INSERT INTO users (company_id, role, department, name, email, password_hash, must_change_password, is_active, email_verified_at) VALUES (?, 'employee', ?, ?, ?, ?, 0, 1, UTC_TIMESTAMP())");
        $created = 0;
        foreach ($companies as $company) {
            $number = 0;
            foreach (Department::DEPARTMENTS as $department => $label) {
                $number++;
                $email = 'employee' . $number . '@' . $company['slug'] . '.test';
                $lookup->execute([$company['id'], $email]);
                if ($lookup->fetchColumn()) {
                    continue;
                }
                // Bestaande accounts en wachtwoorden blijven bij opnieuw uitvoeren intact.
                $insert->execute([$company['id'], $department, 'Medewerker ' . $label, $email, $hash]);
                $created++;
            }
        }
        return $created;
    });
    echo $created . ' voorbeeldmedewerkers aangemaakt; bestaande accounts overgeslagen.' . PHP_EOL;
    echo 'Lokaal: employee1 (assembly), employee2 (bodyshop), employee3 (drones), employee4 (open/intern) @scabro.test of @demo.test.' . PHP_EOL;
    echo 'Wachtwoord voor nieuwe voorbeeldaccounts: DemoPortal!2026' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Voorbeeldaccounts niet aangemaakt: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
