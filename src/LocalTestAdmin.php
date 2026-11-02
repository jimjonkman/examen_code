<?php
declare(strict_types=1);

final class LocalTestAdmin
{
    public static function seed(): bool
    {
        if ($GLOBALS['config']['environment'] !== 'local') {
            throw new RuntimeException('Het admin-testaccount kan alleen worden aangemaakt met APP_ENV=local.');
        }

        return Database::transaction(static function (PDO $database): bool {
            $query = $database->prepare('SELECT id, is_active FROM companies WHERE slug = ? FOR UPDATE');
            $query->execute(['scabro']);
            $company = $query->fetch();
            if ($company && !(bool) $company['is_active']) {
                throw new RuntimeException('Het bedrijf scabro is niet actief; bestaande bedrijfsgegevens zijn niet gewijzigd.');
            }
            if (!$company) {
                $query = $database->prepare('INSERT INTO companies (name, slug, mail_from_name, mail_from_address) VALUES (?, ?, ?, ?)');
                $query->execute(['Scabro', 'scabro', 'Scabro', 'test@admin.nl']);
                $company = ['id' => (int) $database->lastInsertId()];
            }

            $query = $database->prepare('SELECT role FROM users WHERE company_id = ? AND email = ? FOR UPDATE');
            $query->execute([$company['id'], 'test@admin.nl']);
            $role = $query->fetchColumn();
            if ($role !== false) {
                if ($role !== 'admin') {
                    throw new RuntimeException('test@admin.nl bestaat bij Scabro met een andere rol; het account is niet gewijzigd.');
                }
                return false;
            }

            $query = $database->prepare("INSERT INTO users (company_id, role, name, email, password_hash, must_change_password, is_active, email_verified_at)
                VALUES (?, 'admin', ?, ?, ?, 0, 1, UTC_TIMESTAMP())");
            $query->execute([$company['id'], 'test@admin.nl', 'test@admin.nl', password_hash('1234', PASSWORD_BCRYPT)]);
            return true;
        });
    }
}
