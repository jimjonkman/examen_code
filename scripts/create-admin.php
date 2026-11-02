<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$options = getopt('', ['company-name:', 'slug:', 'email:', 'name:']);
// hier haalt hij de bedrijfsnaam, slug, email, en naam op ^
$companyName = trim((string) ($options['company-name'] ?? ''));
//  hier trimt hij de bedrijfsnaam en maakt het een string ^
$slug = trim((string) ($options['slug'] ?? ''));
// hier maakt hij de slug een string ^
$email = strtolower(trim((string) ($options['email'] ?? '')));
// en hier maakt hij de email een string, en zet het naar kleine letters^
$name = trim((string) ($options['name'] ?? ''));
// en hier maakt hij de naam een string ^
if (!Theme::validSlug($slug) || $companyName === '' || strlen($companyName) > 150
    || $name === '' || strlen($name) > 150 || strlen($email) > 254
    || preg_match('/[\x00-\x1F]/u', $companyName . $name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, 'Gebruik: php scripts/create-admin.php --company-name=Bedrijf --slug=bedrijf --email=beheerder@example.nl --name=Beheerder' . PHP_EOL);
    exit(1);
}
// hier checkt hij of de slug valid is, en of de andere dingen voldoen aan de eisen ^

try {
    Database::transaction(static function (PDO $database) use ($companyName, $slug, $email, $name): void {
        $admins = $database->query("SELECT id FROM users WHERE role = 'admin' FOR UPDATE");
        if ($admins->fetchColumn() !== false) {
            throw new RuntimeException('De eerste beheerder bestaat al. Dit installatiecommando is eenmalig.');
        }
        // dit is voor eerst gebruik, om te checken of er een beheerder is, zo niet geeft hij een runtimexception error ^
        $query = $database->prepare('SELECT * FROM companies WHERE slug = ? FOR UPDATE');
        $query->execute([$slug]);
        $company = $query->fetch();
        // hier bereid hij de database voor op het toevoegen van een beheerder en checked of het bedrijf bestaat "WHERE slug = ? " ^
        if ($company) {
            if (!(bool) $company['is_active']) {
                throw new RuntimeException('Het bestaande bedrijf moet actief zijn om een beheerder toe te voegen.');
            }
        } 
        // hier checkt hij of het bedrijf actief is, zo niet een runtimexception, net zoals bij het checken van de berheerder ^
        else {
            $query = $database->prepare('INSERT INTO companies (name, slug, mail_from_name, mail_from_address) VALUES (?, ?, ?, ?)');
            $query->execute([$companyName, $slug, $companyName, $email]);
            $query = $database->prepare('SELECT * FROM companies WHERE id = ?');
            $query->execute([(int) $database->lastInsertId()]);
            $company = $query->fetch();
        }
        // database voorbereiding voor het toevoegen van een bedrijf, en doet weer een check of hij bestaat, zo niet gaat hij door ^
        $query = $database->prepare('SELECT id FROM users WHERE company_id = ? AND email = ?');
        $query->execute([$company['id'], $email]);
        if ($query->fetchColumn()) {
            throw new RuntimeException('Dit e-mailadres bestaat al bij het bedrijf; het account is niet gewijzigd.');
        }
        // dit is voor het toevoegen va neen nieuwe email adress, zo kan hij zien of dat email adress al bestaat ^
        $query = $database->prepare("INSERT INTO users (company_id, role, name, email) VALUES (?, 'admin', ?, ?)");
        $query->execute([$company['id'], $name, $email]);
        $token = Auth::issueToken($database, ['company_id' => $company['id'], 'id' => (int) $database->lastInsertId()], 'set_password');
        Mailer::queue($database, $company, $email, 'set_password', [
            'name' => $name, 'setup_url' => Http::tokenUrl('/set-password.html', $company['slug'], $token),
        ]);
        // hier voegt hij een beheerder toe, en stuurt hij een email naar die nieuwe beheerder voor het instellen van een wachtwoord ^
    });

    echo 'Beheerder aangemaakt. Voer php scripts/mail-worker.php uit om de activatielink te verzenden.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Beheerder niet aangemaakt: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
// en dit is gewoon eerst een echo in de server dat hij wordt aangemaakt, als dit niet lukt geef thij een error ^ 
