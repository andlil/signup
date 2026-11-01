# signup

A self-hosted web app for signing up to events through a unique link per event. Think of it as a small Google Forms with places, a waiting list and Excel export, running on your own server. The requirements specification is in [SPEC.md](SPEC.md).

Built with Laravel 13, Livewire 4 and MariaDB. Runs with Docker Compose. The interface is available in Swedish and English.

## Features

- **Organizer accounts with magic link login.** A main admin creates the organizer accounts; nobody has a password.
- **Events** with a description edited in a rich text editor, time and place, a last day to sign up and an optional number of places.
- **Form builder** with short and long text, multiple choice, dropdown, checkboxes, date, number and vacation planning, where participants tick the days they want off in a calendar. The form is locked once the event is published.
- **Waiting list** that moves people up automatically when places become free. Places can be counted per signup or from a "number of people" question.
- **Personal link** in the confirmation email, where participants change or cancel their signup.
- **Email notifications** to participants and organizers, and an optional daily summary.
- **Excel export** of the signups, also emailed to the organizer when signups close.
- **GDPR**: participants give consent, and signups are deleted automatically a chosen number of days after the event.

## Getting started with Docker

1. Create the configuration. Then set your own database password (`DB_PASSWORD`) in `.env`, and your email address in `ADMIN_EMAIL` (and preferably your name in `ADMIN_NAME`):

   ```bash
   cp .env.example .env
   ```

2. Build the image:

   ```bash
   docker compose build
   ```

3. Start everything (app, database, queue worker and scheduler). The migrations run automatically, and the main admin is created from `ADMIN_EMAIL`:

   ```bash
   docker compose up -d
   ```

4. Go to http://localhost:8080 and log in with your email address.

The application key (`APP_KEY`) is generated on the first start and kept in the storage volume (`storage/app/private/app-key`). Include the volume in your backups: without the key, participants' personal links stop working. If you would rather manage the key yourself, set `APP_KEY` in `.env` and it is used instead.

The main admin is only created when there is none yet, so `ADMIN_EMAIL` can stay in `.env`. To make an existing account main admin later, run:

```bash
docker compose exec -u www-data app php artisan signup:create-admin you@example.com --name="Your Name"
```

The port is set with `APP_PORT` in `.env`. Set `APP_URL` to the address the app is reached at, since it is used in the links in emails. In production, also set `APP_ENV=production` and `APP_DEBUG=false`.

After changing `.env`, apply it with `docker compose up -d`. `docker compose restart` does not read `.env` again.

## Behind a reverse proxy

Put server-specific settings, such as labels and networks for your proxy, in `docker-compose.override.yml`. Docker Compose reads it automatically, and it is excluded in `.gitignore`. Example for Traefik:

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

Keep `default` in the list of networks, since the app reaches the database through it. `ports: !reset []` removes the published port so the app is only reachable through the proxy; it requires Docker Compose 2.24 or later.

Set `APP_URL` to the public address (`https://signup.example.com`). The app trusts `X-Forwarded-*` headers from Docker's private networks, so links and Livewire use https. If the proxy is somewhere else, set its address in `TRUSTED_PROXIES`.

## Email

By default (`MAIL_MAILER=log`) no emails are sent. They are written to `storage/logs/laravel.log` instead, so you can get the latest login link from there:

```bash
docker compose exec app sh -c "grep -o 'http[^ ]*/login/[A-Za-z0-9]*' storage/logs/laravel.log | tail -1"
```

For real emails, set the SMTP details in `.env` and apply them with `docker compose up -d`:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=username
MAIL_PASSWORD=password
MAIL_FROM_ADDRESS="signup@example.com"
```

Use port 587 with `MAIL_SCHEME=smtp` (STARTTLS), or port 465 with `MAIL_SCHEME=smtps`. `MAIL_ENCRYPTION` is no longer used by Laravel.

Emails are sent through the queue, which is why the `queue` service must be running. To check the settings, send a test email directly, without the queue:

```bash
docker compose exec -u www-data -e HOME=/tmp queue php artisan tinker --execute 'Mail::raw("Test from signup", fn ($m) => $m->to("you@example.com")->subject("Test"));'
```

### Postmark, Resend or Amazon SES

Use the services' SMTP interface with `MAIL_MAILER=smtp` and the details below:

| Service | `MAIL_HOST` | `MAIL_PORT` | `MAIL_USERNAME` | `MAIL_PASSWORD` |
|---|---|---|---|---|
| Postmark | `smtp.postmarkapp.com` | 587 | Server API token | Server API token |
| Resend | `smtp.resend.com` | 587 | `resend` | API key |
| Amazon SES | `email-smtp.<region>.amazonaws.com` | 587 | SMTP username | SMTP password |

For SES, create the SMTP credentials in the SES console. They are not the same as regular AWS keys.

`MAIL_FROM_ADDRESS` must belong to a domain verified with the service, or the emails are rejected.

Do not set `MAIL_MAILER=postmark`, `resend` or `ses`. Those drivers need extra PHP packages that are not installed, so no emails would be sent at all.

## Scheduled tasks

The `scheduler` service runs these tasks (times are in `APP_TIMEZONE`, Europe/Stockholm by default):

| When | Task |
|------|------|
| Every 15 minutes | `signup:send-closing-exports`: emails the Excel file of signups to the organizer once signups have closed |
| 07:00 | `signup:send-daily-summaries`: daily summary to organizers who chose it |
| 03:00 | `signup:delete-expired-signups`: deletes signups once the event's retention period has passed (GDPR) |
| 03:30 | `model:prune`: removes expired login links |

Without Docker, run `php artisan schedule:work` (or `schedule:run` from cron every minute) and `php artisan queue:work`.

## Development without Docker

Requires PHP 8.3, Composer and Node. Use SQLite by setting `DB_CONNECTION=sqlite` in `.env`.

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

The seeder creates `admin@example.com` (main admin) and `arrangor@example.com` (organizer) with an example event.

## Tests

```bash
php artisan test
```
