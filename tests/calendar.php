<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$company = ['id' => 42, 'created_at' => '2026-01-12 10:00:00', 'name' => 'Voorbeeld BV', 'mail_from_name' => 'Voorbeeldteam', 'mail_from_address' => 'team@example.test'];
$appointment = ['id' => 17, 'created_at' => '2026-10-20 10:00:00', 'starts_at' => '2027-07-19T09:00:00+02:00', 'ends_at' => '2027-07-19T09:30:00+02:00'];
$employee = ['name' => 'Medewerker', 'email' => 'employee@example.test'];
$applicant = ['name' => 'Sollicitant', 'email' => 'applicant@example.test'];
$tests = [];
$check = static function (bool $condition): void {
    if (!$condition) {
        throw new RuntimeException('Onverwachte agenda-inhoud.');
    }
};
$unfold = static fn (string $content): string => str_replace(["\r\n ", "\r\n\t"], '', $content);
$uid = static function (array $invitation) use ($unfold): string {
    if (!preg_match('/\r\nUID:([^\r\n]+)\r\n/', $unfold($invitation['content']), $match)) {
        throw new RuntimeException('Agenda mist een UID.');
    }
    return $match[1];
};

$tests['Uitnodiging bevat UTC-zomertijd, afzender en beide deelnemers'] = static function () use ($company, $appointment, $employee, $applicant, $check, $unfold): void {
    $invitation = CalendarInvitation::make($company, $appointment, $employee, $applicant);
    $content = $unfold($invitation['content']);
    $check($invitation['method'] === 'REQUEST');
    foreach (['METHOD:REQUEST', 'DTSTART:20270719T070000Z', 'DTEND:20270719T073000Z', 'SEQUENCE:0', 'STATUS:CONFIRMED', 'ORGANIZER;CN="Voorbeeldteam":mailto:team@example.test', ':mailto:employee@example.test', ':mailto:applicant@example.test'] as $expected) {
        $check(str_contains($content, $expected));
    }
    $check(substr_count($content, "\r\nATTENDEE;") === 2);
    $check(substr_count($content, 'RSVP=TRUE') === 2);
};
$tests['Annulering houdt dezelfde UID en verhoogt SEQUENCE'] = static function () use ($company, $appointment, $employee, $applicant, $check, $unfold, $uid): void {
    $request = CalendarInvitation::make($company, $appointment, $employee, $applicant);
    $cancel = CalendarInvitation::make($company, $appointment, $employee, $applicant, true);
    $check($uid($request) === $uid($cancel));
    $content = $unfold($cancel['content']);
    $check($cancel['method'] === 'CANCEL' && str_contains($content, 'METHOD:CANCEL'));
    $check(str_contains($content, 'SEQUENCE:1') && str_contains($content, 'STATUS:CANCELLED'));
    $check(substr_count($content, "\r\nATTENDEE;") === 2);
};
$tests['Accountverwijdering annuleert voor medewerker zonder gegevens van sollicitant'] = static function () use ($company, $appointment, $employee, $applicant, $check, $unfold, $uid): void {
    $person = $applicant;
    $person['name'] = 'Persoon Verwijderd';
    $request = CalendarInvitation::make($company, $appointment, $employee, $person);
    $cancel = CalendarInvitation::make($company, $appointment, $employee, ['name' => 'een verwijderde sollicitant'], true, false);
    $content = $unfold($cancel['content']);
    $check($uid($request) === $uid($cancel) && $cancel['method'] === 'CANCEL');
    $check(substr_count($content, "\r\nATTENDEE;") === 1);
    $check(str_contains($content, ':mailto:employee@example.test'));
    $check(!str_contains($content, $person['email']) && !str_contains($content, $person['name']));
};
$tests['UID blijft stabiel bij nieuwe bedrijfsnaam en verandert per afspraak en bedrijf'] = static function () use ($company, $appointment, $employee, $applicant, $check, $uid): void {
    $original = $uid(CalendarInvitation::make($company, $appointment, $employee, $applicant));
    $renamed = $company;
    $renamed['name'] = 'Nieuwe bedrijfsnaam';
    $check($original === $uid(CalendarInvitation::make($renamed, $appointment, $employee, $applicant)));
    $other = $appointment;
    $other['id']++;
    $check($original !== $uid(CalendarInvitation::make($company, $other, $employee, $applicant)));
    $otherCompany = $company;
    $otherCompany['id']++;
    $check($original !== $uid(CalendarInvitation::make($otherCompany, $appointment, $employee, $applicant)));
};
$tests['Wintertijd en UTC-datums uit database worden niet verschoven'] = static function () use ($company, $appointment, $employee, $applicant, $check, $unfold): void {
    $winter = $appointment;
    $winter['starts_at'] = '2027-01-18T09:00:00+01:00';
    $winter['ends_at'] = '2027-01-18T09:30:00+01:00';
    $offset = $unfold(CalendarInvitation::make($company, $winter, $employee, $applicant)['content']);
    $winter['starts_at'] = '2027-01-18 08:00:00';
    $winter['ends_at'] = '2027-01-18 08:30:00';
    $database = $unfold(CalendarInvitation::make($company, $winter, $employee, $applicant)['content']);
    $check(str_contains($offset, 'DTSTART:20270118T080000Z'));
    $check(str_contains($database, 'DTSTART:20270118T080000Z'));
    $check(str_contains($database, 'DTEND:20270118T083000Z'));
};
$tests['Namen ontsnappen tekst en parameters zonder nieuwe agendaregels'] = static function () use ($company, $appointment, $employee, $applicant, $check, $unfold): void {
    $person = $applicant;
    $person['name'] = "Jos\"é ^ Test, Team; A\\B\r\nBEGIN:VALARM";
    $content = $unfold(CalendarInvitation::make($company, $appointment, $employee, $person)['content']);
    $check(!str_contains($content, "\r\nBEGIN:VALARM"));
    $check(str_contains($content, 'Jos"é ^ Test\\, Team\\; A\\\\B\\nBEGIN:VALARM'));
    $check(str_contains($content, 'CN="Jos^\'é ^^ Test, Team; A\\B^nBEGIN:VALARM"'));
};
$tests['Lange UTF-8-regels vouwen naar maximaal 75 bytes en ontvouwen zonder verlies'] = static function () use ($company, $appointment, $employee, $applicant, $check, $unfold): void {
    $person = $applicant;
    $person['name'] = str_repeat('é🐱', 40);
    $content = CalendarInvitation::make($company, $appointment, $employee, $person)['content'];
    foreach (explode("\r\n", $content) as $line) {
        $check(strlen($line) <= 75 && mb_check_encoding($line, 'UTF-8'));
    }
    $check(str_contains($unfold($content), 'SUMMARY:Sollicitatiegesprek: ' . $person['name'] . ' bij Voorbeeld BV'));
    $check(!preg_match('/(?<!\r)\n|\r(?!\n)/', $content));
};
$tests['Ongeldige deelnemers en omgekeerde periode worden geweigerd'] = static function () use ($company, $appointment, $employee, $applicant, $check): void {
    foreach (['email', 'period'] as $invalid) {
        $person = $applicant;
        $event = $appointment;
        if ($invalid === 'email') {
            $person['email'] = "applicant@example.test\r\nBEGIN:VALARM";
        } else {
            $event['ends_at'] = $event['starts_at'];
        }
        $rejected = false;
        try {
            CalendarInvitation::make($company, $event, $employee, $person);
        } catch (RuntimeException) {
            $rejected = true;
        }
        $check($rejected);
    }
};
$tests['Agenda-payload kan versleuteld in de bestaande outbox worden bewaard'] = static function () use ($company, $appointment, $employee, $applicant, $check): void {
    $GLOBALS['config']['key'] = 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    $payload = ['body' => 'Testmail', 'calendar' => CalendarInvitation::make($company, $appointment, $employee, $applicant)];
    $encrypted = Mailer::encrypt($payload);
    $check(Mailer::decrypt($encrypted) === $payload && !str_contains($encrypted, 'applicant@example.test'));
};

$failed = 0;
foreach ($tests as $label => $test) {
    try {
        $test();
        echo '[OK] ' . $label . PHP_EOL;
    } catch (Throwable $error) {
        $failed++;
        fwrite(STDERR, '[FAIL] ' . $label . ': ' . $error->getMessage() . PHP_EOL);
    }
}
echo (count($tests) - $failed) . '/' . count($tests) . " agendacontroles geslaagd.\n";
exit($failed > 0 ? 1 : 0);
