# Projekt: AI-Driven Subscription & Expense Auditor

## 🎯 Cel Projektu
Aplikacja webowa służąca do importowania wyciągów bankowych (CSV) i automatycznego wykrywania, kategoryzowania oraz podsumowywania subskrypcji użytkownika przy pomocy sztucznej inteligencji. Projekt jest budowany do portfolio, więc priorytetem jest innowacyjność, czysty kod, testowalność, dobre wzorce projektowe i niezawodne działanie w kontenerach.

Język interfejsu: **angielski** (przyciski, walidacje, komunikaty). Dane bankowe (opisy transakcji) zostają w oryginale (PL).

## 🛠 Tech Stack (Zawsze najnowsze wersje)
- **Backend:** Najnowsza stabilna wersja Laravel (12.x / 13.x) oraz PHP (min. 8.3+).
- **Frontend:** **Inertia.js + React 18 + TypeScript** + Tailwind CSS.
  - **Wykresy:** Recharts (gradient fills przez `<defs><linearGradient>`).
  - **Animacje:** Framer Motion (mikrointerakcje, staggered fade-in, modale).
  - **Ikony:** Lucide React.
- **Baza danych:** PostgreSQL 16 (główna) / SQLite (do testów Pest).
- **Kolejki/Cache:** Redis 7.
- **AI API:** Groq (Llama 3/4) lub DeepSeek (`deepseek-chat`) — oba w 100% kompatybilne z formatem zapytań OpenAI (via Laravel HTTP Client).

## 🔐 Autoryzacja i Uwierzytelnianie
- **Auth scaffold:** Laravel Breeze + Inertia + React + TypeScript (`php artisan breeze:install react --typescript`).
- **Polityki autoryzacji:** każdy zasób (`Transaction`, `Import`, `Subscription`) ma swoją Policy. Użytkownik widzi i edytuje **wyłącznie własne** dane (`where user_id = auth()->id()`).
- **Global scope:** `BelongsToUser` global scope na modelach Eloquent dla bezpieczeństwa "by default".
- **Brak wieloużytkownikowych zespołów / współdzielenia danych** na MVP — to konta osobiste.

## 🐳 Środowisko Docker
- Projekt musi być w pełni skonteneryzowany. Używamy **Laravel Sail** (`compose.yaml` w roocie).
- Stack: `laravel.test` (php-fpm 8.5), `pgsql` (Postgres 18-alpine), `redis`, `mailpit`.
- Migracje uruchamiane automatycznie w entrypoint php-fpm: `php artisan migrate --force`.
- **Zawsze na starcie projektu oraz przed modyfikacjami infrastruktury**, weryfikuj i aktualizuj zawartość pliku `.dockerignore`, aby nie budować obrazów z niepotrzebnymi plikami (np. `vendor/`, `node_modules/`, `.git/`).

### 🔄 Lifecycle Saila (uruchamianie / zatrzymywanie)
Sail zjada kilkaset MB RAM bezczynnie — startujemy go **tylko kiedy potrzebny** i zatrzymujemy po sesji.

**Kiedy odpalać `./vendor/bin/sail up -d` (lub `sail up -d` z aliasem):**
- Przed uruchomieniem migracji, `sail artisan ...`, `sail tinker`.
- Przed uruchomieniem testów Pest na PostgreSQL (`sail test`).
- Przed weryfikacją UI w przeglądarce (http://localhost).
- Przed `sail npm run dev` (HMR Vite).
- Po zmianach w `compose.yaml`, Dockerfile lub usługach infra.

**Kiedy NIE potrzebujesz Saila:**
- Sam edytujesz pliki PHP/TS/CSS — bez testów / DB.
- Pest na sqlite in-memory (`vendor/bin/pest` na hoście — używa `pdo_sqlite`).
- Larastan, Pint, Rector — wszystkie działają na hoście bez kontenera.
- `npm run build` — Node 20 lokalnie wystarcza.

**Zatrzymywanie:**
- `./vendor/bin/sail down` — zatrzymuje kontenery (volumes zostają).
- `./vendor/bin/sail down -v` — usuwa też volumes (pełen reset DB) — używaj świadomie.

**Konwencja agenta (Claude):**
1. Przed komendą wymagającą Sail (np. `sail test`, `sail artisan migrate`, sprawdzenie UI w przeglądarce) — najpierw sprawdź `docker ps`, zobacz czy kontenery działają. Jeśli nie — `sail up -d` i zaczekaj na healthcheck pgsql.
2. Po skończeniu większego bloku pracy wymagającego Saila (np. zakończony etap, koniec sesji, koniec serii testów end-to-end) — zaproponuj użytkownikowi `sail down`, nie zatrzymuj sam bez pytania (może mieć inny window otwarty).
3. Nie odpalaj Saila "na zapas" — koszt RAM jest realny.

## 📦 Kontrola wersji (Git)
- **Bezwzględny zakaz oznaczania commitów podpisem AI:** Żaden commit message ani metadata nie może zawierać informacji, że kod został wygenerowany lub wysłany przez "Claude Code", "AI", "Claude", itp. Używaj wyłącznie naturalnych, ludzkich komunikatów w stylu Conventional Commits.
- **Dozwolone typy commitów:** `feat`, `fix`, `chore`, `refactor`, `test`, `docs`, `ci`, `perf`, `style`. Przykłady: `feat: add mbank csv parser`, `fix: queue worker timeout`, `ci: run pest on pull request`.
- **Weryfikacja ignorowanych plików:** Przed KAŻDYM commitem (oraz na samym początku inicjalizacji projektu) przeanalizuj plik `.gitignore`. Upewnij się, że pliki środowiskowe (`.env`), skompilowane assety, klucze API i katalogi zależności nie trafią do repozytorium.

## ⚙️ CI/CD
- **GitHub Actions** uruchamiane na każdy PR do `main`:
  - `lint.yml` — Pint (`vendor/bin/pint --test`) + Larastan (level 8) + ESLint/TS check.
  - `test.yml` — Pest (`php artisan test`) na PostgreSQL service.
- PR nie może być zmergowany przy czerwonym CI.

## 🏗 Struktura i Spójność Aplikacji
- **Przemyślany design:** Cała struktura strony (routing, układ widoków, nawigacja) musi być zaplanowana z góry. Aplikacja ma działać w 100%, bez "ślepych zaułków", niedokończonych stron czy niedziałających przycisków.
- **Komponenty UI:** Twórz reużywalne i spójne wizualnie komponenty (przyciski, tabele, modale, powiadomienia flash) zgodnie z najlepszymi praktykami Tailwind CSS. Szczegóły wizualne — patrz `.claude/agents/design_agent.md`.
- **Obsługa błędów:** System musi elegancko obsługiwać błędy (zły format CSV, brak połączenia z AI, timeouty) i informować o tym użytkownika w przyjazny sposób (toast + sekcja "Failed imports" w UI).
- **Polityka czasu odpowiedzi:** każdy request HTTP < 300ms. Operacje cięższe (parsowanie CSV, AI) **wyłącznie** w Jobs.

## 🏛 Architektura i Wzorce Projektowe

### 1. Brak "Grubych Kontrolerów" (Fat Controllers)
- Kontrolery służą tylko do odbierania żądań HTTP, autoryzacji, walidacji (Form Requests) i zwracania odpowiedzi.
- Cała logika biznesowa musi być delegowana do klas `Action` lub `Service`.
- **Konwencja Action:** `App\Actions\ImportCsvAction` z metodą `public function handle(...)`.
- **Konwencja Form Request:** `App\Http\Requests\ImportCsvRequest`.

### 2. Wzorzec Strategii dla AI (Dependency Injection)
- Aplikacja komunikuje się z AI poprzez interfejs `App\Contracts\AiCategorizerInterface`.
- Implementacje:
  - `FakeAiCategorizer` — statyczny JSON po `sleep()`, używany w testach i w lokalnym dev bez klucza.
  - `GroqAiCategorizer` — API Groq z Laravel HTTP Client.
  - `DeepseekAiCategorizer` — API DeepSeek (`deepseek-chat`), OpenAI-compatible.
- Sterowane zmienną `AI_DRIVER=fake|groq|deepseek` w `.env`. Binding w `AppServiceProvider`.

### 3. Wzorzec Strategii dla parserów CSV (5 banków)
- Aplikacja obsługuje CSV z **pięciu polskich banków**: mBank, PKO BP, ING, Santander, BGŻ BNP Paribas.
- Każdy bank to osobna implementacja `App\Contracts\CsvParserInterface`:
  - `MBankCsvParser`, `PkoBpCsvParser`, `IngCsvParser`, `SantanderCsvParser`, `BgzBnpParibasCsvParser`.
- `App\Services\BankDetector` automatycznie rozpoznaje bank po nagłówkach CSV; w UI dropdown "Bank" jako fallback gdy detekcja zawiedzie.
- **Realne testy** wykonujemy na danych z BGŻ BNP Paribas (konto użytkownika); pozostałe parsery testowane na zsyntetyzowanych fixture'ach na podstawie publicznej dokumentacji formatu.

### 4. Asynchroniczność (Jobs & Queues)
- Parsowanie CSV i zapytania AI **nigdy** nie blokują głównego wątku HTTP.
- Pipeline: `ProcessImportJob` (parser) → `CategorizeTransactionsJob` (AI, chunked) → `DetectSubscriptionsJob` (rule + AI fallback).
- Queue connection: Redis. Worker w osobnym kontenerze Dockera (`php artisan queue:work`).

### 5. Walidacja danych z AI (Ochrona przed Halucynacjami)
- Zawsze wymuszaj format JSON (`response_format: json_object` w Groq API).
- Walidacja struktury zwróconego JSON-a przed zapisem do bazy (Laravel `Validator` lub dedykowany schema validator).
- Jeśli AI zwróci niezgodny JSON → log + retry (max 3 z exponential backoff) → fallback do kategorii `Uncategorized`.

### 6. Kontrola kosztów AI (cache + batching)
- **Cache odpowiedzi:** Redis, klucz = `sha256(normalized_description + amount_sign)`, TTL 30 dni. Powtarzalne opisy ("NETFLIX SUBSCRIPTION") nie generują kolejnego zapytania.
- **Batching:** 20 transakcji w jednym promptcie do Groq (jedno zapytanie zwraca tablicę kategoryzacji).
- **Retry:** exponential backoff (1s, 2s, 4s), max 3 próby na chunk.
- **Wersjonowanie promptów:** kolumna `ai_prompt_version` w tabeli `ai_categorizations`. Zmiana promptu → możliwość re-runu kategoryzacji bez czyszczenia historii.

## 🗄 Model Danych (ERD)
Tabele kluczowe (szczegóły w migracjach Etap 3):
- **`users`** — Breeze default (id, name, email, password, timestamps).
- **`imports`** — `id, user_id, bank, original_filename, status (pending/processing/done/failed), failed_reason, transactions_count, created_at, deleted_at` (soft delete 30 dni).
- **`transactions`** — `id, user_id, import_id, posted_at, amount, currency, description (encrypted), counterparty (encrypted), balance, hash (UNIQUE per user), category_id, created_at`.
  - **Idempotencja:** `hash = sha256(user_id|posted_at|amount|description|balance)`. Re-upload tego samego CSV daje 0 nowych rekordów.
- **`categories`** — `id, name, slug, color, icon` (seedowane: Subscriptions, Food, Transport, Entertainment, Bills, Salary, Other).
- **`subscriptions`** — `id, user_id, name, category_id, amount, currency, billing_cycle_days, last_charge_at, next_expected_charge_at, is_duplicate_of_id, created_at`.
- **`ai_categorizations`** — `id, transaction_id, category_id, confidence, ai_prompt_version, raw_response, created_at` (audit trail kategoryzacji AI).

## 🔒 Bezpieczeństwo i PII
- **Szyfrowanie pól:** `transactions.description` i `transactions.counterparty` szyfrowane przez Eloquent Cast `encrypted` (AES-256 przez `APP_KEY`).
- **Soft delete na importach:** `imports.deleted_at` z TTL 30 dni; cleanup job (`PruneDeletedImportsJob`) na cron daily.
- **Klucze API:** Groq API key tylko w `.env` (nigdy w kodzie/repo). `.env.example` zawiera placeholder.
- **HTTPS-only w produkcji:** middleware `TrustProxies`, `secure_url()`.
- **Rate limiting:** Laravel throttle middleware na endpointach upload (10 req/min/user) i auth (5 req/min/IP).

## 📊 Observability
- **Telescope** w environment `local` (`/telescope`).
- **Log channels:** stack (daily file + stderr w Dockerze).
- Wyjątki w Jobs raportowane do log channel `queue` z pełnym stack trace.

## 🌱 Demo i Seed
- `php artisan db:seed --class=DemoSeeder` tworzy:
  - Konto demo: `demo@example.com` / hasło `demo1234`.
  - 200 zaimportowanych, skategoryzowanych transakcji.
  - 6 wykrytych subskrypcji (w tym 1 zduplikowana — żeby pokazać AI Alert).
- Demo CSV (zanonimizowany): `storage/app/demo/bgz-sample.csv`.

## 🧪 Testowanie (TDD)
- Piszemy testy w środowisku **Pest PHP**.
- Pełne pokrycie dla:
  - parserów CSV per bank (snapshot testy z fixture'ów),
  - `AiCategorizerInterface` (z `FakeAiCategorizer` + `Http::fake()` dla Groq),
  - kluczowych endpointów (upload, dashboard).
- Testy muszą działać w kontenerze Dockera (`docker compose exec app php artisan test`).
- Cel pokrycia: > 80% linii w `app/Actions`, `app/Services`, `app/Jobs`.

## 🧹 Jakość kodu
- **Pint** — formatter (`vendor/bin/pint`), uruchamiany w pre-commit hook (Husky lub `lint-staged`).
- **Larastan** — static analysis level 8.
- **Rector** — automatyczny upgrade i refactoring (uruchamiany ręcznie, nie w CI).
- **ESLint + Prettier** — frontend, z `eslint-plugin-react-hooks` i `eslint-plugin-jsx-a11y`.
- **TypeScript strict mode** (`"strict": true` w `tsconfig.json`).
