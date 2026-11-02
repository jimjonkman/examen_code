<?php

declare(strict_types=1);

final class AdminController
{
    public static function companies(): never
    {
        Http::requireUser(['admin']);
        // Bewuste uitzondering: alleen het beheerpaneel heeft toegang over bedrijfsgrenzen.
        $rows = Database::connection()->query('SELECT * FROM companies ORDER BY name, id')->fetchAll(PDO::FETCH_ASSOC);
        Http::json(['companies' => array_map(self::presentCompany(...), $rows)]);
    }

    public static function createCompany(): never
    {
        Http::requireUser(['admin']);
        Http::csrf();
        $input = Http::input();
        try {
            $values = self::companyValues($input);
            $outlook = self::outlookValues($input);
            $company = Database::transaction(function (PDO $db) use ($input, $values, $outlook): array {
                $statement = $db->prepare('INSERT INTO companies (name, slug, mail_from_name, mail_from_address, is_active, mail_templates) VALUES (?, ?, ?, ?, ?, ?)');
                $statement->execute(array_values($values));
                $companyId = (int) $db->lastInsertId();
                self::writeOutlook($db, $companyId, $outlook);
                self::saveTheme($values['slug'], $input);
                $statement = $db->prepare('SELECT * FROM companies WHERE id = ?');
                $statement->execute([$companyId]);
                return $statement->fetch(PDO::FETCH_ASSOC);
            });
        } catch (DomainException $exception) {
            Http::error($exception->getMessage(), $exception->getCode() ?: 422);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                Http::error('Deze slug is al in gebruik.', 409);
            }
            throw $exception;
        }
        Http::json(['message' => 'Het bedrijf is aangemaakt.', 'company' => self::presentCompany($company)], 201);
    }

    public static function updateCompany(int $id): never
    {
        Http::requireUser(['admin']);
        Http::csrf();
        $input = Http::input();
        try {
            $company = Database::transaction(function (PDO $db) use ($input, $id): array {
                $statement = $db->prepare('SELECT * FROM companies WHERE id = ? FOR UPDATE');
                $statement->execute([$id]);
                $existing = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    throw new DomainException('Bedrijf niet gevonden.', 404);
                }
                $values = self::companyValues($input, $existing);
                $outlook = self::outlookValues($input, self::readOutlook($id));
                $statement = $db->prepare('UPDATE companies SET name = ?, slug = ?, mail_from_name = ?, mail_from_address = ?, is_active = ?, mail_templates = ? WHERE id = ?');
                $statement->execute([...array_values($values), $id]);
                self::writeOutlook($db, $id, $outlook);
                if ($existing['slug'] !== $values['slug']) {
                    Theme::rename($existing['slug'], $values['slug']);
                }
                self::saveTheme($values['slug'], $input);
                $statement = $db->prepare('SELECT * FROM companies WHERE id = ?');
                $statement->execute([$id]);
                return $statement->fetch(PDO::FETCH_ASSOC);
            });
        } catch (DomainException $exception) {
            Http::error($exception->getMessage(), $exception->getCode() ?: 422);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                Http::error('Deze slug is al in gebruik.', 409);
            }
            throw $exception;
        }
        Http::json(['message' => 'Het bedrijf is bijgewerkt.', 'company' => self::presentCompany($company)]);
    }

    public static function employees(): never
    {
        Http::requireUser(['admin']);
        $companyId = self::companyId($_GET['company_id'] ?? null);
        $db = Database::connection();
        $statement = $db->prepare("SELECT id, company_id, name, email, department, is_active, email_verified_at, created_at FROM users WHERE company_id = ? AND role = 'employee' ORDER BY name, id");
        $statement->execute([$companyId]);
        $employees = $statement->fetchAll(PDO::FETCH_ASSOC);
        $hours = $db->prepare('SELECT weekday, start_time, end_time FROM working_hours WHERE company_id = ? AND employee_id = ? ORDER BY weekday, start_time');
        foreach ($employees as &$employee) {
            $hours->execute([$companyId, $employee['id']]);
            $employee['working_hours'] = $hours->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($employee);
        Http::json(['employees' => $employees]);
    }

    public static function createEmployee(): never
    {
        Http::requireUser(['admin']);
        Http::csrf();
        $input = Http::input();
        $companyId = self::companyId($input['company_id'] ?? null);
        $name = self::text($input['name'] ?? '');
        $email = strtolower(self::text($input['email'] ?? ''));
        if ($name === '' || strlen($name) > 150 || preg_match('/[\x00-\x1F]/u', $name)) {
            Http::error('Vul een geldige medewerkersnaam in.', 422);
        }
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Http::error('Vul een geldig e-mailadres in.', 422);
        }
        try {
            $department = Department::validate($input['department'] ?? null);
            $hours = self::workingHours($input['working_hours'] ?? self::defaultHours());
            $employeeId = Database::transaction(function (PDO $db) use ($companyId, $name, $email, $department, $hours): int {
                $statement = $db->prepare('SELECT * FROM companies WHERE id = ? AND is_active = 1 FOR UPDATE');
                $statement->execute([$companyId]);
                $company = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$company) {
                    throw new DomainException('Het bedrijf moet actief zijn om medewerkers toe te voegen.', 409);
                }
                $statement = $db->prepare("INSERT INTO users (company_id, role, name, email, department, password_hash, must_change_password, is_active) VALUES (?, 'employee', ?, ?, ?, NULL, 0, 1)");
                $statement->execute([$companyId, $name, $email, $department]);
                $id = (int) $db->lastInsertId();
                self::replaceHours($db, $companyId, $id, $hours);
                $token = Auth::issueToken($db, ['id' => $id, 'company_id' => $companyId], 'set_password');
                Mailer::queue($db, $company, $email, 'set_password', [
                    'name' => $name, 'company_name' => $company['name'],
                    'setup_url' => Http::tokenUrl('/set-password.html', $company['slug'], $token),
                ]);
                return $id;
            });
        } catch (DomainException $exception) {
            Http::error($exception->getMessage(), $exception->getCode() ?: 422);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                Http::error('Dit e-mailadres is al bij dit bedrijf geregistreerd.', 409);
            }
            throw $exception;
        }
        Http::json(['message' => 'De medewerker is aangemaakt. De uitnodigingsmail staat klaar.', 'employee' => ['id' => $employeeId, 'company_id' => $companyId, 'name' => $name, 'email' => $email, 'department' => $department, 'is_active' => 1]], 201);
    }

    public static function updateEmployee(int $id): never
    {
        Http::requireUser(['admin']);
        Http::csrf();
        $input = Http::input();
        $companyId = self::companyId($input['company_id'] ?? null);
        try {
            $department = array_key_exists('department', $input) ? Department::validate($input['department']) : null;
            $hours = array_key_exists('working_hours', $input) ? self::workingHours($input['working_hours']) : null;
            Database::transaction(function (PDO $db) use ($input, $companyId, $id, $department, $hours): void {
                $statement = $db->prepare("SELECT id, department, is_active FROM users WHERE id = ? AND company_id = ? AND role = 'employee' FOR UPDATE");
                $statement->execute([$id, $companyId]);
                $employee = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$employee) {
                    throw new DomainException('Medewerker niet gevonden.', 404);
                }
                $active = array_key_exists('is_active', $input) ? self::boolean($input['is_active']) : (int) $employee['is_active'];
                $newDepartment = $department ?? $employee['department'];
                if ($hours !== null) {
                    self::replaceHours($db, $companyId, $id, $hours);
                }
                $statement = $db->prepare('UPDATE users SET is_active = ?, department = ? WHERE id = ? AND company_id = ?');
                $statement->execute([$active, $newDepartment, $id, $companyId]);
                if (!$active) {
                    // Reeds verstuurde activatielinks mogen een gedeactiveerd account niet heropenen.
                    $db->prepare('UPDATE auth_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND company_id = ? AND used_at IS NULL')->execute([$id, $companyId]);
                }
            });
        } catch (DomainException $exception) {
            Http::error($exception->getMessage(), $exception->getCode() ?: 422);
        }
        Http::json(['message' => 'De medewerker is bijgewerkt.']);
    }

    private static function companyValues(array $input, array $existing = []): array
    {
        $name = self::text($input['name'] ?? $existing['name'] ?? '');
        $slug = self::text($input['slug'] ?? $existing['slug'] ?? '');
        $fromName = self::text($input['mail_from_name'] ?? $existing['mail_from_name'] ?? $name);
        $fromAddress = self::text($input['mail_from_address'] ?? $existing['mail_from_address'] ?? '');
        if ($name === '' || strlen($name) > 150 || $fromName === '' || strlen($fromName) > 150 || preg_match('/[\x00-\x1F]/u', $name . $fromName)) {
            throw new DomainException('Vul een bedrijfsnaam en afzendernaam van maximaal 150 tekens in.', 422);
        }
        if (!Theme::validSlug($slug)) {
            throw new DomainException('Gebruik voor de slug alleen kleine letters, cijfers en enkele koppeltekens.', 422);
        }
        if (strlen($fromAddress) > 254 || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Vul een geldig afzenderadres in.', 422);
        }
        $templates = $input['mail_templates'] ?? $existing['mail_templates'] ?? null;
        if (is_string($templates) && trim($templates) !== '') {
            $templates = json_decode($templates, true);
            if (!is_array($templates)) {
                throw new DomainException('E-mailsjablonen moeten geldige JSON bevatten.', 422);
            }
        }
        if ($templates === '') {
            $templates = null;
        }
        if ($templates !== null) {
            if (!is_array($templates) || array_is_list($templates) && $templates !== []) {
                throw new DomainException('E-mailsjablonen moeten een object zijn.', 422);
            }
            $allowed = ['verify_email', 'application_received', 'application_rejected', 'set_password', 'appointment_confirmation', 'appointment_cancelled', 'employee_appointment_confirmation', 'employee_appointment_cancelled'];
            foreach ($templates as $key => $template) {
                if (!in_array($key, $allowed, true) || !is_array($template) || !isset($template['subject'], $template['body'])
                    || !is_string($template['subject']) || !is_string($template['body'])
                    || strlen($template['subject']) > 200 || strlen($template['body']) > 10000
                    || preg_match('/[\r\n]/', $template['subject'])) {
                    throw new DomainException('Elk e-mailsjabloon vereist subject (maximaal 200 tekens) en body (maximaal 10000 tekens).', 422);
                }
            }
            $templates = json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        return [
            'name' => $name, 'slug' => $slug, 'mail_from_name' => $fromName,
            'mail_from_address' => $fromAddress,
            'is_active' => self::boolean($input['is_active'] ?? $existing['is_active'] ?? 1),
            'mail_templates' => $templates,
        ];
    }

    private static function saveTheme(string $slug, array $input): void
    {
        $theme = Theme::read($slug);
        $nested = $input['theme'] ?? [];
        if (is_string($nested)) {
            $nested = json_decode($nested, true);
        }
        if (!is_array($nested)) {
            throw new DomainException('Het thema moet geldige JSON bevatten.', 422);
        }
        foreach (['primary_color', 'secondary_color', 'font_family'] as $key) {
            if (array_key_exists($key, $nested) || array_key_exists($key, $input)) {
                $theme[$key] = $nested[$key] ?? $input[$key];
            }
        }
        $uploaded = [];
        try {
            foreach (['logo', 'font_file'] as $key) {
                if (isset($_FILES[$key]) && ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $theme[$key] = Uploads::themeAsset($_FILES[$key], $slug, $key === 'logo' ? 'logo' : 'font');
                    $uploaded[] = $theme[$key];
                } elseif (!empty($input['remove_' . $key])) {
                    $theme[$key] = null;
                }
            }
            Theme::save($slug, $theme);
        } catch (Throwable $exception) {
            foreach ($uploaded as $name) {
                Uploads::removeThemeAsset($slug, $name);
            }
            throw $exception;
        }
    }

    private static function outlookValues(array $input, array $existing = []): array
    {
        $nested = $input['outlook'] ?? [];
        if (is_string($nested)) {
            $nested = json_decode($nested, true);
        }
        if (!is_array($nested)) {
            throw new DomainException('Outlook-instellingen moeten geldige JSON bevatten.', 422);
        }
        $values = [];
        foreach (['tenant_id', 'client_id', 'secret_ref'] as $key) {
            $value = self::text($nested[$key] ?? $input[$key] ?? $existing[$key] ?? '');
            if (strlen($value) > 120 || ($value !== '' && !preg_match($key === 'secret_ref' ? '/^[A-Z][A-Z0-9_]{2,119}$/D' : '/^[A-Za-z0-9][A-Za-z0-9.-]{0,119}$/D', $value))) {
                throw new DomainException($key === 'secret_ref' ? 'Secret-ref moet de naam van een omgevingsvariabele zijn, bijvoorbeeld OUTLOOK_CLIENT_SECRET.' : 'Ongeldige Outlook-instelling.', 422);
            }
            $values[$key] = $value === '' ? null : $value;
        }
        return $values;
    }

    private static function readOutlook(int $companyId): array
    {
        $statement = Database::connection()->prepare('SELECT tenant_id, client_id, secret_ref FROM company_outlook_settings WHERE company_id = ?');
        $statement->execute([$companyId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: ['tenant_id' => null, 'client_id' => null, 'secret_ref' => null];
    }

    private static function writeOutlook(PDO $db, int $companyId, array $values): void
    {
        $statement = $db->prepare('INSERT INTO company_outlook_settings (company_id, tenant_id, client_id, secret_ref) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), client_id = VALUES(client_id), secret_ref = VALUES(secret_ref)');
        $statement->execute([$companyId, $values['tenant_id'], $values['client_id'], $values['secret_ref']]);
    }

    private static function presentCompany(array $company): array
    {
        $company['id'] = (int) $company['id'];
        $company['is_active'] = (int) $company['is_active'];
        $company['mail_templates'] = json_decode((string) ($company['mail_templates'] ?? ''), true) ?: new stdClass();
        $company['theme'] = Theme::read($company['slug']);
        $company['outlook'] = self::readOutlook((int) $company['id']);
        $company['application_url'] = Http::url('/apply.html', ['company' => $company['slug']]);
        return $company;
    }

    private static function companyId(mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            Http::error('Kies een geldig bedrijf.', 422);
        }
        $id = (int) $value;
        $statement = Database::connection()->prepare('SELECT id FROM companies WHERE id = ?');
        $statement->execute([$id]);
        if (!$statement->fetch()) {
            Http::error('Bedrijf niet gevonden.', 404);
        }
        return $id;
    }

    private static function boolean(mixed $value): int
    {
        if (in_array($value, [true, 1, '1'], true)) {
            return 1;
        }
        if (in_array($value, [false, 0, '0'], true)) {
            return 0;
        }
        throw new DomainException('Actief moet true of false zijn.', 422);
    }

    private static function text(mixed $value): string
    {
        if (!is_string($value)) {
            throw new DomainException('Een tekstveld heeft een ongeldige waarde.', 422);
        }
        return trim($value);
    }

    private static function defaultHours(): array
    {
        return array_map(static fn (int $day): array => ['weekday' => $day, 'start_time' => '09:00', 'end_time' => '17:00'], range(1, 5));
    }

    private static function workingHours(mixed $hours): array
    {
        if (is_string($hours)) {
            $hours = json_decode($hours, true);
        }
        if (!is_array($hours) || count($hours) > 28) {
            throw new DomainException('Werktijden moeten een lijst met maximaal 28 intervallen zijn.', 422);
        }
        $result = [];
        foreach ($hours as $hour) {
            if (!is_array($hour)) {
                throw new DomainException('Ongeldige werktijden.', 422);
            }
            $day = filter_var($hour['weekday'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 7]]);
            $start = self::text($hour['start_time'] ?? '');
            $end = self::text($hour['end_time'] ?? '');
            if (!$day || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::00)?$/D', $start)
                || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::00)?$/D', $end)
                || substr($start, 0, 5) >= substr($end, 0, 5)) {
                throw new DomainException('Gebruik weekdagen 1–7 en geldige begin- en eindtijden op dezelfde dag.', 422);
            }
            $result[] = ['weekday' => $day, 'start_time' => substr($start, 0, 5), 'end_time' => substr($end, 0, 5)];
        }
        usort($result, static fn (array $a, array $b): int => [$a['weekday'], $a['start_time']] <=> [$b['weekday'], $b['start_time']]);
        $previous = null;
        foreach ($result as $hour) {
            if ($previous && $hour['weekday'] === $previous['weekday'] && $hour['start_time'] < $previous['end_time']) {
                throw new DomainException('Werktijden mogen elkaar niet overlappen.', 422);
            }
            $previous = $hour;
        }
        return $result;
    }

    private static function replaceHours(PDO $db, int $companyId, int $employeeId, array $hours): void
    {
        $db->prepare('DELETE FROM working_hours WHERE company_id = ? AND employee_id = ?')->execute([$companyId, $employeeId]);
        $statement = $db->prepare('INSERT INTO working_hours (company_id, employee_id, weekday, start_time, end_time) VALUES (?, ?, ?, ?, ?)');
        foreach ($hours as $hour) {
            $statement->execute([$companyId, $employeeId, $hour['weekday'], $hour['start_time'], $hour['end_time']]);
        }
    }
}
