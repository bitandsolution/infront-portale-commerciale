# Collaudo della milestone A — Dati di base (F0, S1, F1, F2)

Stato: 💡 proposta (D-353, 4/11/2026) · **A1 eseguito il 7/11/2026 sul `main` di chiusura di F3 (`d6f7858`): tutti i passi ✅, nessuna PR `fix/…`** (esito al §7). Riferimenti: pianificazione §6.1.4, `docs/dev/pr-f0.md`, `pr-f1.md`, `pr-f2.md`, `docs/dev/ambiente-locale.md`.

Ogni PR delle fette F0, S1, F1 e F2 è già stata collaudata da sola. La milestone A prova invece il **percorso completo**, nell'ordine in cui l'admin caricherà i dati della stagione 2027-28 in produzione con il rilascio interno **R0** (D-235). La stessa checklist, senza i passi segnati "solo VM", è la traccia del caricamento di R0.

La milestone si svolge in due tempi (D-353):

| Tempo | Dove | Dati | Quando |
|---|---|---|---|
| **A1** | VM di collaudo | Fittizi (club del vecchio sistema, calendario e prezzi inventati) | Subito dopo la chiusura di F2 |
| **A2** | VM di collaudo, con l'eccezione di D-355 | Calendario reale di Infront, listino e capacità reali di 2–3 club | All'arrivo del calendario (entro il 30/11/2026) |

Come si compila: per ogni passo segna ✅ (esito atteso), ❌ (diverso: annota cosa vedi) o ⏭ (saltato, con il motivo). Gli esiti con ❌ diventano una PR `fix/…` prima di R0.

Tutti i comandi si eseguono **nel terminale della VM** (collegamento da PowerShell: `ssh -p 2222 antonio@127.0.0.1`, poi `cd ~/infront-portale-commerciale` e `sail up -d`).

---

## 1. Preparazione (A1)

| # | Passo | Esito atteso | A1 |
|---|---|---|---|
| 1.1 | Da VirtualBox, istantanea **"prima della milestone A"** | Si potrà tornare ai dati dei collaudi precedenti | ✅ |
| 1.2 | `git checkout main && git pull`, `sail composer install`, `sail npm ci && sail npm run build` | Nessun errore | ✅ |
| 1.3 | Controllo di `DEV_USERS_PASSWORD` e `DEV_USERS_TOTP_SECRET` nel `.env`, poi `sail artisan migrate:fresh --seed` (⚠️ **cancella tutti i dati della VM**) | Tabelle ricreate; dati di sistema (impostazioni, calcio con Serie A, B, C gironi A/B/C, catalogo, 24 settori merceologici) e utenti di prova | ✅ |
| 1.4 | `sail artisan users:create-admin`, poi accesso su http://localhost:8080 e configurazione della 2FA dell'admin (nell'app la chiave dell'admin è nuova: togliere la vecchia voce) | Dashboard dell'admin | ✅ |
| 1.5 | Seconda finestra: `sail artisan horizon`; poi `sail artisan schedule:run`, `sail artisan health:check` e http://localhost:8080/health | Sei controlli positivi; `{"status":"ok"}` | ✅ |
| 1.6 | `sail artisan test --compact 2>&1 \| tail -n 5` (circa 3 minuti) | Tutto verde, 3 *skipped* (`AuditUsersScriptTest`) | ✅ |

## 2. F0 — Accesso, permessi, audit

| # | Passo | Criterio | Esito atteso | A1 |
|---|---|---|---|---|
| 2.1 | Accesso come `agente2` (senza 2FA) | M01-CA1 | Configurazione della 2FA obbligatoria prima di qualsiasi pagina | ✅ |
| 2.2 | 5 password errate per `agente1` | M01-CA2, M10-CA2 | Blocco di 15′ con conto alla rovescia, e-mail di blocco in Mailpit (utente e admin); `sail artisan audit:list --area=access --limit=15` mostra 5 `access.login_failed` con IP `10.0.2.2` e `access.locked_out`. Poi `sail artisan cache:clear` | ✅ |
| 2.3 | Come `gestore1`: menu laterale e URL diretti `/catalogo/valori`, `/magazzino/capacita`, `/anagrafiche/stagioni`, `/anagrafiche/club`, `/clienti/settori`, `/utenti`, `/impostazioni`, `/audit`, `/horizon` | M01-CA3 | Voci assenti dal menu; gli URL rispondono con la pagina "Accesso non consentito" (403) | ✅ |
| 2.4 | Come admin, **Utenti**: scheda del proprio account | M01-CA5 | Nota "Questo è il tuo account…"; nessun pulsante Disattiva, Reset password o Reset 2FA (la regola lato server è coperta dai test di `DeactivateUser`) | ✅ |
| 2.5 | `sail exec mariadb mariadb -uroot -ppassword infront_portale -e "UPDATE audit_events SET user_name = 'x' LIMIT 1;"` | M10-CA3 | `ERROR 1644 (45000) … audit_events is append-only: only ip_address can be cleared`, anche per root | ✅ |

## 3. S1 — Pipeline PDF

| # | Passo | Criterio | Esito atteso | A1 |
|---|---|---|---|---|
| 3.1 | `grep DOCUMENT_FONTS_PATH .env` e `sail artisan documents:try` | M09-CA4 (anticipato) | 40 pagine, ≤ 15 MB, font Interstate incorporati, nessun avviso di font di ripiego | ✅ 40 pagine, 10,5 MB, 11,4 s, Interstate Regular, Bold e Black |

## 4. F1 e F2 — Dati della stagione 2027-28 (percorso di R0)

### 4.1 Anagrafiche (F1)

| # | Passo | Criterio | Esito atteso | A1 |
|---|---|---|---|---|
| 4.1.1 | Copia del CSV dei club (`club-idoc.csv`) con `scp -P 2222` in `storage/app/private/`, poi `sail artisan sports:import-legacy-clubs storage/app/private/club-idoc.csv --merge=8,20 --merge=11,39 --merge=21,35 --merge=25,36` (prima con `--dry-run`); poi il CSV si cancella dalla VM | D-318, D-329 | Su database vuoto: 81 club creati, 0 già presenti, 85 riferimenti `idoc` (85 righe − 4 unioni); un evento `club.legacy_imported` (sistema); flag top match su Lazio, Inter, Milan, Juventus, Roma, Napoli. ⚠️ Sono club Infront tutti i club con `deleted = 0` nel vecchio sistema (25 sulla VM) | ✅ 81 creati, 1 alias, 85 riferimenti, 40 anomalie nel report |
| 4.1.2 | **Anagrafiche › Stagioni**: nuova stagione `2027-28` con le date reali della stagione; selettore stagione in alto | D-317 | Con `01/07/2027`–`30/06/2028`, stato "In preparazione": la 2027-28 è predefinita (nessuna stagione contiene oggi, è la prossima per data di inizio) | ✅ |
| 4.1.3 | Aggiunta di Serie A e Serie B alla stagione, con il numero standard di partite in casa (19) | D-185 | Edizioni create | ✅ |
| 4.1.4 | Stadi (facoltativi: assegnati a 3 club) e iscrizione di **esattamente 20** club in Serie A, tra cui la Lazio, e di 3–4 club in Serie B; controllo dei flag "club Infront" già impostati dal 4.1.1 | D-188 | Iscrizioni visibili nell'edizione | ✅ 20 in A (14 Infront), 3 in B (nessun Infront) |
| 4.1.5 | *Solo VM*: file fittizio generato dai 20 club iscritti con lo script del §8.1, copiato su Windows con `scp`; **Import calendario** Serie A: anteprima, associazione di "Toro Prova 1906" a Torino, conferma | M02-CA1, CA2 | 380 nuove, 0 errori dopo l'associazione; "380 partite inserite"; alias con origine `import` | ✅ |
| 4.1.6 | Re-import dello stesso file con la data di una partita cambiata e il nome associato al passo precedente | M02-CA2, CA3 | Nome riconosciuto da solo; 1 da aggiornare (LAZIO–INTER, 07/11 → 08/11/2027), 379 invariate, nessuna nuova | ✅ |
| 4.1.7 | Come `gestore1`: import di un calendario e tentativo di aprire `/anagrafiche/stagioni` | M02-CA8 | Re-import della v2: 0 nuove, 0 aggiornate, 380 invariate; stagioni "Accesso non consentito" | ✅ |
| 4.1.8 | `sail artisan sports:matches 2027-28 LAZIO`, poi con `--from=01/10/2027` | M02-CA4, CA6 | Calendario fittizio: 19 partite, 10 mesi, 5 top match; dal 01/10/2027 16 partite (prima il 03/10), 8 mesi, 4 top match | ✅ |
| 4.1.9 | `sail artisan sports:matches 2027-28 BARI` (edizione senza calendario), poi con `--from=31/12/2027` e `--from=01/01/2028` | M02-CA5, CA6, D-187 | 19 (stagione intera, senza avviso, D-320); 19 × 183 / 366 = 9,5 → 10; 19 × 182 / 366 → 9; avviso solo sui riproporzionati; top match "non previsti per queste competizioni" | ✅ |
| 4.1.10 | Club con iscrizioni: nessun pulsante di cancellazione, solo disattivazione | M02-CA7 (parte F1) | Solo il flag Attivo | ✅ |

### 4.2 Catalogo, listino e magazzino (F2)

| # | Passo | Criterio | Esito atteso | A1 |
|---|---|---|---|---|
| 4.2.1 | **Catalogo › Valori**: controllo di format, livelli, varianti e categorie | D-331, D-339 | Valori di D-332; codici non modificabili | ✅ |
| 4.2.2 | **Catalogo › Listini LED**: nuovo listino 2027-28 (bozza); prezzi LED dei club Infront (es. Lazio 1ª fila 3.157,89) | D-333 | Righe = club Infront iscritti (14 sulla VM). Riga del prezzo stagionale sotto la griglia: 59.999,91 € per 19 partite, "da calendario" dopo il 4.1.5 (D-340, D-351) | ✅ |
| 4.2.3 | **Partnership** e **Hospitality**: prezzi (es. Lazio Premium 135.000, posto Premium); un benefit Lazio Top | M03-CA7 | La Libera non ha prezzo; il benefit non compare per altri club o livelli | ✅ |
| 4.2.4 | Lasciare un club Infront senza prezzi LED e partnership; **Pubblica** | D-333, D-348 | Avviso non bloccante sui club senza offerte LED o partnership (11, compreso il club con il solo prezzo hospitality, D-348); listino pubblicato, non più riportabile in bozza | ✅ |
| 4.2.5 | `sail artisan catalog:price 2027-28 LAZIO --format=FILA_1 --minutes=1` e `--minutes=1,5` | M03-CA1 | 59.999,91 € e 89.999,87 € (89.999,865 arrotondato una volta sul club) | ✅ |
| 4.2.6 | Stesso comando con `--from=01/01/2028` (10 partite rimaste nel calendario fittizio) | M03-CA2 | 31.578,90 € e 47.368,35 € | ✅ |
| 4.2.7 | `sail artisan catalog:price 2027-28 LAZIO --tier=PREMIUM` con due date di inizio diverse | M03-CA3 | 135.000 € in entrambi i casi | ✅ |
| 4.2.8 | `sail artisan catalog:price 2027-28 LAZIO --seats=PREMIUM:2`, con e senza `--tier` | M03-CA9 | 2 × prezzo a posto in entrambi i casi (3.000 €; con `--tier=PREMIUM` totale 138.000 €) | ✅ |
| 4.2.9 | **Magazzino › Capacità**: capacità dei club Infront; 1ª fila Lazio **non gestita**; un bacino di un altro club a 0 | D-347 | Salvataggio con audit; capacità mai impostata = "non impostato", non gestita (D-347) | ✅ |
| 4.2.10 | `sail artisan inventory:availability 2027-28 LAZIO` e pagina **Magazzino › Disponibilità** | M04-CA3, M03-CA4 | Il comando mostra tutte le opzioni con lo stato: 1ª fila, Jumbo e Domination della Lazio "Non gestito"; un valore senza prezzo "Non disponibile" (le nasconde il configuratore, F4) | ✅ |
| 4.2.11 | `sail artisan inventory:availability` sul club con il bacino a 0 | M04-CA4 | Format "Esaurito" con residuo 0′00″, visibile ma non selezionabile | ✅ |
| 4.2.12 | *Solo VM*: impegno di prova di 10′ sulla 2ª fila della Lazio creato con la factory dei test (comando al §8.2: gli impegni non hanno interfaccia fino a F5, D-334), poi capacità ridotta a 5′ | M04-CA10 | Residuo 10′00″; poi conferma richiesta, salvataggio, residuo −5′00″ evidenziato ("sotto l'impegnato", D-349) | ✅ |
| 4.2.13 | Come `gestore1`: Listini e Disponibilità in sola lettura; Capacità e Valori negati | D-337 | Nessuna modifica in cella, "Pubblica" o "Modifica capacità" | ✅ |

### 4.3 Audit di tutto il percorso

| # | Passo | Criterio | Esito atteso | A1 |
|---|---|---|---|---|
| 4.3.1 | `sail artisan audit:list --area=sports`, `--area=catalog`, `--area=inventory` | M10-CA1 (parte F1–F2) | Un evento per ogni scrittura fatta sopra, con autore e valori prima/dopo; un solo evento per ogni conferma di import; nessun evento per l'impegno del 4.2.12 (factory, non Action) | ✅ |

## 5. A2 — Dati reali

Si svolge all'arrivo del calendario di Infront, **sulla VM** con l'eccezione di D-355: solo dati commerciali non personali reali (calendario, listino, capacità di 2–3 club), mai nel repository; partire da un'istantanea e chiudere con `sail artisan migrate:fresh --seed`.

| # | Passo | Criterio | Esito atteso |
|---|---|---|---|
| 5.1 | Import del calendario reale di Serie A (e di B, se disponibile), con le colonne riconosciute o scelte a mano | M02-CA1 sui dati reali | Nessun errore; nomi associati salvati come alias |
| 5.2 | Re-import dello stesso file | M02-CA2 | Nessun nome da associare |
| 5.3 | `sports:matches` per i club Infront, a stagione intera e da una data | M02-CA4 | Partite coerenti con il calendario ufficiale |
| 5.4 | Listino e capacità reali di 2–3 club; `catalog:price` per contratti già venduti nel 2026-27 con gli stessi dati | M03-CA1…CA3 | Prezzi uguali a quelli dei documenti del vecchio sistema, a meno dell'arrotondamento (≤ 1 €) |

## 6. Chiusura e prerequisiti di R0

| # | Passo | Esito atteso |
|---|---|---|
| 6.1 | Elenco degli esiti ❌ e PR `fix/…` corrispondenti | Tutti risolti prima di R0 · **A1: nessun ❌** |
| 6.2 | Istantanea della VM **"milestone A1"** | — |
| 6.3 | **Produzione** su VM dedicata con root (D-354) conforme a R1–R7: pacchetti di `docs/dev/ambiente-locale.md`, tre utenti MariaDB (`docs/dev/database-utenti.md`), estensioni PHP `gd`, `fileinfo`, `zip`, `pcntl`, `posix`, processo permanente di Horizon, cron dello scheduler, struttura `releases/`/`shared/`/`current` e regola sudoers (`docs/dev/ci.md` §3), Chrome for Testing, qpdf, poppler-utils, font Interstate in `DOCUMENT_FONTS_PATH`, backup fuori dall'host (D-177) | Pronta entro il **15/1/2027** |
| 6.4 | Primo deploy in produzione, `/health` = ok, `users:create-admin`, caricamento dei dati come al §4 (senza i passi "solo VM") | R0 entro il **18/1/2027** |
| 6.5 | Dopo l'A2: `sail artisan migrate:fresh --seed` sulla VM (D-355) | Nessun dato reale resta sulla VM |

## 7. Esito dell'A1 (7/11/2026)

Eseguito da Antonio sulla VM con `main` alla chiusura di F3 (`d6f7858`, PR #79), dopo `migrate:fresh --seed`: **tutti i passi dei §1–§4 ✅, nessuna PR `fix/…`**. Gli esiti attesi sono stati verificati sul codice prima di ogni passo e corretti in questa checklist dove la versione del 4/11 non era precisa (nessun difetto del portale):

- 1.3, 1.5, 1.6: controllo del `.env`, `health:check`, durata della suite;
- 2.3: la pagina 403 è "Accesso non consentito"; aggiunti `/clienti/settori` (F3) e `/horizon`;
- 2.4: l'admin non ha il pulsante "Disattiva" sul proprio account (nota sulla scheda); la regola lato server è nei test;
- 2.5: testo reale dell'errore del trigger;
- 4.1.1: su database vuoto 81 club creati e 0 già presenti; tutti i club con `deleted = 0` diventano club Infront (D-318);
- 4.1.4: il flag "club Infront" non si imposta, si controlla;
- 4.1.5: il file fittizio non esiste nel repository: si genera con lo script del §8.1;
- 4.1.9: l'avviso del numero standard compare solo quando le partite sono riproporzionate (RB-05, D-320);
- 4.2.10: `inventory:availability` mostra le opzioni non vendibili con il loro stato;
- 4.2.12: il collaudo della #63 non creava impegni; comando al §8.2.

⚠️ **Da fare prima di R0** (non è un difetto, ma riguarda il caricamento reale): il comando dei club marca come club Infront tutti i club non cancellati del vecchio sistema (25 sulla VM). Prima di R0 l'elenco dei club Infront 2027-28 va confermato con Infront e corretto dall'admin in **Anagrafiche › Club**, perché le griglie dei listini e delle capacità mostrano tutti i club Infront iscritti.

Restano: istantanea **"milestone A1"** (6.2), A2 con il calendario reale (§5), prerequisiti di R0 (6.3, 6.4).

## 8. Strumenti del collaudo (solo VM, mai in produzione)

### 8.1 Calendario fittizio della Serie A (4.1.5, 4.1.6)

Lo script legge i 20 club iscritti alla Serie A 2027-28 in ordine di codice e scrive in `storage/app/private/` due file nel tracciato D-017, con le intestazioni dei test di F1-04: un doppio girone di 380 partite, una giornata a settimana da domenica 22/08/2027 al 07/05/2028, 19 partite in casa per club e mai più di due di fila in casa o fuori. Nella **v1** il club TORINO compare come "Toro Prova 1906" (nome da associare); la **v2** sposta LAZIO–INTER di un giorno. Si incolla nel terminale della VM e non si committa.

```bash
cat > storage/app/private/genera-calendario.php <<'EOF'
<?php

// Calendario fittizio della Serie A 2027-28 per il collaudo A1 (passi 4.1.5 e 4.1.6).
// Legge i 20 club iscritti all'edizione e scrive due file nel tracciato D-017:
// v1 con il nome di TORINO storpiato (da associare); v2 uguale, con LAZIO-INTER spostata di un giorno.

use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use Carbon\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

$edition = CompetitionSeason::query()
    ->whereHas('season', fn ($query) => $query->where('code', '2027-28'))
    ->whereHas('competition', fn ($query) => $query->where('code', 'SERIE_A'))
    ->firstOrFail();

$clubs = Club::query()
    ->whereIn('id', ClubEnrollment::query()->where('competition_season_id', $edition->id)->pluck('club_id'))
    ->orderBy('code')
    ->get();

if ($clubs->count() !== 20) {
    throw new RuntimeException('Servono 20 club iscritti in Serie A 2027-28, trovati '.$clubs->count());
}

$unknown = 'Toro Prova 1906';
$codes = $clubs->pluck('code')->all();
$names = [];
foreach ($clubs as $club) {
    $names[$club->code] = $club->code === 'TORINO' ? $unknown : $club->short_name;
}

// Doppio girone all'italiana (metodo del cerchio), una giornata a settimana da domenica 22/08/2027.
$first = Carbon::create(2027, 8, 22);
$teams = $codes;
$count = count($teams);
$rounds = $count - 1;
$matches = [];

for ($round = 0; $round < $rounds; $round++) {
    for ($index = 0; $index < intdiv($count, 2); $index++) {
        $a = $teams[$index];
        $b = $teams[$count - 1 - $index];
        if ($index === 0) {
            [$home, $away] = $round % 2 === 0 ? [$a, $b] : [$b, $a];
        } else {
            [$home, $away] = $index % 2 === 1 ? [$a, $b] : [$b, $a];
        }
        $matches[] = [$round + 1, $home, $away];
        $matches[] = [$round + 1 + $rounds, $away, $home];
    }
    $teams = array_merge([$teams[0], $teams[$count - 1]], array_slice($teams, 1, $count - 2));
}

usort($matches, fn (array $x, array $y): int => $x[0] <=> $y[0]);

$write = function (string $file, bool $moved) use ($matches, $names, $first): void {
    $writer = new Writer;
    $writer->openToFile($file);
    $writer->addRow(Row::fromValues(['Stagione', 'Competizione', 'Giornata', 'Data', 'Ora', 'Squadra casa', 'Squadra ospite', 'Stadio']));

    foreach ($matches as [$matchday, $home, $away]) {
        $date = $first->copy()->addWeeks($matchday - 1);
        if ($moved && $home === 'LAZIO' && $away === 'INTER') {
            $date->addDay();
        }
        $writer->addRow(Row::fromValues(['2027-28', '', $matchday, $date->format('d/m/Y'), '20:45', $names[$home], $names[$away], '']));
    }

    $writer->close();
};

$v1 = storage_path('app/private/calendario-fittizio-serie-a-2027-28.xlsx');
$v2 = storage_path('app/private/calendario-fittizio-serie-a-2027-28-v2.xlsx');
$write($v1, false);
$write($v2, true);

echo 'Partite: '.count($matches).PHP_EOL;
echo 'Nome da associare: '.$unknown.' (TORINO)'.PHP_EOL;
foreach ($matches as [$matchday, $home, $away]) {
    if ($home === 'LAZIO' && $away === 'INTER') {
        echo 'Partita spostata nella v2: giornata '.$matchday.', LAZIO-INTER dal '
            .$first->copy()->addWeeks($matchday - 1)->format('d/m/Y').' al '
            .$first->copy()->addWeeks($matchday - 1)->addDay()->format('d/m/Y').PHP_EOL;
    }
}
echo 'File: '.$v1.PHP_EOL.'      '.$v2.PHP_EOL;
EOF
sail artisan tinker --execute="require 'storage/app/private/genera-calendario.php';"
```

Copia su Windows (PowerShell): `scp -P 2222 "antonio@127.0.0.1:~/infront-portale-commerciale/storage/app/private/calendario-fittizio-*.xlsx" .`

### 8.2 Impegno di prova (4.2.12)

```bash
sail artisan tinker --execute="App\Models\InventoryCommitment::factory()->forPool(App\Models\InventoryPool::query()->where('code', 'FILA_2')->firstOrFail(), 600)->create(['season_id' => App\Models\Season::query()->where('code', '2027-28')->value('id'), 'club_id' => App\Models\Club::query()->where('code', 'LAZIO')->value('id'), 'created_by' => App\Models\User::query()->where('role', 'admin')->value('id')]);"
```

Crea un impegno aperto di 600 secondi a partita sulla 2ª fila della Lazio per tutta la stagione, con id di proposta fittizi (le FK verso le proposte arrivano con F5, D-334). L'impegno è immutabile per trigger (D-346) e sparisce solo con `migrate:fresh`.
