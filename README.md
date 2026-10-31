# signup

Self-hostad webbapp för anmälan till event via en unik länk per event. Se [SPEC.md](SPEC.md) för kravspecifikationen.

Byggd med Laravel 13, Livewire 4 och MariaDB. Körs med Docker Compose.

## Kom igång med Docker

1. Skapa konfigurationen. Sätt sedan ett eget databaslösenord (`DB_PASSWORD`) i `.env`, och din e-postadress i `ADMIN_EMAIL` (och gärna ditt namn i `ADMIN_NAME`):

   ```bash
   cp .env.example .env
   ```

2. Bygg avbildningen:

   ```bash
   docker compose build
   ```

3. Starta allt (app, databas, kö-arbetare och schemaläggare). Migreringarna körs automatiskt, och huvudadmin skapas från `ADMIN_EMAIL`:

   ```bash
   docker compose up -d
   ```

4. Gå till http://localhost:8080 och logga in med din e-postadress.

Applikationsnyckeln (`APP_KEY`) skapas automatiskt vid första starten och sparas i lagringsvolymen (`storage/app/private/app-key`). Ta med volymen i dina backuper: utan nyckeln slutar deltagarnas personliga länkar att fungera. Vill du hellre hantera nyckeln själv sätter du `APP_KEY` i `.env`, och då används den i stället.

Huvudadmin skapas bara om det inte redan finns någon, så det gör inget att `ADMIN_EMAIL` ligger kvar i `.env`. Vill du göra ett befintligt konto till huvudadmin senare kör du:

```bash
docker compose exec -u www-data app php artisan signup:create-admin du@example.com --name="Ditt namn"
```

Porten styrs av `APP_PORT` i `.env`. Sätt `APP_URL` till den adress appen nås på, eftersom den används i länkarna i mejlen.
I produktion bör du även sätta `APP_ENV=production` och `APP_DEBUG=false`.

## Bakom en reverse proxy

Lägg serverspecifika inställningar, som etiketter och nätverk för din proxy, i `docker-compose.override.yml`. Docker Compose läser in filen automatiskt, och den är undantagen i `.gitignore`. Exempel för Traefik:

```yaml
services:
  app:
    labels:
      - traefik.enable=true
      - traefik.http.routers.signup.rule=Host(`signup.example.com`)
      - traefik.http.routers.signup.entrypoints=websecure
      - traefik.http.routers.signup.tls.certresolver=letsencrypt
      - traefik.http.services.signup.loadbalancer.server.port=80
      - traefik.docker.network=proxy
    networks:
      - default
      - proxy
    ports: !reset []

networks:
  proxy:
    external: true
```

Sätt `APP_URL` till den publika adressen (`https://signup.example.com`). Appen litar på `X-Forwarded-*`-huvuden från Dockers privata nätverk, så att länkar och Livewire använder https. Ligger proxyn någon annanstans anger du dess adress i `TRUSTED_PROXIES`.



Som standard (`MAIL_MAILER=log`) skickas inga mejl. De skrivs i stället till `storage/logs/laravel.log`, så att du kan hämta inloggningslänkarna där:

```bash
docker compose exec app grep -o 'http[^ ]*/login/[A-Za-z0-9]*' storage/logs/laravel.log
```

För riktiga mejl sätter du SMTP-uppgifterna i `.env` och startar om:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=användare
MAIL_PASSWORD=lösenord
MAIL_FROM_ADDRESS="signup@example.com"
```

Mejlen skickas via kön. Det är därför tjänsten `queue` måste vara igång.

### Postmark, Resend eller Amazon SES

Använd tjänsternas SMTP-gränssnitt med `MAIL_MAILER=smtp` och uppgifterna nedan:

| Tjänst | `MAIL_HOST` | `MAIL_PORT` | `MAIL_USERNAME` | `MAIL_PASSWORD` |
|---|---|---|---|---|
| Postmark | `smtp.postmarkapp.com` | 587 | Server API token | Server API token |
| Resend | `smtp.resend.com` | 587 | `resend` | API-nyckel |
| Amazon SES | `email-smtp.<region>.amazonaws.com` | 587 | SMTP-användarnamn | SMTP-lösenord |

För SES skapar du SMTP-uppgifterna i SES-konsolen. Det är inte samma sak som vanliga AWS-nycklar.

`MAIL_FROM_ADDRESS` måste höra till en domän som är verifierad hos tjänsten, annars nekas mejlen.

Sätt inte `MAIL_MAILER=postmark`, `resend` eller `ses`. De drivrutinerna kräver extra PHP-paket som inte är installerade, så då skickas inga mejl alls.

## Schemalagda jobb

Tjänsten `scheduler` kör följande jobb (tiderna gäller `APP_TIMEZONE`, som standard Europe/Stockholm):

| Tid | Jobb |
|-----|------|
| Var 15:e minut | `signup:send-closing-exports`: mejlar Excel-filen med anmälningarna till arrangören när anmälan har stängt |
| 07:00 | `signup:send-daily-summaries`: daglig sammanfattning till arrangörer som har valt det |
| 03:00 | `signup:delete-expired-signups`: raderar anmälningar när eventets gallringstid har gått ut (GDPR) |
| 03:30 | `model:prune`: rensar utgångna inloggningslänkar |

Utan Docker behöver du köra `php artisan schedule:work` (eller `schedule:run` från cron varje minut) och `php artisan queue:work`.

## Utveckling utan Docker

Kräver PHP 8.3, Composer och Node. Ställ in SQLite med `DB_CONNECTION=sqlite` i `.env`.

```bash
composer install
```

```bash
npm install && npm run build
```

```bash
php artisan migrate --seed
```

```bash
composer run dev
```

Seedern skapar `admin@example.com` (huvudadmin) och `arrangor@example.com` (arrangör) med ett exempelevent.

## Tester

```bash
php artisan test
```
