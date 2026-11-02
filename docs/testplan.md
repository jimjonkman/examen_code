# Testplan en verificatie

Gebruik synthetische gegevens, een aparte lokale database en een SMTP-testserver.
De controles mogen geen echte sollicitanten, bestaande dossiers of externe mailontvangers gebruiken.

## Zelfstandige controles

| Commando | Bereik |
| --- | --- |
| `php tests/run.php` | Validatie, veilige SVG's, versleuteling, CSRF en tokenlinks. |
| `php tests/calendar.php` | Agenda-uitnodiging, annulering, UID, tijdzone en inhoud. |
| `php tests/smtp.php` | Aflevering en foutafhandeling via een lokale SMTP-mock. |
| `node tests/theme.mjs` | Kleuren, contrast en lettertypegedrag van de frontend. |

De SMTP-suite opent een tijdelijke loopback-testserver. Deze suites gebruiken geen
bestaande applicatiedatabase en sturen geen berichten naar externe ontvangers.
De resultaten van de afronding staan in [verificatie.md](verificatie.md).

## Functionele regressie

| Onderdeel | Verwachte uitkomst |
| --- | --- |
| Bedrijven en huisstijl | Alleen actieve bedrijven; geldige bedrijfscontext; kleuren, logo en font per bedrijf. |
| Solliciteren | Verplichte functie, naam en uniek e-mailadres; twee geldige documenten; pending zonder wachtwoord. |
| Verificatie en activatie | Links zijn eenmalig, doel- en bedrijfsgebonden; verlopen links worden geweigerd. |
| Inloggen en sessie | Rol bepaalt de startpagina; gedeactiveerde of nog niet toegelaten accounts krijgen geen toegang. |
| Beoordeling | Alleen geverifieerde pending-aanvragen van de eigen afdeling; één definitieve beoordeling. |
| Documenten | Geautoriseerde downloads; geen toegang via vreemde bedrijfs- of aanvraag-ID's. |
| Planning | Alleen goedgekeurde sollicitanten bij hun reviewer; één actieve afspraak; boeking binnen werktijd. |
| Gelijktijdig boeken | Eén succes en één conflict bij hetzelfde medewerkersslot; geen dubbele actieve afspraak. |
| Annuleren en herboeken | Eigenaar of toegewezen medewerker; oude afspraak cancelled; slot kan opnieuw worden geboekt. |
| Agenda-e-mail | Beide deelnemers krijgen REQUEST/CANCEL met stabiele UID en oplopende SEQUENCE. |
| Bedrijfsbeheer | Alleen beheerder; toevoegen, wijzigen, slug, huisstijl, sjablonen en deactiveren werken samen. |
| Medewerkersbeheer | Uitnodiging, wachtwoordactivering, afdeling, rooster en deactivering; geen onbedoelde accountwijziging. |
| Initialisatie | Eerste beheerder mogelijk; opnieuw uitvoeren overschrijft geen bestaande beheerder. |
| Cleanup | Alleen oude afgewezen/onbevestigde dossiers weg; recente en goedgekeurde dossiers blijven. |
| Accountverwijdering | Wachtwoord en eigen account vereist; documenten weg; anonieme agenda-annulering voor medewerker. |
| SMTP-storing | Bericht blijft versleuteld klaarstaan; retry werkt; geslaagde aflevering wist de payload. |

Beheerders hebben bewust portaalbrede bevoegdheden. De tests moeten die uitzondering
onderscheiden van de bedrijfsgrenzen voor medewerkers en sollicitanten.

## Browseracceptatie

Controleer op desktop en smal scherm de volledige route van solliciteren naar beoordelen
en boeken. Gebruik aparte browserprofielen voor sollicitant, medewerker en beheerder.
Controleer labels, toetsenbordfocus, foutmeldingen, uitloggen, lege lijsten, bestandnamen,
de dialoog voor annuleren en het live huisstijlvoorbeeld.

Controleer in de beheeromgeving het uitnodigen van een medewerker, een gesplitst weekrooster,
de aangepaste beschikbaarheid, het vervangen en verwijderen van een logo en het wisselen
tussen de twee bedrijven. Open ontvangen agenda-uitnodigingen en annuleringen ook in de
beoogde mail- en agendaclient.

Controleer op de uiteindelijke host HTTPS, cookies, API-rewrites, onbereikbaarheid van
`.env`, private opslag en broncode, SMTP-aflevering en de geplande onderhoudstaken.
Deze acceptatiestappen gelden als afzonderlijke controles naast de lokale testresultaten.
