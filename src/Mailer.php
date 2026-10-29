<?php
declare(strict_types=1);

final class Mailer
{
    private const TEMPLATES = [
        'verify_email' => ['Bevestig je e-mailadres', "Beste {name},\n\nBedankt voor je sollicitatie bij {company_name}. Bevestig je e-mailadres via deze eenmalige link (48 uur geldig):\n{verification_url}\n\nEen medewerker beoordeelt daarna je sollicitatie."],
        'application_received' => ['Sollicitatie ontvangen', "Beste {name},\n\nJe sollicitatie bij {company_name} is ontvangen. Je ontvangt een bericht zodra deze is beoordeeld."],
        'application_rejected' => ['Reactie op je sollicitatie', "Beste {name},\n\nBedankt voor je interesse in {company_name}. We hebben besloten niet verder te gaan met je sollicitatie. We wensen je veel succes."],
        'set_password' => ['Stel je wachtwoord in', "Beste {name},\n\nJe krijgt toegang tot het portaal van {company_name}. Stel binnen 48 uur een wachtwoord in via deze eenmalige link:\n{setup_url}\n\nNa het inloggen kun je verder in het portaal."],
        'appointment_confirmation' => ['Bevestiging van je gesprek', "Beste {name},\n\nJe gesprek bij {company_name} met {employee_name} staat gepland op {starts_at}. Je ziet de afspraak ook in het portaal.\n\nOpen de agenda-uitnodiging in Outlook of een andere agenda. Wil je annuleren? Doe dit in het portaal; antwoorden via je agenda worden niet verwerkt in het portaal."],
        'appointment_cancelled' => ['Je gesprek is geannuleerd', "Beste {name},\n\nJe gesprek bij {company_name} op {starts_at} is geannuleerd. Je kunt in het portaal een nieuw moment kiezen. De meegestuurde agenda-annulering hoort bij deze afspraak."],
        'employee_appointment_confirmation' => ['Sollicitatiegesprek met {applicant_name}', "Beste {name},\n\nEr is bij {company_name} een sollicitatiegesprek met {applicant_name} ingepland op {starts_at}.\n\nOpen de agenda-uitnodiging in Outlook of een andere agenda. Je ziet de afspraak ook in het medewerkersportaal. Annuleer via het portaal; antwoorden via je agenda worden niet verwerkt in het portaal."],
        'employee_appointment_cancelled' => ['Sollicitatiegesprek met {applicant_name} geannuleerd', "Beste {name},\n\nHet sollicitatiegesprek met {applicant_name} bij {company_name} op {starts_at} is geannuleerd. De meegestuurde agenda-annulering hoort bij deze afspraak. Je ziet de wijziging ook in het medewerkersportaal."],
    ];

    public static function queue(PDO $database, array $company, string $to, string $template, array $variables, ?array $calendar = null): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !isset(self::TEMPLATES[$template])) {
            throw new RuntimeException('Ongeldige e-mailconfiguratie.');
        }
        $variables['company_name'] = $company['name'];
        $configured = json_decode($company['mail_templates'] ?? '{}', true) ?: [];
        [$subject, $body] = self::TEMPLATES[$template];
        if (isset($configured[$template]) && is_array($configured[$template])) {
            $subject = (string) ($configured[$template]['subject'] ?? $subject);
            $body = (string) ($configured[$template]['body'] ?? $body);
        }
        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements['{' . $name . '}'] = (string) $value;
        }
        $message = [
            'from_address' => $company['mail_from_address'], 'from_name' => $company['mail_from_name'],
            'subject' => strtr($subject, $replacements), 'body' => strtr($body, $replacements),
        ];
        if ($calendar !== null) {
            self::validateCalendar($calendar);
            $message['calendar'] = $calendar;
        }
        // De outbox moet een toegangslink kunnen afleveren; versleuteling beschermt de ruwe token.
        $payload = self::encrypt($message);
        $query = $database->prepare('INSERT INTO mail_queue (company_id, recipient, template, payload, available_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())');
        $query->execute([$company['id'], $to, $template, $payload]);
    }

    public static function encrypt(array $payload): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $nonce, self::key()));
    }

    public static function decrypt(string $payload): array
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new RuntimeException('Ongeldige outboxinhoud.');
        }
        $plain = sodium_crypto_secretbox_open(substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::key());
        if ($plain === false) {
            throw new RuntimeException('De outbox kan niet worden ontsleuteld.');
        }
        return json_decode($plain, true, 32, JSON_THROW_ON_ERROR);
    }

    private static function key(): string
    {
        $encoded = $GLOBALS['config']['key'];
        $key = base64_decode(str_starts_with($encoded, 'base64:') ? substr($encoded, 7) : $encoded, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Stel een geldige APP_KEY in.');
        }
        return $key;
    }

    public static function deliver(string $recipient, array $message): void
    {
        foreach ([$recipient, $message['from_address']] as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL) || strpbrk($address, "\r\n") !== false) {
                throw new RuntimeException('Ongeldig e-mailadres.');
            }
        }
        $calendar = $message['calendar'] ?? null;
        if ($calendar !== null) {
            self::validateCalendar($calendar);
        }
        $host = getenv('SMTP_HOST') ?: '';
        $encryption = getenv('SMTP_ENCRYPTION') ?: 'tls';
        if ($host === '' || !in_array($encryption, ['tls', 'ssl', 'none'], true) || ($encryption === 'none' && $GLOBALS['config']['environment'] !== 'local')) {
            throw new RuntimeException('Configureer beveiligde SMTP voor aflevering.');
        }
        $port = (int) (getenv('SMTP_PORT') ?: 587);
        $connection = stream_socket_client(($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $error, 10, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]));
        if (!$connection) {
            throw new RuntimeException('SMTP-verbinding niet beschikbaar.');
        }
        stream_set_timeout($connection, 15);
        try {
            self::response($connection, [220]);
            self::command($connection, 'EHLO portal.local', [250]);
            if ($encryption === 'tls') {
                self::command($connection, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP-TLS is mislukt.');
                }
                self::command($connection, 'EHLO portal.local', [250]);
            }
            $username = getenv('SMTP_USERNAME') ?: '';
            if ($username !== '') {
                self::command($connection, 'AUTH LOGIN', [334]);
                self::command($connection, base64_encode($username), [334]);
                self::command($connection, base64_encode(getenv('SMTP_PASSWORD') ?: ''), [235]);
            }
            self::command($connection, 'MAIL FROM:<' . $message['from_address'] . '>', [250]);
            self::command($connection, 'RCPT TO:<' . $recipient . '>', [250, 251]);
            self::command($connection, 'DATA', [354]);
            $encode = static fn (string $value): string => mb_encode_mimeheader(str_replace(["\r", "\n"], '', $value), 'UTF-8', 'B', "\r\n");
            $headers = [
                'From: ' . $encode($message['from_name']) . ' <' . $message['from_address'] . '>',
                'To: <' . $recipient . '>', 'Subject: ' . $encode($message['subject']),
                'Date: ' . gmdate(DATE_RFC2822), 'Message-ID: <' . bin2hex(random_bytes(16)) . '@portal.local>',
                'MIME-Version: 1.0',
            ];
            if ($calendar === null) {
                // Bestaande wachtwoord- en verificatiemails blijven gewone tekstmail.
                $headers[] = 'Content-Type: text/plain; charset=UTF-8';
                $headers[] = 'Content-Transfer-Encoding: base64';
                $body = chunk_split(base64_encode($message['body']), 76, "\r\n");
            } else {
                // Outlook herkent een text/calendar-part met dezelfde METHOD als
                // de VCALENDAR. multipart/alternative houdt ook de tekst leesbaar.
                $boundary = 'calendar_' . bin2hex(random_bytes(16));
                $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
                $body = '--' . $boundary . "\r\n"
                    . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                    . chunk_split(base64_encode($message['body']), 76, "\r\n")
                    . '--' . $boundary . "\r\n"
                    . 'Content-Type: text/calendar; charset=UTF-8; method=' . $calendar['method'] . "; name=\"appointment.ics\"\r\n"
                    . "Content-Transfer-Encoding: base64\r\nContent-Disposition: inline; filename=\"appointment.ics\"\r\n\r\n"
                    . chunk_split(base64_encode($calendar['content']), 76, "\r\n")
                    . '--' . $boundary . "--\r\n";
            }
            $wire = implode("\r\n", $headers) . "\r\n\r\n" . $body;
            self::command($connection, $wire . "\r\n.", [250]);
            self::command($connection, 'QUIT', [221]);
        } finally {
            fclose($connection);
        }
    }

    private static function validateCalendar(array $calendar): void
    {
        $method = $calendar['method'] ?? null;
        $content = $calendar['content'] ?? null;
        if (!in_array($method, ['REQUEST', 'CANCEL'], true) || !is_string($content)
            || !str_starts_with($content, "BEGIN:VCALENDAR\r\n")
            || !str_ends_with($content, "END:VCALENDAR\r\n")
            || !str_contains($content, "\r\nMETHOD:" . $method . "\r\n")) {
            throw new RuntimeException('Ongeldige agenda-uitnodiging.');
        }
    }

    private static function command($connection, string $command, array $codes): void
    {
        if (fwrite($connection, $command . "\r\n") === false) {
            throw new RuntimeException('SMTP-schrijven is mislukt.');
        }
        self::response($connection, $codes);
    }

    private static function response($connection, array $codes): void
    {
        do {
            $line = fgets($connection, 4096);
            if ($line === false) {
                throw new RuntimeException('SMTP gaf geen antwoord.');
            }
        } while (isset($line[3]) && $line[3] === '-');
        if (!in_array((int) substr($line, 0, 3), $codes, true)) {
            // Het SMTP-antwoord kan persoonsgegevens bevatten en wordt daarom niet gelogd.
            throw new RuntimeException('SMTP heeft het bericht geweigerd.');
        }
    }
}
