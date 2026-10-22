# Portale Commerciale Infront

Portale web per gli agenti commerciali di Infront Italy: configurazione delle proposte di sponsorizzazione sportiva, approvazione e generazione del documento commerciale in PDF.

- Regole di sviluppo: [`CLAUDE.md`](CLAUDE.md)
- Specifiche, decisioni e piano: [`docs/`](docs/README.md)

## Avvio in locale (Laravel Sail)

Per il collaudo su una VM VirtualBox in Windows segui la guida passo passo [`docs/dev/ambiente-locale.md`](docs/dev/ambiente-locale.md) (D-261).

In breve, con Docker, PHP 8.4 e Composer già installati:

```bash
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm ci
./vendor/bin/sail npm run dev
```

Il portale risponde su http://localhost; Mailpit su http://localhost:8025.

## Controlli

```bash
./vendor/bin/sail artisan test          # Pest (MariaDB 11.8)
./vendor/bin/sail composer analyse      # Larastan
./vendor/bin/sail composer format       # Pint
./vendor/bin/sail npm run typecheck     # vue-tsc
./vendor/bin/sail npm run lint          # ESLint
```

Codice proprietario: uso riservato a Infront Italy e bitAND solution.
