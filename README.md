# Sollicitatieportaal

Het portaal ondersteunt meerdere bedrijven met een eigen huisstijl. Sollicitanten sturen
hun gegevens, cv en motivatie in. Medewerkers van de juiste afdeling beoordelen de
sollicitatie. Na goedkeuring krijgt de sollicitant een link om een wachtwoord te kiezen.

Deze versie bevat sollicitaties, accounts, e-mailverificatie, beoordeling, gespreksplanning
en de mailwachtrij met agenda-uitnodigingen. Beheerders beheren bedrijven, huisstijlen,
medewerkers, afdelingen en werktijden via de beheeromgeving.
Alle aanvullingen komen rechtstreeks in dezelfde projectmap.

## Lokaal starten en testen

Bij een nieuwe lokale installatie maakt `setup.php` de tabellen, het bedrijf **Scabro**
en dit direct bruikbare beheerdersaccount aan:

| Website-inloggegeven | Waarde |
| --- | --- |
| Naam en e-mailadres | `test@admin.nl` |
| Wachtwoord | `1234` |
| Inlogpagina | [Inloggen bij Scabro](http://127.0.0.1:8080/login.html?company=scabro) |

Hiervoor zijn geen activatiemail of mailworker nodig. Het testaccount wordt alleen
aangemaakt als `APP_ENV=local`. Gebruik het uitsluitend voor lokaal testen.

Heb je al tabellen in je database? Volg dan [Bestaande database gebruiken](#bestaande-database-gebruiken).
Voor een nieuwe database doorloop je de volgende vijf stappen.

### 1. Controleer PHP en start MariaDB

Benodigd: PHP 8.3 of nieuwer met `pdo_mysql`, `fileinfo`, `mbstring`, `sodium`, `dom`,
`openssl`, `session` en `phar`, en MariaDB 10.5 of nieuwer.
Er zijn geen Composer-pakketten of frontendbouwstappen.

Open PowerShell in de projectmap en controleer PHP:

```powershell
cd "C:\Users\JimJonkman\Desktop\examen_code"
php --version
```

Als `php` niet wordt gevonden, voeg je PHP-map aan PATH toe of gebruik je de volledige
locatie van `php.exe` bij ieder commando. Op deze computer staat die bijvoorbeeld in
`C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe`.
Een volledig pad voer je in PowerShell uit als `& "C:\pad\naar\php.exe" --version`.

Start MariaDB vanuit PowerShell. Open hiervoor een **aparte PowerShell als administrator**
(zoek PowerShell in Start, klik met rechts en kies **Als administrator uitvoeren**):

```powershell
Start-Service -Name MariaDB
Get-Service -Name MariaDB
```

Bij `Status` moet `Running` staan. Draait de service al, dan kun je meteen verder.
Je kunt dit administratorvenster daarna sluiten; MariaDB blijft draaien.
Voer de overige projectcommando's uit in je gewone PowerShell in de projectmap.

Krijg je een melding dat de service niet bestaat? Zoek de geïnstalleerde servicenaam met:

```powershell
Get-Service -Name '*Maria*','*MySQL*' -ErrorAction SilentlyContinue
```

Gebruik de waarde uit de kolom `Name` in plaats van `MariaDB` bij `Start-Service` en
`Get-Service`. Verschijnt er geen databaseservice, dan moet je MariaDB eerst installeren
of de database via je lokale databasepakket starten.

De database moet blijven draaien zolang je de website gebruikt. De standaardpoort
is `3306`; gebruik je bewust een andere poort, vul die dan zowel bij de client als in `.env` in.

### 2. Maak de database en een databasegebruiker aan

Open de MariaDB-client vanuit PowerShell. Dit voorbeeld gebruikt poort `3306`:

```powershell
mariadb --host=127.0.0.1 --port=3306 --user=root --password
```

Wordt `mariadb` niet herkend? Gebruik de volledige locatie van de client. Op deze computer:

```powershell
& "C:\Program Files\MariaDB 13.0\bin\mariadb.exe" --host=127.0.0.1 --port=3306 --user=root --password
```

Vul bij de wachtwoordvraag het **MariaDB-rootwachtwoord** in dat bij de installatie
is ingesteld. Tijdens het typen zie je geen tekens. Als je bij de installatie bewust
geen wachtwoord hebt ingesteld, druk je op Enter.

Je krijgt een SQL-prompt zoals `MariaDB [(none)]>`. Plak daar de volgende SQL-commando's
(dus in de MariaDB-client, niet rechtstreeks in PowerShell):

```sql
CREATE DATABASE application_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'portal'@'127.0.0.1' IDENTIFIED BY 'portal_lokaal_2026';
GRANT ALL PRIVILEGES ON application_portal.* TO 'portal'@'127.0.0.1';
EXIT;
```

Dit maakt een lege database `application_portal` en een databasegebruiker `portal`
met het lokale voorbeeldwachtwoord `portal_lokaal_2026`. `EXIT;` brengt je terug in PowerShell.
Je mag andere namen of een ander wachtwoord gebruiken, maar neem die dan exact over in `.env`.
Bestaat de database of gebruiker al? Gebruik de bestaande gegevens en verwijder niets.

**Deze databasegebruiker is iets anders dan het websiteaccount.** `portal` wordt door
PHP gebruikt om met MariaDB te verbinden. In de website log je in als `test@admin.nl`
met `1234`. Het websitewachtwoord hoort dus niet bij `DB_PASSWORD`.

### 3. Vul `.env` in

`.env` is het tekstbestand met de lokale instellingen, in dezelfde map als deze README.
Als het nog niet bestaat, kopieer je het voorbeeld. Dit commando bewaart een bestaande `.env`:

```powershell
if (!(Test-Path .env)) { Copy-Item .env.example .env }
notepad .env
```

Voor de SQL-commando's hierboven moeten deze waarden erin staan. Bestaat `.env` al?
Vervang dan alle vijf `DB_*`-regels door onderstaande waarden als je de SQL uit stap 2
hebt gebruikt; alleen `DB_PASSWORD` aanpassen is niet voldoende. Behoud je bestaande `APP_KEY`.

```dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8080
APP_TIMEZONE=Europe/Amsterdam
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=application_portal
DB_USERNAME=portal
DB_PASSWORD=portal_lokaal_2026
SESSION_SECURE=false
APP_KEY=
SMTP_HOST=127.0.0.1
SMTP_PORT=1025
SMTP_ENCRYPTION=none
SMTP_USERNAME=
SMTP_PASSWORD=
RETENTION_DAYS=28
```

| Instelling | Wat vul je in? |
| --- | --- |
| `APP_ENV` | `local` om het lokale testaccount aan te maken. |
| `APP_URL` | Het adres waarop je de website opent; hier `http://127.0.0.1:8080`. |
| `DB_HOST` / `DB_PORT` | Adres en poort van MariaDB; lokaal meestal `127.0.0.1` en `3306`. Staat er al `3307`, controleer dan op welke poort jouw server werkelijk draait. |
| `DB_DATABASE` | De databasenaam uit `CREATE DATABASE`: hier `application_portal`. |
| `DB_USERNAME` | De gebruiker uit `CREATE USER`: hier `portal`. |
| `DB_PASSWORD` | Het wachtwoord uit `IDENTIFIED BY`: hier `portal_lokaal_2026`. |
| `SESSION_SECURE` | `false` voor lokaal testen via HTTP, zodat de inlogsessie werkt. |
| `APP_KEY` | Een eigen gegenereerde sleutel voor versleutelde e-mails; zie hieronder. |
| `SMTP_*` | De lokale Mailpit-instellingen. Zie [E-mail lokaal testen](#e-mail-lokaal-testen) voor het starten. |
| `APP_TIMEZONE` / `RETENTION_DAYS` | Kunnen voor deze test op `Europe/Amsterdam` en `28` blijven staan. |

Genereer een sleutel als `APP_KEY` nog leeg is:

```powershell
php scripts/generate-key.php
```

Kopieer de volledige uitvoer, inclusief `base64:`, en plak die achter `APP_KEY=`.
Bewaar een reeds ingevulde sleutel: bestaande wachtrijmails zijn daarmee versleuteld.
Sla `.env` op voordat je verdergaat. Zet opmerkingen op een eigen regel met `#`;
zet geen uitleg achter de waarden. Bewaar wachtwoorden en je sleutel buiten Git.

### 4. Maak de tabellen en het testaccount aan

Voer in PowerShell vanuit de projectmap uit:

```powershell
php scripts/setup.php
```

Dit gebruikt de database uit `.env` en maakt daarin alle tabellen aan. Met `APP_ENV=local`
worden ook Scabro en de beheerder `test@admin.nl` / `1234` aangemaakt. Het wachtwoord
wordt als bcrypt-hash opgeslagen. De bestaande Scabro-huisstijl wordt gebruikt.

`setup.php` maakt de database en de MariaDB-gebruiker zelf niet aan: dat deed je bij stap 2.
Het commando weigert een database waarin al tabellen staan om bestaande gegevens te bewaren.

### 5. Start de website en log in

```powershell
php scripts/serve.php
```

Laat deze terminal open. Open [Inloggen bij Scabro](http://127.0.0.1:8080/login.html?company=scabro)
en vul `test@admin.nl` en `1234` in. Je komt daarna in de beheeromgeving.
De [startpagina](http://127.0.0.1:8080/) toont de beschikbare bedrijven en via
[Solliciteren bij Scabro](http://127.0.0.1:8080/apply.html?company=scabro) test je het formulier.

Gebruik steeds `127.0.0.1:8080`, overeenkomstig `APP_URL`. Stop de webserver met Ctrl+C.
Bij een volgende testsessie hoef je alleen MariaDB en `php scripts/serve.php` te starten.
Voor registratie, uitnodigingen en andere e-mailacties volg je ook **E-mail lokaal testen**.

### Bestaande database gebruiken

Bewaar je database, `.env` en bestaande `APP_KEY`. Controleer of de `DB_*`-instellingen
naar jouw bestaande database wijzen. Het databaseschema is ongewijzigd; er is geen migratie nodig.
Als de tabellen al bestaan, sla je `setup.php` over. Zet `APP_ENV=local`,
`APP_URL=http://127.0.0.1:8080` en `SESSION_SECURE=false` in `.env` en voer uit:

```powershell
php scripts/seed-test-admin.php
php scripts/serve.php
```

Het eerste commando voegt Scabro en het admin-testaccount toe als ze ontbreken.
Het wijzigt geen bestaande bedrijfsgegevens, accounts of wachtwoorden. Als dit adminaccount
al bestaat, behoudt het dus zijn huidige wachtwoord. Een inactief Scabro-bedrijf of een
bestaand `test@admin.nl`-account met een andere rol bij Scabro wordt gemeld zonder wijziging.
Het commando werkt uitsluitend met `APP_ENV=local`.

### Veelvoorkomende problemen

| Melding of probleem | Controle |
| --- | --- |
| `Connection refused` / verbinding geweigerd | Draait MariaDB? Komen `DB_HOST` en `DB_PORT` overeen met de server? `serve.php` start MariaDB niet. |
| `Unknown database` | Maak eerst de database aan met stap 2; controleer `DB_DATABASE`. |
| `Access denied` | Controleer `DB_USERNAME`, `DB_PASSWORD` en de rechten uit `GRANT`. Dit zijn je MariaDB-gegevens. |
| `could not find driver` | Schakel `pdo_mysql` in de `php.ini` van je PHP-terminalversie in. `php --ini` toont welk bestand wordt gebruikt. |
| `De database is niet leeg` | De tabellen bestaan al; gebruik `seed-test-admin.php` voor het lokale testaccount. |
| Inloggen lukt, maar de sessie verdwijnt | Gebruik `SESSION_SECURE=false` bij HTTP en open steeds hetzelfde adres als `APP_URL`. |
| Geen activatie- of bevestigingsmail | Start Mailpit en voer na de actie `php scripts/mail-worker.php` uit; zie de e-mailstappen hieronder. |
| Poort `8080` is bezet | Start met `php scripts/serve.php --port=8081`, zet `APP_URL=http://127.0.0.1:8081` en open de links op die poort. |

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

Voor een productie-installatie gebruik je een lege database met `APP_ENV=production`,
je eigen HTTPS-adres als `APP_URL`, `SESSION_SECURE=true`, een eigen `APP_KEY` en beveiligde SMTP.
Voer dan `php scripts/setup.php` uit; in productie worden alleen tabellen aangemaakt.
Maak vervolgens één eigen beheerder via de terminal. Vervang de bedrijfsgegevens,
het e-mailadres en de naam door je eigen gegevens:

```powershell
php scripts/create-admin.php --company-name="Mijn bedrijf" --slug=mijn-bedrijf --email=beheerder@example.nl --name=Beheerder
```

Gebruik een eigen e-mailadres en stel eerst `APP_KEY`, `APP_URL` en SMTP in.
Dit commando werkt alleen als nog geen beheerder bestaat. Het kan een bestaand actief
bedrijf met dezelfde slug gebruiken of een nieuw bedrijf aanmaken. Voer daarna de
mailworker uit en stel via de ontvangen link een wachtwoord in.
Gebruik voor productie een aparte database zonder het lokale testaccount en testgegevens.
Het instellen van `APP_ENV=production` verwijdert een eerder aangemaakt testaccount niet.

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

Op deze computer kun je de bestaande Mailpit-installatie starten in een aparte terminal
als die nog niet draait:

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
- `scripts/`: installatie met lokaal testaccount, aanvullende lokale seeding, eerste productiebeheerder,
  sleutelgeneratie, mailworker, cleanup en ontwikkelserver.
- `tests/`: zelfstandige controles van validatie, versleuteling, agenda-inhoud, SMTP en thema's.
- `docs/`: ontwerp, testplan en beveiligings- en onderhoudsinformatie.

Instellingen in `.env` en gegenereerde bestanden onder `storage/` zijn lokale gegevens.
Git-inrichting wordt apart gedaan.
