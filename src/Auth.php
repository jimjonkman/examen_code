<?php
declare(strict_types=1);

final class Auth
{
    public static function issueToken(PDO $database, array $user, string $purpose, int $hours = 48): string
    {
        $raw = bin2hex(random_bytes(32));
        $query = $database->prepare('UPDATE auth_tokens SET used_at = UTC_TIMESTAMP() WHERE company_id = ? AND user_id = ? AND purpose = ? AND used_at IS NULL');
        $query->execute([$user['company_id'], $user['id'], $purpose]);
        $query = $database->prepare('INSERT INTO auth_tokens (company_id, user_id, token_hash, purpose, expires_at) VALUES (?, ?, ?, ?, ?)');
        $query->execute([$user['company_id'], $user['id'], hash('sha256', $raw), $purpose, gmdate('Y-m-d H:i:s', time() + $hours * 3600)]);
        return $raw;
    }

    public static function session(): never
    {
        $company = Http::company();
        $user = Http::currentUser();
        if ($user && (int) $user['company_id'] !== (int) $company['id']) {
            $user = null;
        }
        Http::json([
            'company' => ['id' => $company['id'], 'name' => $company['name'], 'slug' => $company['slug']],
            'user' => $user,
            'csrf_token' => $_SESSION['csrf_token'],
            'positions' => Department::POSITIONS,
            'departments' => Department::DEPARTMENTS,
        ]);
    }

    public static function login(): never
    {
        Http::csrf();
        Http::limit('login', 10, 900);
        $company = Http::company();
        $data = Http::input();
        $email = strtolower(trim(Http::string($data, 'email')));
        $password = Http::string($data, 'password');
        $query = Database::connection()->prepare('SELECT * FROM users WHERE company_id = ? AND email = ? AND is_active = 1');
        $query->execute([$company['id'], $email]);
        $user = $query->fetch();
        // Ook onbekende accounts krijgen bcrypt-werk om eenvoudig tijdsverschil te vermijden.
        $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = strlen($password) <= 72 && password_verify($password, $hash);
        if (!$user || !$user['password_hash'] || !$valid || !$user['email_verified_at']) {
            Http::error('E-mailadres of wachtwoord is onjuist, of je account is nog niet geactiveerd.', 401);
        }
        if ($user['role'] === 'applicant') {
            $query = Database::connection()->prepare("SELECT id FROM applications WHERE company_id = ? AND user_id = ? AND status = 'approved'");
            $query->execute([$company['id'], $user['id']]);
            if (!$query->fetchColumn()) {
                Http::error('E-mailadres of wachtwoord is onjuist, of je account is nog niet geactiveerd.', 401);
            }
        }
        if ((bool) $user['must_change_password']) {
            Http::error('Gebruik de eenmalige e-maillink om eerst je wachtwoord in te stellen.', 403);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['company_id'] = (int) $company['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        Http::json(['user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role'], 'department' => $user['department']], 'csrf_token' => $_SESSION['csrf_token']]);
    }

    public static function updateDepartment(): never
    {
        $user = Http::requireUser(['employee']);
        Http::csrf();
        $input = Http::input();
        if (array_diff(array_keys($input), ['department'])) {
            Http::error('Je kunt hier alleen je eigen afdeling wijzigen.', 422);
        }
        $department = Department::validate($input['department'] ?? null);
        $statement = Database::connection()->prepare("UPDATE users SET department = ? WHERE id = ? AND company_id = ? AND role = 'employee' AND is_active = 1");
        $statement->execute([$department, $user['id'], $user['company_id']]);
        Http::json(['message' => 'Je afdeling is bijgewerkt.', 'user' => Http::currentUser()]);
    }

    public static function logout(): never
    {
        Http::csrf();
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        Http::json(['message' => 'Je bent uitgelogd.']);
    }

    public static function setPassword(): never
    {
        Http::csrf();
        Http::limit('set-password', 15, 900);
        $company = Http::company();
        $data = Http::input();
        $password = Http::string($data, 'password');
        if (strlen($password) < 12 || strlen($password) > 72) {
            Http::error('Gebruik een wachtwoord van minimaal 12 en maximaal 72 bytes.', 422);
        }
        $raw = Http::string($data, 'token');
        Database::transaction(static function (PDO $database) use ($company, $password, $raw): void {
            $token = self::lockedToken($database, $company, $raw, 'set_password');
            $query = $database->prepare('SELECT role FROM users WHERE company_id = ? AND id = ? AND is_active = 1 FOR UPDATE');
            $query->execute([$company['id'], $token['user_id']]);
            $role = $query->fetchColumn();
            if (!$role) {
                Http::error('Deze toegangslink is niet meer geldig.', 422);
            }
            if ($role === 'applicant') {
                $query = $database->prepare("SELECT id FROM applications WHERE company_id = ? AND user_id = ? AND status = 'approved'");
                $query->execute([$company['id'], $token['user_id']]);
                if (!$query->fetchColumn()) {
                    Http::error('Deze toegangslink is niet meer geldig.', 422);
                }
            }
            $query = $database->prepare('UPDATE users SET password_hash = ?, must_change_password = 0, email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE company_id = ? AND id = ?');
            $query->execute([password_hash($password, PASSWORD_BCRYPT), $company['id'], $token['user_id']]);
            self::consumeToken($database, $company, $token);
        });
        Http::json(['message' => 'Je wachtwoord is ingesteld. Je kunt nu inloggen.']);
    }

    public static function verifyEmail(): never
    {
        Http::csrf();
        Http::limit('verify-email', 20, 900);
        $company = Http::company();
        $raw = Http::string(Http::input(), 'token');
        Database::transaction(static function (PDO $database) use ($company, $raw): void {
            $token = self::lockedToken($database, $company, $raw, 'verify_email');
            $query = $database->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE company_id = ? AND id = ?');
            $query->execute([$company['id'], $token['user_id']]);
            self::consumeToken($database, $company, $token);
        });
        Http::json(['message' => 'Je e-mailadres is bevestigd. Een medewerker beoordeelt je sollicitatie.']);
    }

    private static function lockedToken(PDO $database, array $company, string $raw, string $purpose): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $raw)) {
            Http::error('Deze link is ongeldig of verlopen.', 422);
        }
        $query = $database->prepare('SELECT * FROM auth_tokens WHERE company_id = ? AND token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() FOR UPDATE');
        $query->execute([$company['id'], hash('sha256', $raw), $purpose]);
        $token = $query->fetch();
        if (!$token) {
            Http::error('Deze link is ongeldig of verlopen.', 422);
        }
        return $token;
    }

    private static function consumeToken(PDO $database, array $company, array $token): void
    {
        $query = $database->prepare('UPDATE auth_tokens SET used_at = UTC_TIMESTAMP() WHERE company_id = ? AND id = ?');
        $query->execute([$company['id'], $token['id']]);
    }

    public static function deleteAccount(): never
    {
        Http::csrf();
        $user = Http::requireUser(['applicant']);
        $company = Http::company();
        $query = Database::connection()->prepare('SELECT password_hash FROM users WHERE company_id = ? AND id = ?');
        $query->execute([$user['company_id'], $user['id']]);
        if (!password_verify(Http::string(Http::input(), 'password'), (string) $query->fetchColumn())) {
            Http::error('Het wachtwoord is onjuist.', 422);
        }
        $files = Database::transaction(static function (PDO $database) use ($user, $company): array {
            $query = $database->prepare('SELECT id, cv_path, letter_path FROM applications WHERE company_id = ? AND user_id = ? FOR UPDATE');
            $query->execute([$user['company_id'], $user['id']]);
            $application = $query->fetch();
            if ($application) {
                $query = $database->prepare('SELECT p.*, e.name AS employee_name, e.email AS employee_email FROM appointments p
                    JOIN users e ON e.id = p.employee_id AND e.company_id = p.company_id
                    WHERE p.company_id = ? AND p.application_id = ? ORDER BY p.employee_id, p.id');
                $query->execute([$company['id'], $application['id']]);
                AppointmentNotifications::deleteApplicant($database, $company, $query->fetchAll(PDO::FETCH_ASSOC));
            }
            $query = $database->prepare('DELETE FROM users WHERE company_id = ? AND id = ?');
            $query->execute([$user['company_id'], $user['id']]);
            $query = $database->prepare('DELETE FROM mail_queue WHERE company_id = ? AND recipient = ?');
            $query->execute([$user['company_id'], $user['email']]);
            return $application ? [$application['cv_path'], $application['letter_path']] : [];
        });
        foreach ($files as $path) {
            Uploads::remove($path);
        }
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        Http::json(['message' => 'Je account, sollicitatie en documenten zijn verwijderd.']);
    }
}
