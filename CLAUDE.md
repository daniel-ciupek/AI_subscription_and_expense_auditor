# Projekt: AI-Driven Subscription & Expense Auditor

## 🎯 Cel Projektu
Aplikacja webowa służąca do importowania wyciągów bankowych (CSV) i automatycznego wykrywania, kategoryzowania oraz podsumowywania subskrypcji użytkownika przy pomocy sztucznej inteligencji. Projekt jest budowany do portfolio, więc priorytetem jest innowacyjność, czysty kod, testowalność, dobre wzorce projektowe i niezawodne działanie w kontenerach.

## 🛠 Tech Stack (Zawsze najnowsze wersje)
- **Backend:** Najnowsza stabilna wersja Laravel (12.x / 13.x) oraz PHP (min. 8.3+)
- **Frontend:** Inertia.js + najnowszy Vue.js (Composition API) / React + Tailwind CSS
- **Baza danych:** PostgreSQL (główna) / SQLite (do testów)
- **Kolejki/Cache:** Redis
- **AI API:** Groq (modele Llama 3/4) ze 100% kompatybilnością z formatem zapytań OpenAI (via zintegrowane interfejsy).

## 🐳 Środowisko Docker
- Projekt musi być w pełni "z konteneryzowany".
- Użyj Laravel Sail lub natywnego pliku `docker-compose.yml`, aby środowisko deweloperskie (PHP, PostgreSQL, Redis, Node.js) dało się uruchomić jednym poleceniem (`docker compose up -d`).
- **Zawsze na starcie projektu oraz przed modyfikacjami infrastruktury**, weryfikuj i aktualizuj zawartość pliku `.dockerignore`, aby nie budować obrazów z niepotrzebnymi plikami (np. `vendor/`, `node_modules/`, `.git/`).

## 📦 Kontrola wersji (Git)
- **Bezwzględny zakaz oznaczania commitów podpisem AI:** Żaden commit message ani metadata nie może zawierać informacji, że kod został wygenerowany lub wysłany przez "Claude Code", "AI", "Claude", itp. Używaj wyłącznie naturalnych, ludzkich komunikatów w stylu Conventional Commits (np. `feat: add docker environment setup`, `fix: queue worker timeout`).
- **Weryfikacja ignorowanych plików:** Przed KAZDYM commitem (oraz na samym początku inicjalizacji projektu) AI ma obowiązek przeanalizować plik `.gitignore`. Upewnij się, że pliki środowiskowe (`.env`), skompilowane assety, klucze API i katalogi zależności nie trafią do repozytorium.

## 🏗 Struktura i Spójność Aplikacji
- **Przemyślany design:** Cała struktura strony (routing, układ widoków, nawigacja) musi być zaplanowana z góry. Aplikacja ma działać w 100%, bez "ślepych zaułków", niedokończonych stron czy niedziałających przycisków.
- **Komponenty UI:** Twórz reużywalne i spójne wizualnie komponenty (przyciski, tabele, modale, powiadomienia flash) zgodnie z najlepszymi praktykami Tailwind CSS.
- **Obsługa błędów:** System musi elegancko obsługiwać błędy (np. zły format CSV, brak połączenia z AI, timeouty) i informować o tym użytkownika w przyjazny sposób (UI/UX).

## 🏛 Architektura i Wzorce Projektowe
1. **Brak "Grubych Kontrolerów" (Fat Controllers):**
   - Kontrolery służą tylko do odbierania żądań HTTP, autoryzacji, walidacji (Form Requests) i zwracania odpowiedzi.
   - Cała logika biznesowa musi być delegowana do klas `Action` lub `Service`.

2. **Wzorzec Strategii dla AI (Dependency Injection):**
   - Aplikacja musi komunikować się z AI poprzez interfejs `App\Contracts\AiCategorizerInterface`.
   - Implementacje: `FakeAiCategorizer` (statyczny JSON po `sleep()`) oraz `OpenAiCategorizer` (API Groq/OpenAI z użyciem HTTP Client). Sterowane zmienną `AI_DRIVER` w `.env`.

3. **Asynchroniczność (Jobs & Queues):**
   - Parsowanie CSV i zapytania AI **nigdy** nie blokują głównego wątku HTTP.
   - Używaj `Jobs` (np. `ProcessTransactionsJob`) by procesować zadania w tle.

4. **Walidacja danych z AI (Ochrona przed Halucynacjami):**
   - Zawsze wymuszaj format JSON (`response_format: json_object`).
   - Dokładnie waliduj strukturę zwróconego JSON-a przed zapisem do bazy.

## 🧪 Testowanie (TDD)
- Piszemy testy w środowisku **Pest PHP**.
- Architektura musi zakładać pełne pokrycie testami dla parserów CSV, serwisów AI oraz kluczowych endpointów.
- Testy muszą działać w kontenerze Dockera.