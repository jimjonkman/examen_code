# Ontwerp en werkverdeling

## Doel

Eén sollicitatieportaal voor meerdere bedrijven, elk met een eigen huisstijl.
Een sollicitant moet eerst worden beoordeeld voordat een gesprek kan worden ingepland.

## Geplande gebruikersstroom

Bedrijf kiezen → gegevens, cv en motivatie insturen → e-mailadres bevestigen →
beoordeling door de juiste afdeling → bij goedkeuring wachtwoord kiezen → gesprek inplannen.

## Technische basis

De browser gebruikt HTML, CSS en JavaScript ES-modules. PHP levert JSON via een kleine API.
PDO gebruikt prepared statements voor bedrijfsselectie. MariaDB bewaart de gegevens.
De openbare map is `public/`; instellingen en backendcode blijven daarbuiten.
De bedrijfslink bevat een slug; de server controleert die en haalt alleen een actief bedrijf op.
Thema's staan in `public/themes/<slug>/theme.json`. Kleuren en bestandsnamen worden gecontroleerd.

Het schema bevat de geplande bedrijfs-, gebruikers-, sollicitatie-, token-, werktijden-,
afspraken-, mail- en limiettabellen. Relaties bevatten waar nodig `company_id` om gegevens
van verschillende bedrijven te scheiden. De tabellen zijn ontwerpvoorbereiding;
de bijbehorende functies zijn nog niet allemaal geïmplementeerd.

## Werkverdeling

| Dag | Onderdeel |
| --- | --- |
| Maandag | Projectstructuur, databaseontwerp, bedrijvenoverzicht en bedrijfspagina met huisstijl. |
| Dinsdag | Solliciteren, documentuploads, inloggen, verificatie, afdelingsgebonden beoordeling en e-mail. |
| Donderdag | Beschikbaarheid, afspraken boeken en annuleren, agenda-uitnodigingen. |
| Vrijdag | Beheer, afronding, volledige controles en documentatie. |

Woensdag is geen werkdag in deze planning. Volgende dagmappen worden pas op verzoek gemaakt.
Elke dagmap is een zelfstandig, cumulatief project: de nieuwe onderdelen plus de bestaande basis.
Deze planning beschrijft de verdeling van projectonderdelen, geen historische urenregistratie.
