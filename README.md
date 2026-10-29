# signup

Self-hostad webbapp för anmälan till event via en unik länk per event. Se [SPEC.md](SPEC.md) för kravspecifikationen.

Byggd med Laravel 13, Livewire 4 och MariaDB. Körs med Docker Compose.

## Kom igång med Docker

1. Skapa konfigurationen och sätt ett eget databaslösenord (`DB_PASSWORD`) i `.env`:

   ```bash
   cp .env.example .env
   ```

2. Bygg avbildningen och generera en applikationsnyckel. Klistra in nyckeln som `APP_KEY=` i `.env`:

   ```bash
   docker compose build
   ```

   ```bash
   docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
   ```

3. Starta allt (app, databas, kö-arbetare och schemaläggare). Migreringarna körs automatiskt:

   ```bash
   docker compose up -d
   ```

4. Skapa huvudadmin:

   ```bash
   docker compose exec -u www-data app php artisan signup:create-admin du@example.com --name="Ditt namn"
   ```

5. Gå till http://localhost:8080 och logga in med din e-postadress.

Porten styrs av `APP_PORT` i `.env`. Sätt `APP_URL` till den adress appen nås på, eftersom den används i länkarna i mejlen.
I produktion bör du även sätta `APP_ENV=production` och `APP_DEBUG=false`.

## E-post

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

Seedern skapar `admin@example.com` (huvudadmin) och `arrangor@example.com` (arrangör).

## Tester

```bash
php artisan test
```
