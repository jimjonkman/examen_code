<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$database = Database::connection();
$sent = 0;
$failed = 0;
for ($index = 0; $index < 50; $index++) {
    $result = Database::transaction(static function (PDO $database): ?bool {
        $query = $database->query('SELECT * FROM mail_queue WHERE sent_at IS NULL AND attempts < 5 AND available_at <= UTC_TIMESTAMP() ORDER BY id LIMIT 1 FOR UPDATE');
        $message = $query->fetch();
        if (!$message) {
            return null;
        }
        try {
            Mailer::deliver($message['recipient'], Mailer::decrypt($message['payload']));
            $query = $database->prepare("UPDATE mail_queue SET sent_at = UTC_TIMESTAMP(), payload = '', last_error = NULL WHERE company_id = ? AND id = ?");
            $query->execute([$message['company_id'], $message['id']]);
            return true;
        } catch (Throwable) {
            $query = $database->prepare('UPDATE mail_queue SET attempts = attempts + 1, available_at = ?, last_error = ? WHERE company_id = ? AND id = ?');
            $query->execute([gmdate('Y-m-d H:i:s', time() + 300), 'Aflevering mislukt; controleer SMTP-configuratie.', $message['company_id'], $message['id']]);
            return false;
        }
    });
    if ($result === null) {
        break;
    }
    $result ? $sent++ : $failed++;
}
echo "Verzonden: {$sent}. Uitgesteld: {$failed}." . PHP_EOL;
