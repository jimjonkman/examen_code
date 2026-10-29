<?php

declare(strict_types=1);

final class LocalCalendarProvider implements CalendarProvider
{
    public function __construct(private PDO $db, private int $companyId)
    {
    }

    public function getBusy(int $employeeId, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        $statement = $this->db->prepare(
            "SELECT starts_at, ends_at FROM appointments
             WHERE company_id = ? AND employee_id = ? AND status = 'scheduled'
             AND starts_at < ? AND ends_at > ?"
        );
        $utc = new DateTimeZone('UTC');
        $statement->execute([
            $this->companyId,
            $employeeId,
            $until->setTimezone($utc)->format('Y-m-d H:i:s'),
            $from->setTimezone($utc)->format('Y-m-d H:i:s'),
        ]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createEvent(array $appointment, array $employee, array $applicant): ?string
    {
        // Fase 1 bewaart afspraken lokaal; er wordt geen Outlook-synchronisatie gesuggereerd.
        return null;
    }
}
