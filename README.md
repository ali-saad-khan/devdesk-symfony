# DevDesk

A helpdesk and ticketing app built with **Symfony 7.4**, **PHP 8.4**, **Doctrine** and **PostgreSQL**.
Customers open tickets, agents work them, admins oversee everything. Built as a production-minded
reference project: real access control, tests, static analysis, CI, a hardened container image, and a
serverless deployment path.

> **Live demo:** _add your Vercel URL here after deploying_ · demo logins are in [docs/DEPLOY.md](docs/DEPLOY.md)

## What it demonstrates

| Area | What's in the repo |
|---|---|
| **Security** | Form login with CSRF, login throttling, remember-me, email verification before sign-in, role hierarchy (customer → agent → admin), a `TicketVoter` for per-ticket access, hashed API tokens (SHA-256, shown once), registration rate limit, security headers incl. a strict CSP |
| **Domain model** | Doctrine entities (`User`, `Ticket`, `Comment`), backed enums for status and priority, migrations, fixtures, indexed queries |
| **Workflow** | Ticket lifecycle as a Symfony **state machine** (`open → in_progress → resolved → closed`, plus `reopen`); customers may only close or reopen their own tickets |
| **SLA tracking** | Each priority has a first-response deadline; the first public agent reply stops the clock; breaches are detected by a console command or a protected cron endpoint and notified exactly once |
| **API** | Token-authenticated REST API with request DTO validation (`#[MapRequestPayload]`), explicit response shape, per-user rate limiting, OpenAPI spec at `public/api/openapi.yaml` |
| **Async** | Symfony Messenger (Doctrine transport) for email notifications, with retries and a failure transport |
| **Quality** | PHPUnit unit and functional tests (DB rolled back per test via DAMA), PHPStan **level 8**, PHP-CS-Fixer, `composer audit`, GitHub Actions CI |
| **Operations** | `/health` endpoint, structured JSON logs to stderr in prod, env-driven config, multi-stage Dockerfile (`docker/Dockerfile.prod`, FrankenPHP, non-root, opcache, pre-warmed cache, healthcheck), DB-backed sessions for stateless hosting |

## Quick start (Docker)

Requires Docker only; no local PHP needed.

```bash
make up                                   # app :8080, Postgres, Mailpit :8025
make console c="doctrine:migrations:migrate -n"
make console c="doctrine:fixtures:load -n"   # demo data (dev only!)
open http://localhost:8080
```

Demo accounts (password `devdesk-demo-2026`): `customer@devdesk.test`, `agent@devdesk.test`, `admin@devdesk.test`.
Outgoing mail is caught by Mailpit at http://localhost:8025. Run the background worker with
`make console c="messenger:consume async -vv"`.

```bash
make test    # PHPUnit
make stan    # PHPStan level 8
make cs      # PHP-CS-Fixer
```

## API

Create a token on the **Account** page, then:

```bash
curl -H "Authorization: Bearer dd_…" http://localhost:8080/api/tickets
curl -X POST -H "Authorization: Bearer dd_…" -H "Content-Type: application/json" \
  -d '{"title":"Printer on fire","description":"Smoke everywhere, please help.","priority":"urgent"}' \
  http://localhost:8080/api/tickets
```

Full reference: [`public/api/openapi.yaml`](public/api/openapi.yaml).

## Documentation

- [Architecture and design decisions](docs/ARCHITECTURE.md)
- [Deployment (Vercel, Docker)](docs/DEPLOY.md)

## Project layout

```
src/Entity        Doctrine entities          src/Security      Voter, API token handler, user checker
src/Enum          Status/priority enums      src/Service       SLA checker, DB session handler
src/Controller    HTTP + API controllers     src/Command       Console commands
src/Message*      Messenger messages/handlers  tests/          Unit + functional tests
```
