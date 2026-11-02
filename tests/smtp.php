<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$GLOBALS['config']['environment'] = 'local';
putenv('SMTP_HOST=127.0.0.1');
putenv('SMTP_ENCRYPTION=none');
putenv('SMTP_USERNAME=local-test');
putenv('SMTP_PASSWORD=local-test');
$passed = 0;
$company = ['id' => 42, 'created_at' => '2026-01-12 10:00:00', 'name' => 'Voorbeeld BV', 'mail_from_name' => 'Testteam', 'mail_from_address' => 'team@example.test'];
$appointment = ['id' => 17, 'created_at' => '2026-10-20 10:00:00', 'starts_at' => '2027-07-19T09:00:00+02:00', 'ends_at' => '2027-07-19T09:30:00+02:00'];
$employee = ['name' => 'Medewerker', 'email' => 'employee@example.test'];
$applicant = ['name' => 'Sollicitant', 'email' => 'recipient@example.test'];
foreach (['accept', 'reject', 'request', 'cancel'] as $mode) {
    $capture = $GLOBALS['config']['root'] . '/storage/runtime/smtp-' . bin2hex(random_bytes(8)) . '.eml';
    $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/smtp-server.php', $capture, $mode], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('SMTP-testserver kon niet starten.');
    }
    try {
        $port = trim(fgets($pipes[1]) ?: '');
        if (!ctype_digit($port)) {
            throw new RuntimeException('SMTP-testserver is niet beschikbaar.');
        }
        putenv('SMTP_PORT=' . $port);
        $body = "Beste Sollicitant,\n\nDit is uitsluitend een lokale e-mailtest.\nMet één accént.";
        $payload = ['from_address' => 'team@example.test', 'from_name' => 'Testteam', 'subject' => 'Bevestiging – test', 'body' => $body];
        $calendar = null;
        if (in_array($mode, ['request', 'cancel'], true)) {
            $calendar = CalendarInvitation::make($company, $appointment, $employee, $applicant, $mode === 'cancel');
            $payload['calendar'] = $calendar;
        }
        $rejected = false;
        try {
            Mailer::deliver('recipient@example.test', $payload);
        } catch (RuntimeException) {
            $rejected = true;
        }
        if ($mode !== 'reject') {
            $message = is_file($capture) ? file_get_contents($capture) : '';
            $parts = explode("\r\n\r\n", $message, 2);
            if ($rejected || count($parts) !== 2 || !str_contains($message, 'From: Testteam <team@example.test>') || !str_contains($message, 'To: <recipient@example.test>')) {
                throw new RuntimeException('De SMTP-testmail is niet correct afgeleverd.');
            }
            if ($calendar === null) {
                if (!str_contains($parts[0], 'Content-Type: text/plain; charset=UTF-8') || base64_decode(preg_replace('/\s+/', '', $parts[1]), true) !== $body) {
                    throw new RuntimeException('Bestaande tekstmail is gewijzigd of beschadigd.');
                }
                echo "[OK] SMTP EHLO, AUTH LOGIN en bestaande tekstmail lokaal afgeleverd\n";
            } else {
                if (!preg_match('/Content-Type: multipart\/alternative; boundary="([^"]+)"/', $parts[0], $match)) {
                    throw new RuntimeException('Agenda-uitnodiging mist een MIME-boundary.');
                }
                $mimeParts = explode('--' . $match[1], $parts[1]);
                if (count($mimeParts) !== 4) {
                    throw new RuntimeException('Agenda-uitnodiging bevat niet precies twee MIME-parts.');
                }
                $textPart = explode("\r\n\r\n", ltrim($mimeParts[1], "\r\n"), 2);
                $calendarPart = explode("\r\n\r\n", ltrim($mimeParts[2], "\r\n"), 2);
                if (count($textPart) !== 2 || count($calendarPart) !== 2
                    || base64_decode(preg_replace('/\s+/', '', $textPart[1]), true) !== $body
                    || base64_decode(preg_replace('/\s+/', '', $calendarPart[1]), true) !== $calendar['content']
                    || !str_contains($calendarPart[0], 'Content-Type: text/calendar; charset=UTF-8; method=' . $calendar['method'])
                    || !str_contains($calendarPart[0], 'filename="appointment.ics"')) {
                    throw new RuntimeException('Tekst en Outlook-agendapart zijn niet correct afgeleverd.');
                }
                echo '[OK] Outlook MIME ' . $calendar['method'] . " met ongewijzigde tekst en ICS lokaal afgeleverd\n";
            }
        } elseif (!$rejected) {
            throw new RuntimeException('SMTP-weigering moet als fout worden doorgegeven.');
        } else {
            echo "[OK] SMTP-weigering geeft een veilige fout terug\n";
        }
        $passed++;
    } finally {
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
        if (is_file($capture)) {
            unlink($capture);
        }
    }
}

$valid = CalendarInvitation::make($company, $appointment, $employee, $applicant);
foreach ([['method' => "REQUEST\r\nBcc: injected@example.test", 'content' => $valid['content']], ['method' => 'CANCEL', 'content' => $valid['content']]] as $calendar) {
    $rejected = false;
    try {
        Mailer::deliver('recipient@example.test', ['from_address' => 'team@example.test', 'from_name' => 'Testteam', 'subject' => 'Test', 'body' => 'Test', 'calendar' => $calendar]);
    } catch (RuntimeException $error) {
        $rejected = $error->getMessage() === 'Ongeldige agenda-uitnodiging.';
    }
    if (!$rejected) {
        throw new RuntimeException('Ongeldige agendamethode moet voor SMTP worden geweigerd.');
    }
    $passed++;
}
echo "[OK] Headerinjectie en afwijkende MIME/ICS-methoden worden voor SMTP geweigerd\n";
$GLOBALS['config']['environment'] = 'production';
$rejected = false;
try {
    Mailer::deliver('recipient@example.test', ['from_address' => 'team@example.test', 'from_name' => 'Testteam', 'subject' => 'Test', 'body' => 'Test']);
} catch (RuntimeException $error) {
    $rejected = $error->getMessage() === 'Configureer beveiligde SMTP voor aflevering.';
}
if (!$rejected) {
    throw new RuntimeException('Productie moet onbeveiligde SMTP blijven weigeren.');
}
$passed++;
echo "[OK] Productie blijft onbeveiligde SMTP weigeren\n";
echo $passed . "/7 SMTP-controles geslaagd (geen externe e-mail verstuurd).\n";
