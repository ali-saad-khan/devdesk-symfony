# Deployment

## Environment variables

| Variable | Required | Notes |
|---|---|---|
| `APP_ENV` | yes | `prod` |
| `APP_SECRET` | yes | long random string (`openssl rand -hex 32`) |
| `DATABASE_URL` | yes | `postgresql://USER:PASS@HOST/DB?serverVersion=16&sslmode=require&charset=utf8` |
| `MAILER_DSN` | yes | SMTP/provider DSN; `null://null` discards mail |
| `DEFAULT_URI` | yes | public base URL, used in emails |
| `MESSENGER_TRANSPORT_DSN` | on Vercel | `sync://` (no worker available) |
| `TRUSTED_PROXIES` | on Vercel | `REMOTE_ADDR`. Needed so Symfony sees `https` behind the proxy (CSRF and redirects break otherwise). Leave unset on hosts with no trusted proxy |
| `CRON_SECRET` | for SLA cron | Vercel sends it as `Authorization: Bearer …` |
| `REQUIRE_EMAIL_VERIFICATION` | optional | `0` only for demos without a mail provider |
| `MAIL_FROM` | optional | sender address |

## Vercel

Vercel has no official PHP runtime. This project uses the community runtime
[`vercel-php`](https://github.com/vercel-community/php) (`vercel-php@0.8.0` = PHP 8.4), configured in `vercel.json`;
`api/index.php` forwards every request to Symfony.

1. Create a free PostgreSQL database (Neon, Supabase, …) and copy its connection string.
2. `npm i -g vercel && vercel login && vercel link` from the project root.
3. Add the environment variables above (`vercel env add …`), including `APP_ENV=prod`, `TRUSTED_PROXIES=REMOTE_ADDR` and `MESSENGER_TRANSPORT_DSN=sync://`.
4. Run migrations once from your machine against that database:
   `DATABASE_URL=… APP_ENV=prod php bin/console doctrine:migrations:migrate -n`
5. (Optional demo data) `DATABASE_URL=… php bin/console doctrine:fixtures:load -n --env=dev`. Demo accounts only.
6. `vercel --prod`.

Serverless constraints already handled: read-only filesystem (cache/logs → `/tmp`), no shared disk (sessions in PostgreSQL),
no worker (sync messenger + a daily Vercel Cron on `/cron/sla`; Hobby plans allow one run per day), no asset build step
(CSS is inlined).

## Docker

```bash
docker build -f docker/Dockerfile.prod -t devdesk .
docker run -p 8080:8080 -e APP_SECRET=… -e DATABASE_URL=… -e MAILER_DSN=… -e DEFAULT_URI=https://desk.example.com devdesk
```

(The production Dockerfile lives in `docker/` on purpose: a root-level `Dockerfile` makes Vercel build a container instead of using the PHP runtime.)

The image runs as a non-root user, has a healthcheck on `/health`, and ships with a pre-warmed prod cache.
Run `bin/console doctrine:migrations:migrate -n` as a release step, and a separate container with
`bin/console messenger:consume async` for emails plus a scheduler for `app:tickets:check-sla`.
