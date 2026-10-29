<?php
declare(strict_types=1);

final class AppointmentNotifications
{
    public static function queue(PDO $db, array $company, array $appointment, array $employee, array $applicant, bool $cancelled = false): void
    {
        $calendar = CalendarInvitation::make($company, $appointment, $employee, $applicant, $cancelled);
        $variables = self::variables($appointment, $employee, $applicant);
        $template = $cancelled ? 'appointment_cancelled' : 'appointment_confirmation';
        // Samen met de afspraak in dezelfde transactie: beide ontvangers of geen.
        Mailer::queue($db, $company, $applicant['email'], $template, ['name' => $applicant['name']] + $variables, $calendar);
        Mailer::queue($db, $company, $employee['email'], 'employee_' . $template, ['name' => $employee['name']] + $variables, $calendar);
    }

    public static function deleteApplicant(PDO $db, array $company, array $appointments): void
    {
        $uids = [];
        $active = [];
        $owned = [];
        foreach ($appointments as $appointment) {
            $uid = CalendarInvitation::uid($company, $appointment);
            $uids[$uid] = true;
            if ($appointment['status'] === 'scheduled') {
                // Zelfde vergrendelvolgorde als boeken/annuleren: medewerker, afspraak.
                $query = $db->prepare('SELECT id FROM users WHERE id = ? AND company_id = ? FOR UPDATE');
                $query->execute([$appointment['employee_id'], $company['id']]);
                $query = $db->prepare('SELECT status FROM appointments WHERE id = ? AND company_id = ? FOR UPDATE');
                $query->execute([$appointment['id'], $company['id']]);
                $appointment['status'] = $query->fetchColumn();
                if ($appointment['status'] === 'scheduled') {
                    $active[$uid] = $appointment;
                }
            }
            $owned[$uid] = $appointment;
        }
        if ($uids === []) {
            return;
        }
        // Verwijder nog niet verzonden medewerkersmails over deze sollicitant.
        // De versleutelde outbox bevat geen doorzoekbare persoonsgegevens.
        $query = $db->prepare("SELECT id, template, payload FROM mail_queue WHERE company_id = ? AND sent_at IS NULL AND template IN ('employee_appointment_confirmation', 'employee_appointment_cancelled') FOR UPDATE");
        $query->execute([$company['id']]);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $queued) {
            try {
                $message = Mailer::decrypt($queued['payload']);
            } catch (RuntimeException|JsonException) {
                continue;
            }
            $content = $message['calendar']['content'] ?? '';
            if (!is_string($content)) {
                continue;
            }
            $unfolded = str_replace(["\r\n ", "\r\n\t"], '', $content);
            if (preg_match('/\r\nUID:([^\r\n]+)\r\n/', $unfolded, $match) && isset($uids[$match[1]])) {
                $delete = $db->prepare('DELETE FROM mail_queue WHERE company_id = ? AND id = ? AND sent_at IS NULL');
                $delete->execute([$company['id'], $queued['id']]);
                // Een eerder verzonden REQUEST moet ook na accountverwijdering
                // nog geannuleerd worden. Vervang de wachtende CANCEL anoniem.
                if ($queued['template'] === 'employee_appointment_cancelled') {
                    $active[$match[1]] = $owned[$match[1]];
                }
            }
        }
        foreach ($active as $appointment) {
            $employee = ['name' => $appointment['employee_name'], 'email' => $appointment['employee_email']];
            $anonymous = ['name' => 'een verwijderde sollicitant'];
            // Alleen de medewerker krijgt deze annulering. De oorspronkelijke
            // sollicitant en diens e-mailadres komen niet in de nieuwe payload.
            $calendar = CalendarInvitation::make($company, $appointment, $employee, $anonymous, true, false);
            Mailer::queue($db, $company, $employee['email'], 'employee_appointment_cancelled',
                ['name' => $employee['name']] + self::variables($appointment, $employee, $anonymous), $calendar);
        }
    }

    private static function variables(array $appointment, array $employee, array $applicant): array
    {
        $start = (new DateTimeImmutable($appointment['starts_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Amsterdam'));
        return [
            'applicant_name' => $applicant['name'], 'employee_name' => $employee['name'],
            'starts_at' => $start->format('d-m-Y H:i') . ' (Europe/Amsterdam)',
        ];
    }
}
