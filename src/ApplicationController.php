<?php

declare(strict_types=1);

final class ApplicationController
{
    public static function create(): never
    {
        $company = Http::company();
        Http::csrf();
        Http::limit('application:' . $company['id'], 5, 3600);
        $input = Http::input();
        if (!is_string($input['name'] ?? null) || !is_string($input['email'] ?? null) || !is_string($input['website'] ?? '')) {
            Http::error('Vul een geldige naam en een geldig e-mailadres in.', 422);
        }
        if (trim((string) ($input['website'] ?? '')) !== '') {
            Http::error('Het formulier kon niet worden verwerkt.', 422);
        }
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($name === '' || strlen($name) > 150 || preg_match('/[\x00-\x1F]/u', $name)) {
            Http::error('Vul een geldige naam in (maximaal 150 tekens).', 422);
        }
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Http::error('Vul een geldig e-mailadres in.', 422);
        }
        $position = Department::validatePosition($input['position'] ?? null);
        $check = Database::connection()->prepare('SELECT id FROM users WHERE company_id = ? AND email = ?');
        $check->execute([$company['id'], $email]);
        if ($check->fetch()) {
            Http::error('Dit e-mailadres is al bij dit bedrijf geregistreerd.', 409);
        }

        $files = [];
        try {
            $files['cv'] = Uploads::document($_FILES['cv'] ?? [], (string) $company['id']);
            $files['letter'] = Uploads::document($_FILES['letter'] ?? [], (string) $company['id']);
            Database::transaction(function (PDO $db) use ($company, $name, $email, $position, $files): void {
                $insert = $db->prepare(
                    "INSERT INTO users (company_id, role, name, email, password_hash, must_change_password, is_active)
                     VALUES (?, 'applicant', ?, ?, NULL, 0, 1)"
                );
                $insert->execute([$company['id'], $name, $email]);
                $userId = (int) $db->lastInsertId();
                $insert = $db->prepare(
                    "INSERT INTO applications (company_id, user_id, position, cv_path, letter_path, cv_name, letter_name, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
                );
                $insert->execute([
                    $company['id'], $userId, $position, $files['cv']['path'], $files['letter']['path'],
                    $files['cv']['name'], $files['letter']['name'],
                ]);
                $user = ['id' => $userId, 'company_id' => (int) $company['id'], 'name' => $name, 'email' => $email];
                $token = Auth::issueToken($db, $user, 'verify_email');
                Mailer::queue($db, $company, $email, 'verify_email', [
                    'name' => $name,
                    'company_name' => $company['name'],
                    'verification_url' => Http::tokenUrl('/verify-email.html', $company['slug'], $token),
                ]);
            });
        } catch (Throwable $exception) {
            foreach ($files as $file) {
                Uploads::remove($file['path']);
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                Http::error('Dit e-mailadres is al bij dit bedrijf geregistreerd.', 409);
            }
            throw $exception;
        }
        Http::json(['message' => 'Je sollicitatie is ontvangen. Bevestig je e-mailadres via de toegestuurde e-mail.'], 201);
    }

    public static function index(): never
    {
        $company = Http::company();
        $employee = Http::requireUser(['employee']);
        $status = $_GET['status'] ?? 'pending';
        if (!is_string($status)) {
            Http::error('Ongeldige sollicitatiestatus.', 422);
        }
        if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            Http::error('Ongeldige sollicitatiestatus.', 422);
        }
        $query = self::selection() . ' WHERE a.company_id = ? AND a.department = ?';
        $params = [$company['id'], $employee['department']];
        if ($status !== 'all') {
            $query .= ' AND a.status = ?';
            $params[] = $status;
        }
        $statement = Database::connection()->prepare($query . ' ORDER BY a.created_at DESC, a.id DESC LIMIT 200');
        $statement->execute($params);
        $applications = array_map(self::present(...), $statement->fetchAll(PDO::FETCH_ASSOC));
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        $statement = Database::connection()->prepare('SELECT status, COUNT(*) AS total FROM applications WHERE company_id = ? AND department = ? GROUP BY status');
        $statement->execute([$company['id'], $employee['department']]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        Http::json(['applications' => $applications, 'counts' => $counts]);
    }

    public static function show(int $id): never
    {
        $application = self::authorizedApplication($id);
        Http::json(['application' => self::present($application)]);
    }

    public static function review(int $id): never
    {
        $company = Http::company();
        $employee = Http::requireUser(['employee']);
        Http::csrf();
        $status = Http::input()['status'] ?? '';
        if (!in_array($status, ['approved', 'rejected'], true)) {
            Http::error('Kies goedkeuren of afwijzen.', 422);
        }
        try {
            Database::transaction(function (PDO $db) use ($id, $company, $employee, $status): void {
                // De rijvergrendeling voorkomt dat twee medewerkers dezelfde aanvraag beoordelen.
                $statement = $db->prepare(
                    'SELECT a.*, u.name, u.email, u.email_verified_at FROM applications a
                     JOIN users u ON u.id = a.user_id AND u.company_id = a.company_id
                     WHERE a.id = ? AND a.company_id = ? FOR UPDATE'
                );
                $statement->execute([$id, $company['id']]);
                $application = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$application) {
                    throw new DomainException('Sollicitatie niet gevonden.', 404);
                }
                // Controleer de actuele afdeling opnieuw voordat de beoordeling wordt opgeslagen.
                $statement = $db->prepare("SELECT department FROM users WHERE id = ? AND company_id = ? AND role = 'employee' AND is_active = 1 FOR UPDATE");
                $statement->execute([$employee['id'], $company['id']]);
                $currentEmployee = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$currentEmployee) {
                    throw new DomainException('Log eerst in om verder te gaan.', 401);
                }
                if ($currentEmployee['department'] === null || $currentEmployee['department'] !== $application['department']) {
                    throw new DomainException('Sollicitatie niet gevonden.', 404);
                }
                if ($application['status'] !== 'pending') {
                    throw new DomainException('Deze sollicitatie is al beoordeeld.', 409);
                }
                if (!$application['email_verified_at']) {
                    throw new DomainException('De sollicitant moet eerst het e-mailadres bevestigen.', 409);
                }
                $statement = $db->prepare(
                    'UPDATE applications SET status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP()
                     WHERE id = ? AND company_id = ? AND status = \'pending\''
                );
                $statement->execute([$status, $employee['id'], $id, $company['id']]);
                $variables = ['name' => $application['name'], 'company_name' => $company['name']];
                $template = 'application_rejected';
                if ($status === 'approved') {
                    $token = Auth::issueToken($db, [
                        'id' => (int) $application['user_id'], 'company_id' => (int) $company['id'],
                    ], 'set_password');
                    $variables['setup_url'] = Http::tokenUrl('/set-password.html', $company['slug'], $token);
                    $template = 'set_password';
                }
                Mailer::queue($db, $company, $application['email'], $template, $variables);
            });
        } catch (DomainException $exception) {
            Http::error($exception->getMessage(), $exception->getCode());
        }
        Http::json(['message' => $status === 'approved' ? 'Sollicitatie goedgekeurd. De toegangsmail staat klaar.' : 'Sollicitatie afgewezen. De afwijzingsmail staat klaar.']);
    }

    public static function download(int $id): never
    {
        $application = self::authorizedApplication($id);
        $type = $_GET['type'] ?? 'cv';
        if (!in_array($type, ['cv', 'letter'], true)) {
            Http::error('Ongeldig documenttype.', 422);
        }
        $path = Uploads::absolute($application[$type . '_path']);
        if (!is_file($path)) {
            Http::error('Het document is niet beschikbaar.', 404);
        }
        $name = basename($application[$type . '_name']);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($extension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        header("Content-Disposition: attachment; filename=\"document.{$extension}\"; filename*=UTF-8''" . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private static function authorizedApplication(int $id): array
    {
        $company = Http::company();
        $user = Http::requireUser(['employee', 'applicant']);
        $query = self::selection() . ' WHERE a.id = ? AND a.company_id = ?';
        $params = [$id, $company['id']];
        if ($user['role'] === 'applicant') {
            $query .= ' AND a.user_id = ?';
            $params[] = $user['id'];
        } else {
            $query .= ' AND a.department = ?';
            $params[] = $user['department'];
        }
        $statement = Database::connection()->prepare($query);
        $statement->execute($params);
        $application = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$application) {
            Http::error('Sollicitatie niet gevonden.', 404);
        }
        return $application;
    }

    private static function selection(): string
    {
        return 'SELECT a.*, u.name, u.email, u.email_verified_at, r.name AS reviewer_name
                FROM applications a JOIN users u ON u.id = a.user_id AND u.company_id = a.company_id
                LEFT JOIN users r ON r.id = a.reviewed_by AND r.company_id = a.company_id';
    }

    private static function present(array $application): array
    {
        // De browser krijgt documentnamen, maar hoeft het interne opslagpad niet te kennen.
        unset($application['cv_path'], $application['letter_path']);
        $application['id'] = (int) $application['id'];
        return $application;
    }
}
