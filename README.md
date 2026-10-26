# Sollicitatieportaal — maandag

Deze versie bevat de basis: een bedrijvenoverzicht, een openbare bedrijfspagina,
huisstijl per bedrijf, een kleine PHP-API en het databaseontwerp voor de verdere uitwerking.
De applicatie gebruikt HTML, CSS, JavaScript ES-modules, PHP en MariaDB zonder frameworks.

## Starten

Benodigd: PHP 8.3 of nieuwer met `pdo_mysql` en een draaiende MariaDB 10.5 of nieuwer.

1. Open PowerShell in deze map:

   ```powershell
   cd "C:\Users\JimJonkman\Desktop\examen_code\monday"
   ```

2. Maak in MariaDB een **lege** database `application_portal_monday` en geef je databasegebruiker toegang.
3. Kopieer `.env.example` naar `.env` en vul je databasepoort, gebruikersnaam en wachtwoord in.
4. Voer eenmalig uit om de tabellen en twee voorbeeldbedrijven aan te maken:

   ```powershell
   php scripts/setup.php --seed
   ```

5. Start de website:

   ```powershell
   php scripts/serve.php
   ```

Open http://127.0.0.1:8080/ en kies Scabro of Voorbeeldbedrijf.
De links http://127.0.0.1:8080/?company=scabro en http://127.0.0.1:8080/?company=demo
openen direct een bedrijfspagina. Stop de website met Ctrl+C.

`setup.php` weigert een database waarin al tabellen staan. Start je database apart;
er staat geen databaseserver of databasekopie in deze map. Als `php` niet wordt gevonden,
gebruik dan de volledige locatie van je PHP-programma.

## Wat werkt

- Actieve bedrijven uit de database ophalen en kiezen.
- Bedrijfspagina met eigen kleuren, logo en lettertype.
- Bedrijfslink controleren en foutmeldingen tonen.
- Alleen de openbare map via de ontwikkelserver bereikbaar maken.

Sollicitatieformulieren, accounts, beoordelingen, e-mail, agenda en beheer volgen in latere versies.
Het volledige SQL-schema bevat alvast de geplande tabellen; de seed vult alleen bedrijven.
Er zijn nog geen inlogaccounts of sollicitaties.

## Bestanden

- `public/`: pagina, opmaak, JavaScript en huisstijlen.
- `api/`: twee leesroutes, `/api/companies` en `/api/theme.php?company=scabro`.
- `src/` en `config/`: databaseverbinding, initialisatie, antwoorden en thema's.
- `database/`: schema en lokale voorbeeldbedrijven.
- `scripts/`: installatie en ontwikkelserver.
- `docs/ontwerp.md`: ontwerp en vervolgstappen.

Lokale instellingen en gegenereerde runtimebestanden horen niet bij de broncode.
Git-inrichting wordt apart gedaan.
