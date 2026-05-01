# AI-Driven Subscription & Expense Auditor

A self-hosted personal-finance app that ingests Polish bank CSV/XLS statements,
auto-categorizes transactions with an LLM, and surfaces recurring subscriptions
(including likely duplicates you may be paying twice for).

Built as a portfolio project — the focus is on clean architecture, testability,
and shipping a fully working flow end-to-end.

> _Screenshots placeholder — drop final renders in `docs/img/` and link them here:_
> - `docs/img/dashboard.png` — dashboard with insights, area chart, donut, top subs
> - `docs/img/subscriptions.png` — subscription list with duplicate flagging
> - `docs/img/import.gif` — drag-and-drop CSV → categorization → dashboard

---

## Features

- **Multi-bank CSV/XLS import** — parsers for **mBank, PKO BP, ING, Santander, BGŻ BNP Paribas**.
  Bank auto-detected from the file headers; manual bank dropdown as fallback.
- **Idempotent imports** — re-uploading the same statement inserts zero duplicate rows
  thanks to a deterministic per-row hash (`sha256(user_id|posted_at|amount|description|balance)`).
- **AI categorization with cost controls** — Groq (Llama 3.3 70B) via OpenAI-compatible
  HTTP, batched 20 transactions per prompt, JSON-schema validated to block hallucinated
  slugs. Redis cache keyed on a normalized merchant fingerprint with prompt-version
  invalidation, 30-day TTL.
- **Rule-based subscription detection** — groups expenses by normalized merchant name,
  promotes a group to a `Subscription` when it shows ≥2 charges 25–35 days apart
  with consistent amounts (±10%).
- **Duplicate-subscription detection** — flags subscriptions that share a meaningful
  token, billing cycle, and amount within ±15% (e.g. `NETFLIX.COM` vs `NETFLIX EU`)
  so the headline monthly total isn't double-counted.
- **Dashboard analytics** — 90-day spending area chart, category-breakdown donut,
  top-5 subscriptions widget, AI insights alert (duplicates + spending spikes).
- **Async pipeline** — every CSV is parsed in a queue job, then a `Bus::batch` of
  categorize jobs, then `DetectSubscriptionsJob` runs once the batch finishes.
- **PII at rest is encrypted** — `transactions.description` and `counterparty` use
  Laravel's `encrypted` cast (AES-256 via `APP_KEY`).

## Stack

- **Backend:** Laravel 13, PHP 8.5, PostgreSQL 18, Redis 8
- **Frontend:** Inertia.js + React 18 + TypeScript, Tailwind CSS, Recharts, Framer Motion, Lucide
- **AI:** Groq API (OpenAI-compatible chat completions, `llama-3.3-70b-versatile`)
  with a swappable `FakeAiCategorizer` for offline dev/CI
- **Infra:** Laravel Sail (php-fpm 8.5 / pgsql / redis / mailpit / queue worker), Docker Compose
- **Quality gates:** Pest (126 tests), Larastan level 8, Pint, ESLint, TypeScript strict mode

## Quickstart

Requirements: Docker, Docker Compose. No need for a local PHP/Node toolchain to run.

```bash
git clone https://github.com/daniel-ciupek/AI_subscription_and_expense_auditor.git
cd AI_subscription_and_expense_auditor
cp .env.example .env

# Install PHP deps (one-off, uses a tiny throwaway container if you don't have PHP locally)
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    composer:latest composer install --ignore-platform-reqs --no-scripts

# Bring the stack up
./vendor/bin/sail up -d

# Generate APP_KEY, run migrations, seed demo data
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed --class=DemoSeeder

# Frontend dev (HMR)
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

Open <http://localhost> and log in with:

- **Email:** `demo@example.com`
- **Password:** `demo1234`

The demo account is pre-loaded with ~140 realistic transactions across 120 days,
6 detected subscriptions (one is intentionally a near-duplicate to demonstrate
the alerting path), categorized using the deterministic `FakeAiCategorizer`.

## Switching to real AI categorization

By default the app runs `AI_DRIVER=fake` so it works without paid credentials.
To use Groq's Llama 3.3 70B:

1. Grab a key from <https://console.groq.com>.
2. Set in `.env`:
   ```env
   AI_DRIVER=groq
   GROQ_API_KEY=gsk_...
   ```
3. Restart the queue worker so the binding picks up: `./vendor/bin/sail restart queue`.

Subsequent imports will use Groq; previously-cached categorizations stay valid
since the cache key includes the prompt version.

## Project structure

```
app/
├── Actions/                 — single-responsibility business actions
│   ├── ImportCsvAction.php
│   └── DetectSubscriptionsAction.php   # rule-based + duplicate flagging
├── Contracts/               — interfaces enabling Strategy pattern
│   ├── AiCategorizerInterface.php
│   └── CsvParserInterface.php
├── Http/Controllers/        — thin: validate, authorize, delegate, render
├── Jobs/                    — async pipeline
│   ├── ProcessImportJob.php
│   ├── CategorizeTransactionsJob.php   # batched + Redis-cached
│   └── DetectSubscriptionsJob.php      # runs after categorize batch
├── Services/
│   ├── AiCategorizers/      — FakeAiCategorizer, GroqAiCategorizer
│   ├── Parsers/             — 5 bank parsers + StatementReader (CSV/XLS/ODS)
│   └── BankDetector.php     # auto-detect from headers
└── Support/
    ├── TransactionNormalizer.php       # merchant fingerprinting
    └── SubscriptionMonthlyCost.php

resources/js/
├── Components/Dashboard/    — Recharts widgets (donut, area, top subs, alerts)
├── Pages/                   — Inertia pages
└── Layouts/

database/
├── migrations/              — schema (users, imports, transactions, categories, subscriptions, ai_categorizations)
└── seeders/
    ├── CategorySeeder.php
    └── DemoSeeder.php       # idempotent demo dataset
```

## Architecture highlights

### Strategy pattern × 2

- **`AiCategorizerInterface`** has two implementations: `FakeAiCategorizer`
  (deterministic keyword matching, used in tests + `AI_DRIVER=fake`) and
  `GroqAiCategorizer` (HTTP Groq client, retries with exponential backoff,
  schema-validated JSON, falls back to `other` rather than letting hallucinated
  slugs reach the database).
- **`CsvParserInterface`** has one implementation per supported bank.
  `BankDetector` matches the file's header row against each parser's
  signature; on a tie/miss the import form's bank dropdown is the fallback.

### Async pipeline

```
ProcessImportJob (parse + persist) ─┐
                                    │
  Bus::batch([                      │   each chunk = up to 20 transaction IDs
    CategorizeTransactionsJob,      ◄── per-tx Redis cache (sha256 of normalized
    CategorizeTransactionsJob,      │   description + amount sign), 30-day TTL,
    ...                             │   invalidated automatically on prompt
  ])->then(DetectSubscriptionsJob)  │   version bump
```

### Idempotency at every boundary

- **Imports:** `firstOrCreate` keyed on `(user_id, hash)` — re-uploading the
  same statement inserts zero new rows. The post-import pipeline only
  dispatches if at least one row was actually inserted.
- **Detection:** `updateOrCreate` keyed on `(user_id, name, billing_cycle_days)`.
  Re-running the detector never duplicates a subscription.
- **Demo seeder:** wipes the demo user's data first so re-running is safe.

### Cost control on AI calls

- **Cache** — normalize description (lowercase, strip digits/punctuation,
  collapse whitespace), hash it with the amount sign. Recurring merchants
  hit cache after the first occurrence.
- **Batching** — 20 transactions per prompt cuts cost ~20×.
- **Versioning** — `ai_prompt_version` is stored on the cached value AND on
  the audit row. Bumping the prompt invalidates stale cache entries without
  flushing Redis.
- **Schema validation** — Laravel `Validator` with an `in:` slug allow-list
  on the LLM response. Schema violation → fall back to `other`, never let
  invented categories reach the dashboard.

## Testing

```bash
./vendor/bin/pest                  # 126 feature + unit tests
./vendor/bin/phpstan analyse       # Larastan level 8
./vendor/bin/pint --test           # Style check
npm run typecheck                  # TypeScript strict
```

Pest runs against in-memory SQLite locally (~5s) and PostgreSQL inside Sail
for parity with production.

## Banks supported

| Bank             | Header signature snippet                            | Real-data tested |
|------------------|-----------------------------------------------------|------------------|
| mBank            | `#data operacji`                                    | Synthetic fixtures |
| PKO BP           | `typ transakcji + opis transakcji`                  | Synthetic fixtures |
| ING              | `dane kontrahenta`                                  | Synthetic fixtures |
| Santander        | `opis nadawcy/odbiorcy`                             | Synthetic fixtures |
| BGŻ BNP Paribas  | `data zaksięgowania + numer rachunku kontrahenta`   | ✅ Author's account |

Adding a sixth bank = one `CsvParserInterface` implementation + a header
signature + a fixture-driven test. No other code changes.

## License

MIT — see `LICENSE`.

## Author

Built by [Daniel Ciupek](https://github.com/daniel-ciupek).
