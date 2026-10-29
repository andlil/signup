# signup – kravspecifikation

Self-hostad webbapp för anmälan till event (t.ex. semester, fest, kurs) via en
unik länk per event. Ungefär som Google Forms, men med platser, väntelista och
Excel-export.

## Teknik

- Laravel (PHP 8.3+), MySQL/MariaDB
- Blade + Livewire för gränssnittet
- Docker / docker-compose: `app` (PHP + Apache), `db` (MariaDB), `queue` (kö-arbetare), `scheduler`
- E-post via Laravels mail-konfiguration i `.env` (`MAIL_MAILER=log` under utveckling, SMTP eller tjänst senare)
- Gränssnitt på svenska och engelska (`lang/sv`, `lang/en`)
- Excel-export med `.xlsx`

## Roller

| Roll        | Beskrivning |
|-------------|-------------|
| Huvudadmin  | Skapar, inaktiverar och raderar arrangörskonton. Har full insyn i och kan redigera och radera alla event. |
| Arrangör    | Loggar in med magic link. Skapar och hanterar sina egna event. |
| Deltagare   | Har inget konto. Anmäler sig via eventets publika länk. |

Den första huvudadmin skapas med kommandot `php artisan signup:create-admin`.

## Inloggning (magic link)

- Arrangören anger sin e-post och får ett mejl med en engångslänk.
- Länken är giltig i 15 minuter och kan bara användas en gång.
- Bara konton som huvudadmin har skapat och som är aktiva kan logga in. Svaret är detsamma oavsett om e-posten finns, så att man inte kan ta reda på vilka konton som existerar.
- Inloggningsförsöken är rate-limitade.

## Event

- Titel, beskrivning (formateras med knappar för fetstil, kursiv, rubriker, listor, citat och länkar; sparas som Markdown), plats, starttid och sluttid
- Eventet måste ha en starttid, en sista anmälningsdag eller båda. En sluttid kräver en starttid. Utan starttid (t.ex. en förfrågan om semesterdatum) räknas avbokning och gallring från sista anmälningsdagen.
- Publik länk med en slumpad slug: `/e/{slug}`
- Status: utkast → publicerat → stängt
- Sista anmälningsdag (valfri). Efter den är formuläret stängt. Anmälan stängs alltid när eventet börjar.
- Max antal platser (valfritt). Arrangören väljer räknesätt per event:
  - en plats per anmälan, eller
  - via ett valt antal-fält i formuläret (t.ex. "antal personer")
- Väntelista när eventet är fullt
- Notiser till arrangören, valbara per event: vid varje anmälan/avbokning, daglig sammanfattning eller inga
- GDPR: antal dagar efter eventets slut innan anmälningarna raderas automatiskt (t.ex. 30 dagar som standard)

## Formulärbyggare

- Fältet **Namn** och fältet **E-post** finns alltid och är obligatoriska.
- Arrangören kan lägga till egna frågor av följande typer:
  - kort text, lång text
  - flerval (radioknappar), rullgardin
  - kryssrutor (flera val)
  - datum
  - antal (heltal, med valfritt min- och maxvärde)
- Varje fråga har etikett, hjälptext, kan vara obligatorisk och har en sorteringsordning.
- **När eventet har publicerats kan frågorna inte ändras.** Formuläret är då låst. Beskrivning, datum och liknande kan fortfarande redigeras.

## Anmälan (deltagare)

- Deltagaren fyller i formuläret och kryssar i samtyckesrutan (obligatoriskt).
- Om det finns plats bekräftas anmälan, annars hamnar deltagaren på väntelistan (deltagaren ser vilket som gäller).
- Deltagaren får ett bekräftelsemejl med en personlig hemlig länk där anmälan kan ändras eller avbokas.
- Ändringar är möjliga fram till sista anmälningsdag. Avbokning är möjlig fram till eventets start, eller fram till sista anmälningsdag om eventet saknar starttid.

## Väntelista

- Ordningen bestäms av när anmälan gjordes.
- När en plats blir ledig (vid avbokning, ändrat antal eller utökat max) flyttas den som står först upp automatiskt, så länge personen ryms, och får ett mejl.
- Om platserna räknas via antal-fältet och den som står först inte ryms, prövas nästa person i ordningen.
- En bekräftad deltagare som vill öka sitt antal får det bara om platserna räcker. Annars visas ett fel, och deltagaren behåller sin plats.
- En anmälan får inte vara större än eventets totala antal platser.
- Om arrangören minskar antalet platser avbokas ingen. Nya anmälningar hamnar då på väntelistan.
- Varje e-postadress kan bara ha en aktiv anmälan per event.

## Admin för arrangör

- Lista över egna event med antal anmälda, antal på väntelistan och antal lediga platser
- Anmälningslista per event, där arrangören kan avboka enskilda anmälningar (deltagaren får ett mejl) och radera avbokade anmälningar
- Excel-export (`.xlsx`) med en rad per anmälan och en kolumn per fråga, plus kolumnerna status (bekräftad/väntelista), platser och tidpunkt för anmälan. Bekräftade listas först, sedan väntelistan i ordning. Avbokade tas inte med. Svar som ser ut som formler sparas som text.

## Bakgrundsjobb (scheduler)

- Daglig sammanfattning kl. 07 till de arrangörer som har valt det: ett mejl per arrangör med nya anmälningar och avbokningar sedan förra sammanfattningen. Inget mejl skickas om inget har hänt.
- Radering av anmälningar för event som slutade för mer än X dagar sedan
- Rensning av utgångna magic link-token

## Utanför omfånget

- Betalningar
- Röstning om datum (som Doodle)
- Konton för deltagare
