<?php

declare(strict_types=1);

/** Dezelfde afspraaklogica kan later een externe agenda gebruiken. */
interface CalendarProvider
{
    /** @return array<array{starts_at: string, ends_at: string}> UTC-datums. */
    public function getBusy(int $employeeId, DateTimeImmutable $from, DateTimeImmutable $until): array;

    /** Geeft alleen een externe event-id terug wanneer er werkelijk is gesynchroniseerd. */
    public function createEvent(array $appointment, array $employee, array $applicant): ?string;
}
