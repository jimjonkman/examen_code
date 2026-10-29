# Sollicitatieportaal

Het portaal ondersteunt meerdere bedrijven met een eigen huisstijl. Sollicitanten sturen
hun gegevens, cv en motivatie in. Medewerkers van de juiste afdeling beoordelen de
sollicitatie. Na goedkeuring krijgt de sollicitant een link om een wachtwoord te kiezen.

Deze versie bevat sollicitaties, accounts, e-mailverificatie, beoordeling, gespreksplanning
en de mailwachtrij met agenda-uitnodigingen. De beheeromgeving volgt later.
Alle aanvullingen komen rechtstreeks in dezelfde projectmap.

## Starten

Open PowerShell in de projectmap:

```powershell
cd "C:\Users\JimJonkman\Desktop\examen_code"
```

Benodigd: PHP 8.3 of nieuwer met `pdo_mysql`, `fileinfo`, `mbstring`, `sodium`, `dom`,
`openssl`, `session` en `phar`, en een draaiende MariaDB 10.5 of nieuwer.
Er zijn geen Composer-pakketten of frontendbouwstappen.

Bij een **nieuwe installatie**:

1. Maak een lege MariaDB-database en geef je databasegebruiker toegang.
2. Kopieer `.env.example` naar `.env` en vul de databasegegevens in.
3. Voer `php scripts/generate-key.php` uit en zet de volledige uitvoer, inclusief `base64:`, achter `APP_KEY=` in `.env`.
4. Voer eenmalig `php scripts/setup.php --seed` uit voor de tabellen en voorbeeldbedrijven.
5. Voer `php scripts/seed-demo-accounts.php` uit voor de lokale medewerkersaccounts.
6. Voer `php scripts/seed-demo-hours.php` uit voor hun beschikbare werktijden.

Bij een **bestaande database**:

Bewaar je `.env` en database. Het schema is gelijk aan de vorige versie; er is geen
migratie nodig. Voer `setup.php` niet opnieuw uit op een gevulde database. Voeg de nieuwe
instellingen uit `.env.example` aan je `.env` toe. Vul eenmalig `APP_KEY` in als die nog leeg
is. Bewaar een bestaande sleutel: eerder klaargezette mails zijn daarmee versleuteld.
Voer `php scripts/seed-demo-accounts.php` uit om ontbrekende demoaccounts toe te voegen.
Voer daarna `php scripts/seed-demo-hours.php` uit om ontbrekende demo-werktijden toe te voegen.
Dit bewaart bestaande accounts, documenten, afspraken en aangepaste werktijden.

Start je database apart en voer daarna uit:

```powershell
php scripts/serve.php
```

Open [het portaal](http://127.0.0.1:8080/) en kies een bedrijf, of open direct:

- [Scabro: solliciteren](http://127.0.0.1:8080/apply.html?company=scabro).
- [Scabro: inloggen](http://127.0.0.1:8080/login.html?company=scabro).
- [Voorbeeldbedrijf: solliciteren](http://127.0.0.1:8080/apply.html?company=demo).
- [Voorbeeldbedrijf: inloggen](http://127.0.0.1:8080/login.html?company=demo).

Stop met Ctrl+C. Als `php` niet wordt gevonden, gebruik dan de volledige locatie van je
PHP-programma. `APP_URL` moet overeenkomen met het adres waarop je de website opent.

## Lokale medewerkersaccounts

Het demoscript werkt alleen met `APP_ENV=local` en bewaart bestaande accounts.
Alle nieuw aangemaakte demoaccounts gebruiken `DemoPortal!2026`.

| Account bij Scabro | Afdeling |
| --- | --- |
| `employee1@scabro.test` | Assembly technician |
| `employee2@scabro.test` | Bodyshop coating technician |
| `employee3@scabro.test` | Production employee drones |
| `employee4@scabro.test` | open/intern |

Voor het voorbeeldbedrijf eindigen dezelfde adressen op `@demo.test`.
Een medewerker ziet alleen sollicitaties van de eigen afdeling binnen het eigen bedrijf.
Via **Mijn afdeling** kan de medewerker de eigen afdeling wijzigen.
Stages en open sollicitaties vallen samen onder **open/intern**.

## Gesprekken inplannen

Een goedgekeurde sollicitant kan na inloggen bij de medewerker plannen die de sollicitatie
heeft goedgekeurd. De agenda toont slots van 30 minuten tot maximaal 90 dagen vooruit.
De tijden worden weergegeven in **Europe/Amsterdam** en opgeslagen in UTC.

Het commando `php scripts/seed-demo-hours.php` geeft demo-medewerkers zonder bestaande
werktijden maandag tot en met vrijdag 09:00–17:00. Het script werkt alleen lokaal en
slaat medewerkers met een bestaand rooster over. Voer eerst het medewerkersscript uit.
Zonder werktijden zijn er geen beschikbare slots.

De sollicitant kan één actieve afspraak hebben, deze annuleren en daarna opnieuw boeken.
Een medewerker ziet de eigen gesprekken en kan die annuleren. Slots die inmiddels zijn
geboekt worden geweigerd; de gebruiker kan daarna een ander moment kiezen.

Boeken en annuleren zetten ieder een e-mail voor de sollicitant en de medewerker klaar.
De mail bevat een agenda-uitnodiging of agenda-annulering die in Outlook kan worden geopend.
Voer de mailworker uit om deze berichten af te leveren. Antwoorden en wijzigingen in Outlook
worden niet teruggesynchroniseerd; annuleer via het portaal. De beschikbaarheid is gebaseerd
op de werktijden en afspraken in het portaal, niet op externe Outlook-afspraken.

## E-mail lokaal testen

Registratie, goedkeuring, boeken en annuleren zetten e-mails versleuteld klaar in de database. Voor aflevering
moet een SMTP-server bereikbaar zijn met de instellingen uit `.env`. De voorbeeldinstellingen
gebruiken een lokale SMTP-inbox op poort 1025:

```dotenv
SMTP_HOST=127.0.0.1
SMTP_PORT=1025
SMTP_ENCRYPTION=none
SMTP_USERNAME=
SMTP_PASSWORD=
```

Op deze computer kun je de bestaande Mailpit-installatie starten in een aparte terminal:

```powershell
& "C:\laragon\bin\mailpit\1.22.3\mailpit.exe" --smtp 127.0.0.1:1025 --listen 127.0.0.1:8025
```

Laat die terminal open. Open [de lokale inbox](http://127.0.0.1:8025) en voer na een
registratie of beoordeling vanuit de projectmap uit:

```powershell
php scripts/mail-worker.php
```

De worker verwerkt per uitvoering maximaal 50 beschikbare berichten en stopt daarna.
Voer het commando opnieuw uit na een nieuwe mailactie. Bij een mislukte aflevering wordt
een bericht vijf minuten uitgesteld. Na vijf mislukte pogingen stopt automatisch opnieuw proberen.

Een volledige test bestaat uit:

1. Solliciteer met een uniek e-mailadres, een functie en twee PDF- of DOCX-bestanden van maximaal 5 MB.
2. Voer de mailworker uit en bevestig je e-mailadres via de ontvangen link.
3. Log in als medewerker van de juiste afdeling en bekijk de sollicitatie en documenten.
4. Keur goed of wijs af. Een beoordeling kan één keer worden uitgevoerd.
5. Voer de mailworker opnieuw uit. Bij goedkeuring krijgt de sollicitant een wachtwoordlink.
6. Kies via die link een wachtwoord van minimaal 12 tekens en log in als sollicitant.
7. Kies in de agenda een beschikbaar tijdslot en bevestig de afspraak.
8. Voer de mailworker uit en controleer beide agenda-uitnodigingen in de lokale inbox.
9. Annuleer als sollicitant of toegewezen medewerker en controleer na de mailworker beide annuleringen.

Gebruik aparte browserprofielen of een privévenster voor medewerker en sollicitant.
Bevestigings- en wachtwoordlinks zijn eenmalig en 48 uur geldig.
Een ingelogde sollicitant kan de goedkeuringsstatus en de eigen afspraak bekijken.

## Agendatests

De zelfstandige controles voor agenda-uitnodigingen gebruiken geen database of SMTP:

```powershell
php tests/calendar.php
```

## Bestanden

- `public/`: pagina's, opmaak, JavaScript en huisstijlen.
- `api/`: JSON-routes voor bedrijven, sessies, accounts, sollicitaties, documenten en afspraken.
- `src/` en `config/`: databaseverbinding, authenticatie, uploads, mail en overige backendcode.
- `database/`: het schema en de voorbeeldbedrijven; de seed bevat geen gebruikersaccounts.
- `scripts/`: installatie, demoaccounts en werktijden, sleutelgeneratie, mailworker en ontwikkelserver.
- `tests/calendar.php`: controles van uitnodigingen, annuleringen en de versleutelde agenda-inhoud.
- `docs/ontwerp.md`: ontwerp en vervolgstappen.

Instellingen in `.env` en gegenereerde bestanden onder `storage/` zijn lokale gegevens.
Git-inrichting wordt apart gedaan.
