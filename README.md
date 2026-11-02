# Sollicitatieportaal

Het portaal ondersteunt meerdere bedrijven met een eigen huisstijl. Sollicitanten sturen
hun gegevens, cv en motivatie in. Medewerkers van de juiste afdeling beoordelen de
sollicitatie. Na goedkeuring krijgt de sollicitant een link om een wachtwoord te kiezen.

Deze versie bevat sollicitaties, accounts, e-mailverificatie, beoordeling, gespreksplanning
en de mailwachtrij met agenda-uitnodigingen. Beheerders beheren bedrijven, huisstijlen,
medewerkers, afdelingen en werktijden via de beheeromgeving.
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
4. Voer eenmalig `php scripts/setup.php` uit om de tabellen aan te maken.
5. Stel `APP_URL` en SMTP in en maak één eigen beheerder aan met `scripts/create-admin.php`, zoals beschreven bij **Beheer**.
6. Voer `php scripts/mail-worker.php` uit en stel via de ontvangen activatielink het beheerderswachtwoord in.

Bij een **bestaande database**:

Bewaar je `.env` en database. Het schema is gelijk aan de vorige versie; er is geen
migratie nodig. Voer `setup.php` niet opnieuw uit op een gevulde database. Voeg de nieuwe
instellingen uit `.env.example` aan je `.env` toe. Vul eenmalig `APP_KEY` in als die nog leeg
is. Bewaar een bestaande sleutel: eerder klaargezette mails zijn daarmee versleuteld.
Gebruik de bestaande beheerder. Als er nog geen beheerder is, maak die eenmalig aan
via de procedure bij **Beheer**. Het verwijderen van voorbeeldbestanden uit de broncode
wijzigt geen bestaande databaseaccounts of dossiers; controleer een bestaande database
afzonderlijk voordat je die online gebruikt.

Start je database apart en voer daarna uit:

```powershell
php scripts/serve.php
```

Open [het portaal](http://127.0.0.1:8080/) en kies het ingestelde bedrijf.
De rechtstreekse links gebruiken de eigen bedrijfsslug, bijvoorbeeld
`/apply.html?company=mijn-bedrijf` en `/login.html?company=mijn-bedrijf`.
De aanwezige Scabro-huisstijl blijft beschikbaar voor een bedrijf met slug `scabro`;
de huisstijlbestanden maken zelf geen bedrijf of accounts aan.

Stop met Ctrl+C. Als `php` niet wordt gevonden, gebruik dan de volledige locatie van je
PHP-programma. `APP_URL` moet overeenkomen met het adres waarop je de website opent.

## Medewerkersaccounts

De beheerder nodigt medewerkers met hun eigen e-mailadres uit via de beheeromgeving.
Een medewerker ziet alleen sollicitaties van de eigen afdeling binnen het eigen bedrijf.
Via **Mijn afdeling** kan de medewerker de eigen afdeling wijzigen.
Stages en open sollicitaties vallen samen onder **open/intern**.

## Beheer

De beheerrol heeft toegang tot alle bedrijven in dit portaal. Medewerkers en sollicitanten
blijven beperkt tot hun eigen bedrijf en de toepasselijke afdeling of afspraken.

In de beheeromgeving kun je:

- Bedrijven toevoegen en naam, slug, afzender en actieve status wijzigen.
- Kleuren, logo en lettertype wijzigen met een voorbeeld van de huisstijl.
- E-mailsjablonen per bedrijf aanpassen; de beschikbare variabelen staan bij het formulier.
- Medewerkers uitnodigen met een afdeling, hun afdeling en weekrooster wijzigen en accounts deactiveren.

Een uitgenodigde medewerker ontvangt via de mailworker een eenmalige wachtwoordlink.
Een gedeactiveerd account krijgt geen toegang meer. Het uitschakelen van een bedrijf
maakt de bedrijfslink onbeschikbaar en bewaart de gegevens. Na een slugwijziging gebruik
je de nieuwe bedrijfslink. Logo's worden gedeeld door de openbare en ingelogde pagina's.

Maak bij de installatie één eigen beheerder via de terminal. Vervang de bedrijfsgegevens,
het e-mailadres en de naam door je eigen gegevens:

```powershell
php scripts/create-admin.php --company-name="Mijn bedrijf" --slug=mijn-bedrijf --email=beheerder@example.nl --name=Beheerder
```

Gebruik een eigen e-mailadres en stel eerst `APP_KEY`, `APP_URL` en SMTP in.
Dit commando werkt alleen als nog geen beheerder bestaat. Het kan een bestaand actief
bedrijf met dezelfde slug gebruiken of een nieuw bedrijf aanmaken. Voer daarna de
mailworker uit en stel via de ontvangen link een wachtwoord in.

## Gesprekken inplannen

Een goedgekeurde sollicitant kan na inloggen bij de medewerker plannen die de sollicitatie
heeft goedgekeurd. De agenda toont slots van 30 minuten tot maximaal 90 dagen vooruit.
De tijden worden weergegeven in **Europe/Amsterdam** en opgeslagen in UTC.

Nieuwe medewerkers krijgen via de beheeromgeving standaard werktijden van maandag
tot en met vrijdag, 09:00–17:00. De beheerder kan dit rooster per medewerker aanpassen.
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

Nodig voor een volledige test via de beheeromgeving eerst een medewerker voor de
gekozen afdeling uit. Verwerk de uitnodigingsmail, stel het medewerkerswachtwoord in
en controleer de werktijden. Doorloop daarna:

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
De sollicitant kan het eigen account met wachtwoordbevestiging verwijderen. Bij een
actieve afspraak ontvangt de medewerker een agenda-annulering zonder de verwijderde
sollicitantgegevens.

## Onderhoud

De mailworker verstuurt alleen berichten wanneer het commando wordt uitgevoerd.
Voor periodiek gebruik moet je het commando zelf als geplande taak instellen.

Het opschonen van oude dossiers gebeurt met:

```powershell
php scripts/cleanup.php
```

Dit verwijdert afgewezen dossiers waarvan de beoordeling ouder is dan `RETENTION_DAYS`
en nog onbevestigde aanvragen die ouder zijn dan die termijn. De standaard is 28 dagen.
Bijbehorende accounts, documenten en relevante wachtrijgegevens worden verwijderd.
Goedgekeurde dossiers en recentere aanvragen blijven staan. Verlopen technische gegevens
worden ook opgeruimd. Alleen het instellen van de termijn voert geen opschoning uit.

## Tests en documentatie

De zelfstandige controles gebruiken geen bestaande database of externe mailontvangers.
Hun fictieve gegevens en testfixtures dienen alleen voor regressietests en maken geen
accounts in het portaal aan:

```powershell
php tests/run.php
php tests/calendar.php
php tests/smtp.php
node tests/theme.mjs
```

Zie [het testplan](docs/testplan.md) voor de gecontroleerde flows en browseracceptatie,
[het ontwerp](docs/ontwerp.md) voor de architectuur en [beveiliging en privacy](docs/beveiliging-en-privacy.md)
voor de technische maatregelen en onderhoudsgrenzen.

## Bestanden

- `public/`: pagina's, opmaak, JavaScript en huisstijlen.
- `api/`: JSON-routes voor bedrijven, sessies, accounts, sollicitaties, documenten, afspraken en beheer.
- `src/` en `config/`: databaseverbinding, authenticatie, uploads, mail en overige backendcode.
- `database/`: het databaseschema, zonder vooraf ingestelde bedrijven of accounts.
- `scripts/`: installatie, eerste beheerder, sleutelgeneratie, mailworker, cleanup en ontwikkelserver.
- `tests/`: zelfstandige controles van validatie, versleuteling, agenda-inhoud, SMTP en thema's.
- `docs/`: ontwerp, testplan en beveiligings- en onderhoudsinformatie.

Instellingen in `.env` en gegenereerde bestanden onder `storage/` zijn lokale gegevens.
Git-inrichting wordt apart gedaan.
