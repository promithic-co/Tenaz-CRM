# Tenaz CRM

Multi-tenant AI SDR platform for the Brazilian credit market (consignado INSS & SIAPE). Tenaz automates lead qualification and sales over WhatsApp — from first contact to closed deal — using AI agents that hold conversations, consult credit data, handle objections, and escalate to human operators when needed.

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12 · PHP 8.3 |
| Frontend | Inertia.js v2 · Vue 3 · Tailwind CSS v4 |
| Realtime | Laravel Reverb · Laravel Echo |
| Queues | Laravel Horizon (Redis) |
| Auth | Laravel Fortify |
| AI | Multi-agent pipeline (OpenAI / OpenRouter) |
| Tests | Pest 4 · PHPUnit 12 |
| Tooling | Laravel Pint · ESLint 9 · Prettier 3 · Wayfinder |

## Requirements

- PHP 8.3+
- Composer 2
- Node.js 20+
- PostgreSQL
- Redis

## Local Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database
php artisan migrate --seed

# 4. Run (app + queue + vite, all in one)
composer run dev
```

App serves on the URL printed by the dev server. Frontend assets rebuild on save.

## Common Commands

```bash
php artisan test --compact          # run the test suite
vendor/bin/pint                     # format PHP
npm run lint                        # lint + fix JS/Vue
npm run types:check                 # vue-tsc type check
php artisan horizon                 # process queues
```

## Branching Model

| Branch | Purpose |
|--------|---------|
| `main` | Production. Protected — merge only via reviewed PR. |
| `development` | Integration. Default branch; features merge here first. |
| `feature/*` | New work. Branch off `development`. |
| `hotfix/*` | Urgent production fix. Branch off `main`, merge back into both. |

**Flow:** branch `feature/x` off `development` → PR into `development` → when stable, PR `development` → `main` for release.

## Documentation

- [`ARCHITECTURE.md`](ARCHITECTURE.md) — system architecture
- [`PRD.md`](PRD.md) — product requirements
- [`SPECS.md`](SPECS.md) — technical specs
- [`SECURITY.md`](SECURITY.md) — security model

## License

Proprietary — © Promithic. All rights reserved.
