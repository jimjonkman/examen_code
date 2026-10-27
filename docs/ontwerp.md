# Ontwerp en werkverdeling

## Doel en gebruikersstroom

Eén sollicitatieportaal voor meerdere bedrijven, elk met een eigen huisstijl.
Een sollicitant moet worden beoordeeld voordat toegang tot de gespreksplanning mogelijk is.

Bedrijf kiezen → gegevens, cv en motivatie insturen → e-mailadres bevestigen →
beoordeling door de juiste afdeling → bij goedkeuring wachtwoord kiezen → inloggen.
Gespreksplanning wordt hier later op aangesloten.

## Technische basis

De browser gebruikt HTML, CSS en JavaScript ES-modules. PHP levert JSON via een API.
PDO gebruikt prepared statements. MariaDB bewaart de gegevens. De openbare map is
`public/`; instellingen, backendcode en geüploade documenten blijven daarbuiten.

De bedrijfslink bevat een slug. De server controleert het actieve bedrijf en begrenst
account- en sollicitatieverzoeken op bedrijf, rol en waar nodig afdeling.
Een medewerker kan de eigen afdeling wijzigen. Stage en open sollicitaties horen
bij **open/intern**, terwijl de gekozen sollicitatierichting bewaard blijft.

Wachtwoorden worden gehasht. Een nieuwe sollicitant krijgt aanvankelijk geen wachtwoord.
Bevestigings- en wachtwoordlinks bevatten een eenmalig token dat gehasht in de database
staat. De mailwachtrij bewaart berichten versleuteld met `APP_KEY`; de mailworker levert
de berichten af via SMTP. Sessies en CSRF-tokens beschermen accountacties.

Thema's staan in `public/themes/<slug>/theme.json`. Kleuren en bestandsnamen worden gecontroleerd.
Documentuploads worden op inhoud, type en grootte gecontroleerd en via een afgeschermde route opgehaald.

Het bestaande schema bevat ook de geplande werktijden-, afspraken- en beheergegevens.
Die tabellen zijn voorbereiding; de agenda en beheeromgeving zijn nog niet beschikbaar.

## Werkverdeling

| Dag | Onderdeel | Status |
| --- | --- | --- |
| Maandag | Projectstructuur, databaseontwerp, bedrijvenoverzicht en huisstijl. | Aanwezig. |
| Dinsdag | Solliciteren, uploads, inloggen, verificatie, afdelingsgebonden beoordeling en e-mail. | Toegevoegd. |
| Donderdag | Beschikbaarheid, afspraken boeken en annuleren, agenda-uitnodigingen. | Gepland. |
| Vrijdag | Beheer, afronding, volledige controles en documentatie. | Gepland. |

Woensdag is geen werkdag in deze planning. Alle aanvullingen komen rechtstreeks in
dezelfde projectmap `C:\Users\JimJonkman\Desktop\examen_code`.
