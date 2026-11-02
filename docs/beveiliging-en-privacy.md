# Beveiliging en privacy

Dit document beschrijft de technische werking van deze versie en de gegevens die de
beheerder moet beheren. De bewaartermijn is een applicatie-instelling.

## Toegang en accounts

De server controleert bij een verzoek de actieve bedrijfscontext, de sessie en de rol.
Medewerkers zien sollicitaties van hun eigen afdeling binnen hun eigen bedrijf.
Een sollicitant kan alleen de eigen afspraken beheren. De beheerrol heeft bewust
portaalbrede toegang tot bedrijven en medewerkers.

Wachtwoorden worden gehasht met bcrypt. Een nieuwe sollicitant heeft geen wachtwoord;
eerst volgen e-mailverificatie en goedkeuring. De eenmalige links zijn 48 uur geldig,
gebonden aan bedrijf en doel en worden onder een databasevergrendeling verbruikt.
Alleen de tokenhash staat in de tokentabel. Een nieuwe uitnodiging trekt eerdere
ongebruikte tokens voor hetzelfde doel in.

Bij `APP_ENV=local` maakt de installatie voor handmatig testen Scabro en een direct
geverifieerde beheerder `test@admin.nl` met wachtwoord `1234` aan. Het wachtwoord staat
als bcrypt-hash in de database. `scripts/seed-test-admin.php` voegt dit account ook aan
een bestaande lokale database toe zonder bestaande accounts of wachtwoorden te wijzigen.
Het seedcommando weigert buiten de lokale omgeving. Het normale beleid voor het instellen
van wachtwoorden blijft minimaal 12 tekens.

Wijzigende verzoeken vereisen een CSRF-token. Login vernieuwt de sessie-ID en het
CSRF-token. Cookies zijn HttpOnly en SameSite=Lax; de Secure-instelling wordt via
`SESSION_SECURE` geregeld. De actieve status van het account wordt opnieuw gecontroleerd.
De applicatie begrenst herhaalde login-, registratie- en tokenverzoeken.

## Documenten en huisstijl

CV en motivatie zijn beperkt tot PDF of DOCX en maximaal 5 MB per bestand. De backend
controleert inhoud, type en structuur. De opslag gebruikt willekeurige bestandsnamen
buiten `public/`. Een documentdownload voert opnieuw de toegangscontrole uit en levert
het bestand als bijlage; interne opslagpaden horen niet bij de API-antwoorden.

Logo's en lettertypen hebben aparte type- en groottecontroles. SVG-bestanden worden
op actieve inhoud, externe verwijzingen en onveilige constructies gecontroleerd.
Themakleuren, slugs en bestandsnamen worden gevalideerd. Ongeldige thema's krijgen een
veilige terugval. Inhoudsvalidatie is geen malwareonderzoek van documenten.

PDO gebruikt prepared statements. De frontend plaatst gebruikerswaarden als tekst.
Alleen `public/` is documentroot; configuratie, broncode en private opslag zijn geen
openbare downloadmappen. Het PHP-proces heeft schrijfrechten nodig op de aangewezen
opslag- en themamappen.

## E-mail en afspraken

Berichten worden versleuteld in `mail_queue` gezet. De mailworker ontsleutelt ze met
`APP_KEY`, levert ze af via SMTP en wist na succesvolle aflevering de payload.
Bewaar de sleutel buiten de broncode en wijzig hem niet terwijl er nog berichten
in de wachtrij staan. Tokens en wachtwoorden horen niet in logs of foutmeldingen.

Afspraak en bevestigingsberichten worden in dezelfde databasetransactie aangemaakt.
Rijvergrendelingen en unieke indexen voorkomen dubbele actieve boekingen.
Annuleren bewaart dezelfde agenda-UID en verhoogt de SEQUENCE. De beschikbaarheid is
lokaal; wijzigingen vanuit Outlook worden niet teruggelezen.

## Bewaren en verwijderen

`RETENTION_DAYS` is standaard 28. Het cleanup-commando verwijdert oude afgewezen dossiers
vanaf de beoordelingsdatum en oude nog onbevestigde aanvragen vanaf de aanmaakdatum.
Het verwijdert de bijbehorende gebruiker, sollicitatie, documenten en relevante
wachtrijgegevens. De selectie wordt onder een lock opnieuw gecontroleerd zodat een
inmiddels bevestigde of goedgekeurde aanvraag niet op basis van een oude selectie verdwijnt.

Goedgekeurde dossiers en bevestigde maar onbeoordeelde dossiers worden niet automatisch
door deze criteria verwijderd. Bepaal voor die dossiers afzonderlijk wanneer de procedure
is afgerond en hoe ze worden verwijderd. Leg ook vast hoe verwijderde gegevens uit
back-ups verdwijnen en hoe een herstel met eerdere verwijderingen omgaat.

Een ingelogde sollicitant kan het eigen account na wachtwoordbevestiging verwijderen.
Documenten en afspraken worden verwijderd; een benodigde agenda-annulering voor de
medewerker bevat geen naam of e-mailadres van de verwijderde sollicitant.

De organisatie bepaalt welke gegevens nodig zijn en hoe betrokkenen worden geïnformeerd.
De formulieren vragen naam, e-mailadres, sollicitatierichting, cv en motivatie.
Een privacycontact en processen voor overige inzage-, correctie- en verwijderverzoeken
moeten bij ingebruikname door de beheerder worden vastgelegd.

## Ingebruikname en onderhoud

Gebruik op de uiteindelijke host HTTPS, `APP_ENV=production`, `SESSION_SECURE=true`,
een eigen `APP_KEY`, één eigen beheerdersaccount en werkende beveiligde SMTP.
Gebruik een aparte productiedatabase zonder het lokale testaccount en testgegevens.
Een wijziging van `APP_ENV` verwijdert eerder aangemaakte accounts niet.
Richt het eerste beheerdersaccount eenmalig in met `scripts/create-admin.php` en
een eigen e-mailadres. Medewerkers ontvangen daarna persoonlijke uitnodigingen.
`SMTP_ENCRYPTION=none` uit de lokale mailtestinstructies is uitsluitend bedoeld voor
een SMTP-inbox op dezelfde computer. Fictieve adressen in tests horen bij de regressietests.
Het verwijderen van voorbeeldbestanden wijzigt geen bestaande database; controleer
bestaande accounts en gegevens afzonderlijk bij ingebruikname.
Plan de mailworker en cleanup afzonderlijk en controleer de uitvoer. Richt de webserver
op `public/` en controleer de API-routering op die host.

De lokale tests vervangen geen controle van de uiteindelijke webserver, echte
mailprovider, browserbediening of een praktische Outlook-clienttest. Zie het testplan
voor de uitgevoerde controles en de resterende acceptatiestappen.
