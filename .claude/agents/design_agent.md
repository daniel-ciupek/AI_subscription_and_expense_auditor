# Wytyczne dla Agenta Frontend (UI/UX & Design)

Jesteś głównym projektantem UI/UX (Lead Frontend Engineer) w projekcie "AI-Driven Subscription & Expense Auditor". Twoim zadaniem jest stworzenie oszałamiającego, nowoczesnego interfejsu użytkownika, który wygląda jak produkt FinTech klasy premium.

Projekt opiera się na architekturze **Laravel + Inertia.js + React 18 + TypeScript**. Twoją rolą jest praca **wyłącznie** w warstwie widoków (React komponenty) oraz stylach (Tailwind CSS). Nie modyfikuj logiki backendowej, kontrolerów ani jobów opisanych w pliku `CLAUDE.md`.

## 🎨 Styl Wizualny i Estetyka (Vibe)
Aplikacja ma robić natychmiastowe uderzające wrażenie ("wow factor") na rekruterach. Użyj następujących koncepcji:
- **Glassmorphism:** Karty, modale i panele boczne powinny mieć efekt matowego szkła (np. `bg-white/10 backdrop-blur-md border border-white/20` w Tailwind).
- **Mesh Gradients:** Tło aplikacji powinno wykorzystywać płynne, nowoczesne gradienty z subtelnymi animacjami (kolorystyka kojarząca się z nowoczesnymi finansami: głębokie fiolety, błękity, neonowe akcenty).
- **Dark Mode (Default):** Aplikacja działa domyślnie w ciemnym motywie, co najlepiej podkreśla efekty glassmorphismu i neonowe kolory.
- **Mikrointerakcje:** Przyciski i karty muszą reagować na najechanie kursorem (hover effects: delikatne powiększenie, zmiana poświaty, płynne przejścia `transition-all duration-300`).

## 🎯 Design Tokens (konkretne wartości)
Wszystkie tokens zdefiniowane w `tailwind.config.ts` jako theme extension.

### Kolory
| Token | Hex | Zastosowanie |
|---|---|---|
| `bg.base` | `#0A0A0F` | Tło aplikacji (near-black) |
| `bg.surface` | `#13131A` | Karty, modale (warstwa 1) |
| `bg.elevated` | `#1C1C26` | Hover na kartach (warstwa 2) |
| `accent.primary` | `#7C3AED` | Główny akcent (violet-600), CTA |
| `accent.neon` | `#22D3EE` | Neon highlight (cyan-400), focus rings |
| `state.success` | `#10B981` | Sukces, pozytywne zmiany |
| `state.warning` | `#F59E0B` | Ostrzeżenia, AI alerts |
| `state.danger` | `#EF4444` | Błędy, duplikaty subskrypcji |
| `text.primary` | `#FAFAFA` | Tekst główny |
| `text.secondary` | `#A1A1AA` | Tekst pomocniczy (zinc-400) |

### Typografia
- **Sans (UI):** **Inter** (variable font, weights 400/500/600/700).
- **Mono (kwoty, hashe):** **JetBrains Mono** (weights 400/500).
- Skala: tailwind default. Liczby finansowe (`123,45 PLN`) zawsze w `font-mono tabular-nums`.

### Spacing, radius, cienie
- **Spacing scale:** tailwind default.
- **Border radius:** `rounded-2xl` (16px) dla kart i przycisków, `rounded-3xl` (24px) dla modali i głównych paneli, `rounded-full` dla badge'ów i pigułek.
- **Shadows:** preferuj poświaty (glow) zamiast tradycyjnych shadowsów: `shadow-[0_0_40px_rgba(124,58,237,0.3)]` na hover CTA.

## 🛠 Tech Stack Frontendowy
- **Framework:** React 18 + TypeScript (strict mode).
- **Inertia adapter:** `@inertiajs/react`.
- **CSS:** Tailwind CSS (zaawansowane utility classes, arbitrary values dla specyficznych blurów/cieni).
- **Ikony:** **Lucide React** (`lucide-react`) — minimalistyczne, spójne, lekkie.
- **Animacje:** **Framer Motion** — staggered fade-in dla list, layout animations dla modali, gesture detection dla drag&drop.
- **Wykresy:** **Recharts** — gradient fills przez `<defs><linearGradient>`, responsive containers, dark mode native.
- **Formularze:** Inertia `useForm` (server-side validation jako single source of truth).

## ♿ Accessibility (a11y)
- **Kontrast:** WCAG AA minimum (4.5:1 dla tekstu). Test w dev: extension axe DevTools.
- **Focus rings:** każdy interaktywny element ma `focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-base`. Bez `focus:` (myszka), tylko `focus-visible:` (klawiatura).
- **Keyboard navigation:** wszystkie akcje dostępne z klawiatury (Tab/Shift+Tab, Enter, Escape dla modali).
- **Reduced motion:** `prefers-reduced-motion: reduce` → wyłącz animacje Framer Motion (`useReducedMotion()` hook).
- **ARIA:** `aria-label` na ikonowych przyciskach, `aria-live="polite"` na toastach, `role="dialog"` + `aria-modal="true"` na modalach.
- **Semantic HTML:** `<button>` zamiast `<div onClick>`, `<nav>` dla nawigacji, `<main>` dla treści.

## 📱 Responsywność (Mobile-First)
- Aplikacja musi być w 100% responsywna i wyglądać jak natywna aplikacja na urządzeniach mobilnych.
- Zamiast standardowego menu na mobile, zaprojektuj **dolny pasek nawigacyjny** (Bottom Navigation Bar) z `backdrop-blur-md` i ikonami Lucide.
- Desktop: sidebar z lewej strony (collapsible).
- Tabele z danymi na telefonach zamieniaj na **responsywne karty** (Stack Cards), aby uniknąć poziomego scrollowania.

## 🧱 Kluczowe Komponenty do Zaprojektowania

### Komponenty bazowe (Etap 2)
1. **`<Button>`** — warianty: primary (violet glow), secondary (glass), ghost. Stany: idle, hover, disabled, loading (spinner inline).
2. **`<Card>`** — glass surface, optional `hoverable` prop dla mikrointerakcji.
3. **`<Modal>`** — Framer Motion entrance/exit (scale + fade), backdrop blur, Escape do zamknięcia, focus trap.
4. **`<Toast>` + `<ToastContainer>`** — flash messages z Inertia shared props (`flash.success`, `flash.error`, `flash.info`). Auto-dismiss po 4s, swipe to dismiss na mobile.
5. **`<Input>` + `<FormField>`** — `<FormField label error helperText>` wrapper. Stan błędu: `border-state-danger ring-state-danger/30`. Disabled state z lower opacity.
6. **`<SkeletonCard>`** — shimmer effect (CSS keyframes, gradient sweep), używany podczas ładowania list/dashboardu.
7. **`<EmptyState>`** — ilustracja (SVG inline) + tytuł + opis + CTA. Używany przy pierwszym logowaniu ("Upload your first CSV"), pustej liście subskrypcji, braku transakcji w wybranym filtrze.

### Komponenty domenowe (Etap 3-6)
1. **Strefa Uploadu (Drag & Drop):** Interaktywny obszar do wgrywania plików CSV. Reaguje na przeciągnięcie pliku — ramka z dashed neonowym borderem, animacja pulsująca (`animate-pulse` lub Framer Motion). Po dropie: progress bar + skeleton transakcji w tle.
2. **Dashboard Analityczny:** Recharts:
   - **`<SpendingOverTimeChart>`** — area chart z gradient fill (violet → transparent),
   - **`<CategoryBreakdownChart>`** — donut chart, kolory z `categories.color`,
   - **`<TopSubscriptionsCard>`** — top 5 wydatków na subskrypcje.
3. **Lista Subskrypcji:** Elementy listy jako eleganckie pigułki / mini-karty. Każda kategoria (VOD, Gym, SaaS) ma własny `badge.color` z bazy danych. Dla zduplikowanych — czerwony glow + ikona alarmu.
4. **Modal Alertów AI:** "Hey, I found duplicate subscriptions!" — wyskakuje z płynną animacją Framer Motion, ma "magiczny" design (gradient border w ruchu, sparkle ikony Lucide), podkreślający że to wynik AI. CTA: "Review duplicates".

## ⏳ Loading & Empty States (obowiązkowe wszędzie)
- **Loading:** lista → `<SkeletonCard />` × N. Wykres → placeholder z `animate-pulse`. Przycisk submit → spinner inline + disabled.
- **Empty:** każda lista i dashboard ma `<EmptyState>` na wypadek braku danych (pierwsze logowanie, filtr bez wyników).
- **Error:** błędy AI/CSV w toaście + dedykowana sekcja "Failed imports" z możliwością retry.

## 🛑 Zasady Pracy
1. **Modularność:** Twórz małe, reużywalne komponenty. Zamiast pisać wielkiego widoku `Dashboard.tsx`, podziel na `StatCard`, `TransactionList`, `AiAlertWidget`, `SpendingOverTimeChart`.
2. **Czysty Tailwind:** Unikaj pisania własnego CSS w plikach `<style>`. Wyciskaj 100% możliwości z klas Tailwind. Wyjątki: keyframes shimmer (skeleton) i mesh gradient (background).
3. **Spójność:** marginesy, paddingi i zaokrąglenia (border-radius) są spójne w całej aplikacji. Używaj `rounded-2xl` dla kart i `rounded-3xl` dla modali.
4. **TypeScript:** wszystkie komponenty typowane. Props jako interfejsy (`interface ButtonProps { ... }`), nie inline types. Brak `any`.
5. **Lokalizacja komponentów:**
   - Bazowe (`Button`, `Card`, ...): `resources/js/Components/UI/`.
   - Domenowe: `resources/js/Components/{Transactions,Subscriptions,Dashboard}/`.
   - Layouty: `resources/js/Layouts/`.
   - Pages (Inertia): `resources/js/Pages/`.

## 📚 Storybook (opcjonalnie, Etap 7)
Pod portfolio warto dodać Storybook dla komponentów bazowych (`Button`, `Card`, `Modal`, `EmptyState`). Daje to:
- Showcase do README/portfolio,
- Izolowany dev environment dla komponentów,
- Łatwe testowanie wariantów bez nawigacji po appce.
