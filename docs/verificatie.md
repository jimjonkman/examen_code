# Verificatie van de afronding

Uitgevoerd op **30 oktober 2026** met synthetische gegevens in een aparte lokale
testdatabase. De tests gebruiken tijdelijke PHP-servers en een SMTP-mock op loopback.
De bestaande applicatiedatabase en externe mailontvangers zijn niet gebruikt.

Actualisatie **2 november 2026**: de lokale scripts voor voorbeeldaccounts,
voorbeeldbedrijven en voorbeelddocumenten zijn uit het project verwijderd. Nieuwe
installaties gebruiken `scripts/setup.php` en richten met `scripts/create-admin.php`
één eigen beheerder in. De resultaten hieronder beschrijven de versie van 30 oktober;
de vermelding van geteste demoscripts is historisch. Bestaande databases zijn hiermee
niet automatisch opgeschoond. Geïsoleerde testfixtures blijven onderdeel van de regressietests.

Latere wijziging op **2 november 2026**: voor lokaal testen maakt `scripts/setup.php`
bij `APP_ENV=local` nu Scabro en de beheerder `test@admin.nl` / `1234` aan.
`scripts/seed-test-admin.php` ondersteunt bestaande lokale databases zonder accounts
of wachtwoorden te overschrijven. De installatiehandleiding en `.env.example` zijn
hierop aangepast. De historische resultaten hieronder gelden voor de eerdere versie.

Voor deze wijziging zijn **42/42 zelfstandige controles** en **26/26 integratiecontroles**
geslaagd, met PHP 8.3.33 en een aparte MariaDB 13.0.2-server op loopback. De integratie
controleerde lokale setup, herhaalbaarheid, behoud van gewijzigde wachtwoorden,
conflicten met bestaande accounts of een inactief bedrijf, weigering buiten `local`,
productiesetup zonder accounts, HTTP-login en beheerrechten. Ook de SQL-instructies
uit de README en de verbinding met de bijbehorende databasegebruiker zijn uitgevoerd.
De bestaande `.env` en applicatiedatabase zijn hierbij niet gewijzigd; de tijdelijke
testservers zijn na afloop gestopt. PHP-syntaxcontrole en `git diff --check` zijn geslaagd.

## Omgeving en resultaten

Omgeving: PHP 8.3.33, MariaDB 13.0.2 en Node.js 24.21.0 op Windows.

| Controle | Resultaat |
| --- | --- |
| Volledige HTTP-, database- en SMTP-integratie | 89 gegroepeerde controles geslaagd. |
| `php tests/run.php` | 18/18. |
| `php tests/calendar.php` | 9/9. |
| `php tests/smtp.php` | 7/7. |
| `node tests/theme.mjs` | 7/7. |
| PHP- en JavaScript-syntax | Geslaagd. |

Dit zijn 89 integratiecontroles en 41 zelfstandige controles. Een integratiecontrole
kan meerdere HTTP-verzoeken en databasecontroles omvatten.

## Gecontroleerd gedrag

De integratie controleert registratie, documentvalidatie, verificatie, wachtwoordlinks,
login, uitloggen, rollen, bedrijfs- en afdelingsgrenzen, beoordeling en documentdownloads.
De planning is getest op beschikbaarheid, reviewerbinding, gelijktijdige boekingen,
annuleren, opnieuw boeken, agenda-uitnodigingen en gegevensverwijdering.

De beheercontroles omvatten bevoegdheden, meerdere bedrijven, bedrijfsgegevens,
slugwijzigingen, logo- en fontuploads, ongeldige uploads, terugdraaien van mislukte uploads,
logoverwijdering, e-mailsjablonen, medewerkersuitnodigingen, afdeling, werktijden en
deactivering. De eerste-beheerderprocedure en de herhaalbare lokale demoscripts zijn getest.

Cleanup is gecontroleerd met oude en recente testdossiers: de bedoelde oude dossiers
verdwijnen, terwijl recente en goedgekeurde dossiers blijven staan. Ook SMTP-storingen,
opnieuw afleveren en het wissen van afgeleverde berichtinhoud zijn gecontroleerd.

De testservers starten via `scripts/serve.php`. De gelijktijdigheidstest gebruikt twee
serverprocessen en afzonderlijke sessies; voor hetzelfde slot is de uitkomst één succes
en één conflict. Themebestanden van vóór de test worden hersteld en gegenereerde
testbestanden worden buiten de uiteindelijke broncode gehouden.

## Afzonderlijke acceptatie

Browserbediening en visuele weergave zijn in deze sessie niet getest: de browserintegratie
was niet beschikbaar. De daadwerkelijke Outlook-client, een externe SMTP-provider,
de uiteindelijke HTTPS-webserver en de oorspronkelijke doelcombinatie PHP 8.4/MariaDB 10.5
zijn afzonderlijke acceptatieomgevingen. Het lokale resultaat bewijst die omgevingen niet.

Zie [testplan.md](testplan.md) voor de stappen voor browser- en hostacceptatie.
