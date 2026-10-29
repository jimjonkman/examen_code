<?php

declare(strict_types=1);

final class SchedulingController
{
    private const ZONE = 'Europe/Amsterdam';

    public static function slots(): never
    {
        $company = Http::company();
        $user = Http::requireUser(['applicant']);
        $db = Database::connection();
        $application = self::application($db, (int) $company['id'], (int) $user['id']);
        $dateValue = $_GET['date'] ?? (new DateTimeImmutable('now', new DateTimeZone(self::ZONE)))->format('Y-m-d');
        if (!is_string($dateValue)) {
            Http::error('Kies een geldige datum.', 422);
        }
        $date = self::day($dateValue);
        $slots = self::daySlots($db, (int) $company['id'], (int) $application['reviewed_by'], $date);
        $statement = $db->prepare("SELECT id, starts_at, ends_at, status FROM appointments WHERE company_id = ? AND application_id = ? AND status = 'scheduled'");
        $statement->execute([$company['id'], $application['id']]);
        $appointment = $statement->fetch(PDO::FETCH_ASSOC);
        Http::json([
            'date' => $date->format('Y-m-d'),
            'employee' => ['id' => (int) $application['reviewed_by'], 'name' => $application['employee_name']],
            'application' => ['id' => (int) $application['id'], 'status' => $application['status']],
            'slots' => $slots,
            'appointment' => $appointment ? self::present($appointment) : null,
        ]);
    }

    public static function create(): never
    {
        $company = Http::company();
        $user = Http::requireUser(['applicant']);
        Http::csrf();
        Http::limit('booking:' . $user['id'], 20, 3600);
        $rawStart = Http::input()['starts_at'] ?? '';
        // Een expliciete offset voorkomt een stilzwijgende verschuiving tussen browser en server.
        if (!is_string($rawStart) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $rawStart)) {
            Http::error('Kies een geldig tijdslot uit de agenda.', 422);
        }
        try {
            $start = new DateTimeImmutable($rawStart);
        } catch (Exception) {
            Http::error('Kies een geldig tijdslot uit de agenda.', 422);
        }
        $start = $start->setTimezone(new DateTimeZone(self::ZONE));
        $date = self::day($start->format('Y-m-d'));
        try {
            $appointment = Database::transaction(function (PDO $db) use ($company, $user, $start, $date): array {
                $application = self::application($db, (int) $company['id'], (int) $user['id'], true);
                // Alle boekingen voor dezelfde medewerker nemen dezelfde rijvergrendeling.
                // Ook overlappende begintijden worden hierdoor correct geserialiseerd.
                $statement = $db->prepare("SELECT id, name, email FROM users WHERE id = ? AND company_id = ? AND role = 'employee' AND is_active = 1 FOR UPDATE");
                $statement->execute([$application['reviewed_by'], $company['id']]);
                $employee = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$employee) {
                    throw new DomainException('De medewerker is momenteel niet beschikbaar.', 409);
                }
                $statement = $db->prepare("SELECT id FROM appointments WHERE company_id = ? AND application_id = ? AND status = 'scheduled' FOR UPDATE");
                $statement->execute([$company['id'], $application['id']]);
                if ($statement->fetch()) {
                    throw new DomainException('Je hebt al een actieve afspraak. Annuleer die eerst.', 409);
                }
                $chosen = null;
                foreach (self::daySlots($db, (int) $company['id'], (int) $employee['id'], $date) as $slot) {
                    if ($slot['starts_at'] === $start->format(DateTimeInterface::ATOM) && $slot['available']) {
                        $chosen = $slot;
                        break;
                    }
                }
                if (!$chosen) {
                    throw new DomainException('Dit tijdslot is niet meer beschikbaar. Kies een ander tijdslot.', 409);
                }
                $utc = new DateTimeZone('UTC');
                $end = $start->modify('+30 minutes');
                $statement = $db->prepare("INSERT INTO appointments (company_id, application_id, employee_id, starts_at, ends_at, status) VALUES (?, ?, ?, ?, ?, 'scheduled')");
                $statement->execute([
                    $company['id'], $application['id'], $employee['id'],
                    $start->setTimezone($utc)->format('Y-m-d H:i:s'), $end->setTimezone($utc)->format('Y-m-d H:i:s'),
                ]);
                $appointmentId = (int) $db->lastInsertId();
                $statement = $db->prepare('SELECT id, starts_at, ends_at, status, created_at FROM appointments WHERE id = ? AND company_id = ?');
                $statement->execute([$appointmentId, $company['id']]);
                $appointment = $statement->fetch(PDO::FETCH_ASSOC);
                // De provider bewaart lokaal; Outlook krijgt een uitnodiging via de outbox.
                (new LocalCalendarProvider($db, (int) $company['id']))->createEvent($appointment, $employee, $user);
                AppointmentNotifications::queue($db, $company, $appointment, $employee, $user);
                unset($appointment['created_at']);
                return self::present($appointment);
            });
        } catch (DomainException $exception) {
            Http::error($exception->getMessage(), $exception->getCode());
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                Http::error('Dit tijdslot is zojuist geboekt. Vernieuw de agenda.', 409);
            }
            throw $exception;
        }
        Http::json(['message' => 'Je gesprek is ingepland. De agenda-uitnodigingen voor jou en de medewerker staan klaar.', 'appointment' => $appointment], 201);
    }

    public static function index(): never
    {
        $company = Http::company();
        $user = Http::requireUser(['employee', 'applicant']);
        $query = 'SELECT p.id, p.application_id, p.employee_id, p.starts_at, p.ends_at, p.status,
                         u.name, u.email, e.name AS employee_name
                  FROM appointments p
                  JOIN applications a ON a.id = p.application_id AND a.company_id = p.company_id
                  JOIN users u ON u.id = a.user_id AND u.company_id = p.company_id
                  JOIN users e ON e.id = p.employee_id AND e.company_id = p.company_id
                  WHERE p.company_id = ?';
        $params = [$company['id']];
        if ($user['role'] === 'employee') {
            $query .= ' AND p.employee_id = ?';
        } else {
            $query .= ' AND a.user_id = ?';
        }
        $params[] = $user['id'];
        $statement = Database::connection()->prepare($query . ' ORDER BY p.starts_at DESC LIMIT 200');
        $statement->execute($params);
        Http::json(['appointments' => array_map(self::present(...), $statement->fetchAll(PDO::FETCH_ASSOC))]);
    }

    public static function cancel(int $id): never
    {
        $company = Http::company();
        $user = Http::requireUser(['employee', 'applicant']);
        Http::csrf();
        try {
            Database::transaction(function (PDO $db) use ($company, $user, $id): void {
                $query = 'SELECT p.*, a.user_id, u.name, u.email, e.name AS employee_name, e.email AS employee_email FROM appointments p
                          JOIN applications a ON a.id = p.application_id AND a.company_id = p.company_id
                          JOIN users u ON u.id = a.user_id AND u.company_id = p.company_id
                          JOIN users e ON e.id = p.employee_id AND e.company_id = p.company_id
                          WHERE p.id = ? AND p.company_id = ?';
                $query .= $user['role'] === 'employee' ? ' AND p.employee_id = ?' : ' AND a.user_id = ?';
                $statement = $db->prepare($query);
                $statement->execute([$id, $company['id'], $user['id']]);
                $appointment = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$appointment) {
                    throw new DomainException('Afspraak niet gevonden.', 404);
                }
                $statement = $db->prepare('SELECT id FROM users WHERE id = ? AND company_id = ? FOR UPDATE');
                $statement->execute([$appointment['employee_id'], $company['id']]);
                $statement = $db->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND company_id = ? AND status = 'scheduled'");
                $statement->execute([$id, $company['id']]);
                if ($statement->rowCount() > 0) {
                    AppointmentNotifications::queue($db, $company, $appointment,
                        ['name' => $appointment['employee_name'], 'email' => $appointment['employee_email']],
                        ['name' => $appointment['name'], 'email' => $appointment['email']], true);
                }
            });
        } catch (DomainException $exception) {
            Http::error($exception->getMessage(), $exception->getCode());
        }
        Http::json(['message' => 'De afspraak is geannuleerd. Je kunt een nieuw tijdslot kiezen.']);
    }

    private static function application(PDO $db, int $companyId, int $userId, bool $lock = false): array
    {
        $statement = $db->prepare(
            "SELECT a.id, a.status, a.reviewed_by, e.name AS employee_name
             FROM applications a JOIN users e ON e.id = a.reviewed_by AND e.company_id = a.company_id
             WHERE a.company_id = ? AND a.user_id = ? AND a.status = 'approved'
             AND e.role = 'employee' AND e.is_active = 1" . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute([$companyId, $userId]);
        $application = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$application) {
            if ($lock) {
                throw new DomainException('Alleen een goedgekeurde sollicitant kan bij de toegewezen medewerker plannen.', 403);
            }
            Http::error('Er is geen goedgekeurde sollicitatie met een beschikbare medewerker.', 403);
        }
        return $application;
    }

    private static function day(string $value): DateTimeImmutable
    {
        $zone = new DateTimeZone(self::ZONE);
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);
        $today = new DateTimeImmutable('today', $zone);
        if (!$day || $day->format('Y-m-d') !== $value || $day < $today || $day > $today->modify('+90 days')) {
            Http::error('Kies een datum van vandaag tot maximaal 90 dagen vooruit.', 422);
        }
        return $day;
    }

    private static function daySlots(PDO $db, int $companyId, int $employeeId, DateTimeImmutable $date): array
    {
        $statement = $db->prepare('SELECT start_time, end_time FROM working_hours WHERE company_id = ? AND employee_id = ? AND weekday = ? ORDER BY start_time');
        $statement->execute([$companyId, $employeeId, (int) $date->format('N')]);
        $hours = $statement->fetchAll(PDO::FETCH_ASSOC);
        $firstHour = 8;
        $lastHour = 18;
        $intervals = [];
        foreach ($hours as $hour) {
            $startParts = array_map('intval', explode(':', $hour['start_time']));
            $endParts = array_map('intval', explode(':', $hour['end_time']));
            $intervals[] = [
                'start' => $date->setTime($startParts[0], $startParts[1], $startParts[2] ?? 0),
                'end' => $date->setTime($endParts[0], $endParts[1], $endParts[2] ?? 0),
            ];
            $firstHour = min($firstHour, $startParts[0]);
            $lastHour = max($lastHour, $endParts[0] + (($endParts[1] > 0 || ($endParts[2] ?? 0) > 0) ? 1 : 0));
        }
        $provider = new LocalCalendarProvider($db, $companyId);
        $busy = $provider->getBusy($employeeId, $date, $date->modify('+1 day'));
        $now = new DateTimeImmutable('now', new DateTimeZone(self::ZONE));
        $slots = [];
        // Toon minimaal 08–18 uur, maar maak ook vroege of late werktijden boekbaar.
        // De rasterlijnen blijven op hele en halve uren; een slot moet volledig passen.
        for ($index = 0; $index < ($lastHour - $firstHour) * 2; $index++) {
            $start = $date->setTime($firstHour, 0)->modify('+' . ($index * 30) . ' minutes');
            $end = $start->modify('+30 minutes');
            $inHours = false;
            foreach ($intervals as $interval) {
                if ($start >= $interval['start'] && $end <= $interval['end']) {
                    $inHours = true;
                    break;
                }
            }
            $available = $inHours && $start > $now;
            foreach ($busy as $interval) {
                if ($start < self::local($interval['ends_at']) && $end > self::local($interval['starts_at'])) {
                    $available = false;
                    break;
                }
            }
            $slots[] = ['starts_at' => $start->format(DateTimeInterface::ATOM), 'ends_at' => $end->format(DateTimeInterface::ATOM), 'available' => $available];
        }
        return $slots;
    }

    private static function local(string $utcDate): DateTimeImmutable
    {
        return (new DateTimeImmutable($utcDate, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(self::ZONE));
    }

    private static function present(array $appointment): array
    {
        $appointment['starts_at'] = self::local($appointment['starts_at'])->format(DateTimeInterface::ATOM);
        $appointment['ends_at'] = self::local($appointment['ends_at'])->format(DateTimeInterface::ATOM);
        $appointment['id'] = (int) $appointment['id'];
        return $appointment;
    }
}
