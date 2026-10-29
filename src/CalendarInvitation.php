<?php
declare(strict_types=1);

/** iCalendar-uitnodiging per e-mail; geen rechtstreekse agenda-synchronisatie. */
final class CalendarInvitation
{
    public static function make(array $company, array $appointment, array $employee, array $applicant, bool $cancelled = false, bool $includeApplicant = true): array
    {
        $attendees = $includeApplicant ? [$employee, $applicant] : [$employee];
        foreach ([$company['mail_from_address'], ...array_column($attendees, 'email')] as $address) {
            if (!is_string($address) || !filter_var($address, FILTER_VALIDATE_EMAIL) || strpbrk($address, "\r\n") !== false) {
                throw new RuntimeException('Ongeldig agenda-e-mailadres.');
            }
        }
        $utc = new DateTimeZone('UTC');
        // Expliciete offsets worden gerespecteerd; datums uit de database zijn UTC.
        $start = new DateTimeImmutable($appointment['starts_at'], $utc);
        $end = new DateTimeImmutable($appointment['ends_at'], $utc);
        if ($end <= $start) {
            throw new RuntimeException('Ongeldige agendaperiode.');
        }
        $method = $cancelled ? 'CANCEL' : 'REQUEST';
        $description = 'Sollicitatiegesprek bij ' . $company['name'] . ".\n"
            . 'Sollicitant: ' . $applicant['name'] . "\n"
            . 'Medewerker: ' . $employee['name'] . "\n\n"
            . ($cancelled ? 'Deze afspraak is geannuleerd.' : 'Bekijk of annuleer de afspraak in het sollicitatieportaal. Een antwoord in je agenda wijzigt de afspraak in het portaal niet.');
        $lines = [
            'BEGIN:VCALENDAR', 'PRODID:-//Sollicitatieportaal//Gesprekken//NL',
            'VERSION:2.0', 'CALSCALE:GREGORIAN', 'METHOD:' . $method,
            'BEGIN:VEVENT', 'UID:' . self::uid($company, $appointment), 'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z'),
            'DTEND:' . $end->setTimezone($utc)->format('Ymd\THis\Z'),
            'SEQUENCE:' . ($cancelled ? '1' : '0'),
            'STATUS:' . ($cancelled ? 'CANCELLED' : 'CONFIRMED'),
            'SUMMARY:' . self::text('Sollicitatiegesprek: ' . $applicant['name'] . ' bij ' . $company['name']),
            'DESCRIPTION:' . self::text($description),
            'ORGANIZER;CN=' . self::parameter($company['mail_from_name']) . ':mailto:' . $company['mail_from_address'],
        ];
        foreach ($attendees as $attendee) {
            $lines[] = 'ATTENDEE;CN=' . self::parameter($attendee['name'])
                . ';CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT'
                . ($cancelled ? '' : ';PARTSTAT=NEEDS-ACTION;RSVP=TRUE') . ':mailto:' . $attendee['email'];
        }
        array_push($lines, 'TRANSP:OPAQUE', 'END:VEVENT', 'END:VCALENDAR');
        return ['method' => $method, 'content' => implode("\r\n", array_map(self::fold(...), $lines)) . "\r\n"];
    }

    public static function uid(array $company, array $appointment): string
    {
        // Alleen onveranderlijke databasewaarden: verhuizen, een slug wijzigen of
        // een APP_KEY roteren mag de UID van een bestaande afspraak niet wijzigen.
        $identity = json_encode([
            (string) $company['id'], (string) $company['created_at'],
            (string) $appointment['id'], (string) $appointment['created_at'],
        ], JSON_THROW_ON_ERROR);
        return hash('sha256', $identity) . '@application-portal.invalid';
    }

    private static function text(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        return str_replace(["\\", "\r\n", "\n", "\r", ';', ','], ["\\\\", '\\n', '\\n', '\\n', '\\;', '\\,'], $value);
    }

    private static function parameter(string $value): string
    {
        // RFC 6868 parameter-escaping binnen een quoted-string.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        return '"' . str_replace(['^', "\r\n", "\n", "\r", '"'], ['^^', '^n', '^n', '^n', "^'"], $value) . '"';
    }

    private static function fold(string $line): string
    {
        $folded = '';
        $limit = 75;
        while (strlen($line) > $limit) {
            // mb_strcut splitst op bytes zonder een UTF-8-teken te doorbreken.
            $part = mb_strcut($line, 0, $limit, 'UTF-8');
            $folded .= $part . "\r\n ";
            $line = substr($line, strlen($part));
            $limit = 74; // De voortzettingsspatie telt mee in de 75 octetten.
        }
        return $folded . $line;
    }
}
