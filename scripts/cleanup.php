<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$database = Database::connection();
// database connectie ^
$threshold = gmdate('Y-m-d H:i:s', time() - $GLOBALS['config']['retention_days'] * 86400);
// datum en tijd van sollicitaties, qua retentie, 6 maanden dus ^
$query = $database->prepare("SELECT a.company_id, a.user_id, a.cv_path, a.letter_path, u.email FROM applications a JOIN users u ON u.company_id = a.company_id AND u.id = a.user_id WHERE (a.status = 'rejected' AND a.reviewed_at < ?) OR (a.status = 'pending' AND u.email_verified_at IS NULL AND a.created_at < ?)");
// email verificatie en selectie van sollicitanten, voor het verwijderen na die 6 maanden ^ 
$query->execute([$threshold, $threshold]);
//  voor selectie van degenen die verwijderd moeten worden ^ 
$removed = 0;
// laat zien hoeveel verwijderd is ^
foreach ($query->fetchAll() as $row) {
    // fetched alle rijen als een array ^ 
    $deleted = Database::transaction(static function (PDO $database) use ($row, $threshold): bool {
        // dit checked of een sollicitant verwijderd kan worden ^
        $check = $database->prepare('SELECT a.status, a.reviewed_at, a.created_at, u.email_verified_at FROM applications a JOIN users u ON u.company_id = a.company_id AND u.id = a.user_id WHERE a.company_id = ? AND a.user_id = ? FOR UPDATE');
        // hier bereid hij de database voor op het verwijderen van een sollicitant, en checkt de voorwaarden ^
        $check->execute([$row['company_id'], $row['user_id']]);
        // hier voert hij de check uit^
        $current = $check->fetch();
        // hier fetched hij de status van de voorbereide check ^
        if (!$current || !(($current['status'] === 'rejected' && $current['reviewed_at'] < $threshold) || ($current['status'] === 'pending' && !$current['email_verified_at'] && $current['created_at'] < $threshold))) {
            return false;
        }
        // als het niet correct is returned hij false anders gaat hij verder ^
        $delete = $database->prepare('DELETE FROM mail_queue WHERE company_id = ? AND recipient = ?');
        $delete->execute([$row['company_id'], $row['email']]);
        $delete = $database->prepare("DELETE FROM users WHERE company_id = ? AND id = ? AND role = 'applicant'");
        $delete->execute([$row['company_id'], $row['user_id']]);
        return $delete->rowCount() > 0;
    });
    // hierboven is de hele code waarmee hij de sollicitant uit de database verwijdert ^
    if ($deleted) {
        Uploads::remove($row['cv_path']);
        Uploads::remove($row['letter_path']);
        $removed++;
    }
    // als hij verwijdert is verwijdert hij ook de cv en motivatie ^ 
}
$database->exec('DELETE FROM auth_tokens WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 7 DAY');
$database->exec('DELETE FROM rate_limits WHERE window_start < UTC_TIMESTAMP() - INTERVAL 1 DAY');
$database->exec('DELETE FROM mail_queue WHERE sent_at < UTC_TIMESTAMP() - INTERVAL 7 DAY OR (sent_at IS NULL AND created_at < UTC_TIMESTAMP() - INTERVAL 7 DAY)');
echo "Verwijderde sollicitanten: {$removed}." . PHP_EOL;
// hier verwijdert hij het uit de auth_token, rate_limit en mail_queue ^ 