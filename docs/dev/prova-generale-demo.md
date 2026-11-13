# Dati della demo e prova generale (DM-08)

Guida per preparare la VM per la demo a Infront del **4/12/2026** (D-394, D-400, D-438…D-442) e per la prova generale. I dati sono **fittizi**: nomi veri dei club di Serie A, prezzi, capacità, calendario, clienti e proposte inventati (CLAUDE.md §7). Il materiale vero (foto dei campi, loghi dei club, slide dei documenti di esempio) resta **solo sulla VM**, nella cartella della demo, e non va mai nel repository.

⚠️ La preparazione parte da `migrate:fresh --seed`: **cancella tutti i dati della VM** (proposte dei collaudi, simulazioni LED caricate a mano, libreria slide). Prima fai un'istantanea della VM (*Macchina* → *Istantanea*, es. "prima dei dati della demo").

## 1. Cosa prepara `demo:prepare`

| Parte | Contenuto |
|---|---|
| Anagrafiche | Stagione 2027-28 **Aperta**; Serie A con 20 club (Lazio, Roma, Bologna, Udinese e Como club Infront; flag top match su Lazio, Inter, Milan, Juventus, Roma, Napoli, D-257); calendario fittizio di **380 partite**, una giornata a settimana dal 22/08/2027 all'07/05/2028, 19 partite in casa per club; Serie B con 19 partite in casa, **senza club né calendario** (D-441) |
| Catalogo e magazzino | Listino 2027-28 **pubblicato** per i 5 club (Lazio e Roma al 100%, Bologna 70%, Como 60%, Udinese 55% dei prezzi base: 1ª fila 3.000 €, 2ª fila 2.400 €, Jumbo 5.400 €, Domination 8.400 € per 1′ a partita; Top 250.000 €, Premium 135.000 €, Official 70.000 €, Official 2.0 45.000 €; posto Premium 3.500 €, Base 1.800 €); capacità gestite (30′ per fila, Top 1, Premium 4, Official 8, Official 2.0 10, 60 posti Premium e 120 Base) |
| Clienti | Birra Aurora (logo con sfondo pieno: striscia del LED arancione), Vela Assicurazioni (sfondo pieno blu), Caffè Monteverde e Tecnoforma (logo trasparente: striscia nera, D-425) di **agente1**; Autolinee Delta di **agente2** |
| Libreria slide | Per il network (12) e per ognuno dei 5 club (8): copertina, slide statiche con condizioni (Partnership, Top partner, Hospitality, LED, Jumbo e Domination), **simulazione LED**, **sfondo della slide personalizzata**, **recap**, chiusura. Sfondi chiari con l'area della tabella e della slide personalizzata libera (D-438) |
| Simulazioni LED | Generiche Infront (1ª fila, 2ª fila, Jumbo/Domination) e dell'Udinese, dalle foto della cartella `campi` con le aree misurate: generiche fila superiore y 180–218, inferiore 224–262; Udinese 61–98 e 101–139. Senza le foto, quelle generiche sono disegnate e quelle dell'Udinese si saltano (vale la generica, D-427) |
| Proposte (D-440) | **Birra Aurora**: gruppo A (Lazio Top, 1ª fila 1′), B (Lazio Premium 1ª fila 1′ + Roma Premium 2ª fila 1′), C (cinque club: Lazio, Roma, Bologna con Jumbo, Udinese con Domination e 2 posti, Como) **in approvazione**. **Vela Assicurazioni**: due varianti in **bozza**, Roma Premium + 2ª fila 1′ + 2 posti Premium, A dal 01/07/2027 (**187.600,00 €**, 19 partite) e B dal 01/01/2028 (**166.000,00 €**, 10 partite). **Caffè Monteverde**: Udinese Top + Jumbo 1′ + 4 posti Premium con una slide personalizzata in due colonne, **approvata** dall'admin con lo sconto del 10% (listino 201.630,00 €, finale 181.467,00 €) e documento in coda |

Utenti (D-442): `agente1@example.test` (Luca Agenti), `agente2@example.test` (Sara Agenti, senza 2FA), `gestore1@example.test` (Marta Gestori), `gestore2@example.test`, `agente3@example.test` (disattivato), con la password `DEV_USERS_PASSWORD` e la voce "Infront locale" dell'app per la 2FA; l'admin si crea con `users:create-admin` ed è l'autore di tutte le scritture del comando (audit).

## 2. Materiale della demo sulla VM

La cartella della demo sulla VM è `~/infront-portale-commerciale/storage/app/demo` (esclusa da git), con tre sottocartelle, tutte facoltative:

| Sottocartella | File | Uso |
|---|---|---|
| `campi/` | Le foto della cartella `campi` di Windows così come sono: `INFRONT_FILA1.jpg`, `INFRONT_FILA2.jpg`, `INFRONT_JUMBO_DOMINATION.jpg`, `UDINESE_FILA1.jpg`, `UDINESE_FILA2.jpg`, `UDINESE_JUMBO_DOMINATION.jpg` (le altre si ignorano) | Simulazioni LED |
| `loghi/` | `LAZIO.png`, `ROMA.png`, `BOLOGNA.png`, `UDINESE.png`, `COMO.png` (PNG, al massimo 2 MB) | Loghi dei club (D-439); senza, un disco con le iniziali nel colore sociale |
| `slide/` | `<PREFISSO>_<CHIAVE>.png` o `.jpg` di **1920×1080**: prefisso `INFRONT` o il codice del club; chiavi `COPERTINA`, `RECAP`, `PERSONALIZZATA`, `PARTNERSHIP`, `HOSPITALITY`, `CHIUSURA`, per il network anche `INTRO`, `NETWORK`, `TOP`, `LED`, `JUMBO`, per i club `CLUB` (es. `INFRONT_COPERTINA.jpg`, `LAZIO_RECAP.png`) | Sostituiscono la slide disegnata (D-438); un file di altre misure si ignora e si segnala. ⚠️ Recap e slide personalizzata devono lasciare libera l'area del contenuto (x 56, y 226, 1790 × 690 px; recap club dalla y 453) |

**Copia dal PowerShell di Windows** (ricordati prima di avviare la VM, §3 passo 1):

```powershell
ssh -p 2222 antonio@127.0.0.1 "mkdir -p ~/infront-portale-commerciale/storage/app/demo/campi ~/infront-portale-commerciale/storage/app/demo/loghi ~/infront-portale-commerciale/storage/app/demo/slide"
cd C:\Users\bitan\proj\docs.infrontcorporate.it\campi
scp -P 2222 INFRONT_FILA1.jpg INFRONT_FILA2.jpg INFRONT_JUMBO_DOMINATION.jpg UDINESE_FILA1.jpg UDINESE_FILA2.jpg UDINESE_JUMBO_DOMINATION.jpg antonio@127.0.0.1:~/infront-portale-commerciale/storage/app/demo/campi/
```

Loghi e slide, se li vuoi, dalla loro cartella: `scp -P 2222 <file> antonio@127.0.0.1:~/infront-portale-commerciale/storage/app/demo/loghi/` (o `/slide/`). Le slide dei PDF di esempio si estraggono come in `ambiente-locale.md` §4 ("Provare la pipeline con le immagini vere") e si rinominano con i nomi sopra.

Poi, nel terminale della VM, nel `.env` (è il percorso **dentro** il container):

```bash
grep -q '^DEMO_ASSETS_PATH=' .env || echo 'DEMO_ASSETS_PATH=' >> .env
sed -i 's#^DEMO_ASSETS_PATH=.*#DEMO_ASSETS_PATH=/var/www/html/storage/app/demo#' .env
grep -E '^(DEMO_ASSETS_PATH|DOCUMENT_CHROMIUM_NO_SANDBOX|DOCUMENT_FONTS_PATH)=' .env
ls storage/app/demo/campi
```

Esito atteso: `DEMO_ASSETS_PATH=/var/www/html/storage/app/demo`, `DOCUMENT_CHROMIUM_NO_SANDBOX=true` (anteprime dal browser, DM-07), `DOCUMENT_FONTS_PATH=/var/www/html/storage/app/document-fonts` (Interstate) e l'elenco delle sei foto.

## 3. Preparazione

1. **Collegati alla VM**: avvia `infront-portale` da VirtualBox, aspetta 30 secondi, poi dal PowerShell di Windows `ssh -p 2222 antonio@127.0.0.1`, quindi `cd ~/infront-portale-commerciale` e `sail up -d`.
2. **Ora della VM** (il codice dell'app viene rifiutato se l'orologio sbaglia; dettagli in `ambiente-locale.md` §4):

   ```bash
   date
   timedatectl | grep -E "Time zone|synchronized"
   sudo systemctl enable --now chrony
   sudo chronyc makestep
   sleep 5
   date
   chronyc tracking | grep -E "System time|Leap status"
   ```

   Esito atteso: `date` uguale all'ora di Windows, fuso `Europe/Rome`, `Leap status : Normal`. Se resta sbagliata: `sudo date -s "$(curl -sI https://www.google.com | grep -i '^date:' | cut -d' ' -f2- | tr -d '\r')"`.
3. **Codice allineato a `main`**:

   ```bash
   git checkout main
   git pull
   sail composer install
   sail npm ci && sail npm run build
   ```

4. **Database da zero, admin e dati della demo**:

   ```bash
   sail artisan migrate:fresh --seed
   sail artisan users:create-admin
   ```

   `users:create-admin` è interattivo: alla fine configura la 2FA dell'admin nell'app (togli la vecchia voce dell'admin della VM). Poi apri una **seconda finestra di PowerShell**, collegati di nuovo (`ssh -p 2222 antonio@127.0.0.1`, `cd ~/infront-portale-commerciale`) e avvia il worker: `sail artisan horizon` (serve al PDF della proposta approvata). Nella prima finestra:

   ```bash
   sail artisan demo:prepare
   ```

   Dura circa un minuto. Esito atteso: le fasi ("Stagione, stadi, club ed edizioni…" … "Proposte…"), poi l'elenco con i loghi, "Calendario fittizio della Serie A: 380 partite", "Libreria slide: 52 slide", le sei simulazioni LED "dalla cartella della demo", le proposte **204…206** (Birra Aurora, in approvazione), **207** e **208** (Vela, € 187.600,00 e € 166.000,00) e **209** (Caffè Monteverde, approvata) e "Dati della demo pronti". Se il comando si ferma con "Il portale contiene già stagioni o club", il `migrate:fresh --seed` non è stato fatto.
5. **Controlli**:

   ```bash
   sail artisan documents:compose 204
   sail artisan sports:matches 2027-28 ROMA --from=01/01/2028
   sail artisan audit:list --area=proposals --limit=30
   ```

   Esito atteso: per la 204 il documento network con copertina, slide statiche, cinque simulazioni LED (una per club e format, D-423), recap A, B e C, chiusura e nessun avviso; per la Roma dal 01/01/2028 10 partite; nell'audit la nascita delle bozze, l'invio e l'approvazione della 209. Nel portale, come admin, **Proposte › Tutte**: la 209 "Approvata" con il documento "Pronto" (con il worker avviato).
6. **Istantanea** della VM: *Macchina* → *Istantanea*, nome "demo 4/12". Da qui si riparte prima di ogni prova e prima della demo.

## 4. Prova generale (percorso della demo)

Un passo alla volta, come alla demo; ogni passo indica l'utente.

| # | Utente | Passo | Cosa si vede |
|---|---|---|---|
| 1 | agente1 | Login con la 2FA; **Le mie proposte** | Tre gruppi: Birra Aurora (A, B, C in approvazione), Vela (A, B in bozza), Caffè Monteverde (approvata) |
| 2 | agente1 | **Nuova proposta** per Tecnoforma: stagione 2027-28, Lazio con Premium e 1ª fila 1′, 2 posti Premium; riepilogo | Totale sempre visibile, KPI di campagna (partite, spot, mesi, top match) |
| 3 | agente1 | Apri la bozza Vela A e poi la B: confronta i riepiloghi | Stessa configurazione, € 187.600,00 dal 01/07/2027 contro € 166.000,00 dal 01/01/2028 (19 e 10 partite): il calcolo per date che nel vecchio portale dava errori |
| 4 | agente1 | Sulla bozza Tecnoforma: **Aggiungi variante** B con Roma; passo **Documento**: togli una slide, prova "Solo riepilogo" e torna a "Documento completo"; aggiungi una **slide personalizzata** e premi "Anteprima"; **Invia in approvazione** | Varianti A, B nello stesso gruppo; slide scelte dall'agente; anteprima uguale al PDF |
| 5 | admin o gestore1 | **Proposte › Da approvare** → Birra Aurora | Tre varianti, avvisi, slide del documento modificabili |
| 6 | admin | Sconto del 10% sulla A e di 5.000 € sulla C; **Approva** | Gruppo approvato; documento "In coda" → "Pronto" (worker avviato) |
| 7 | admin | Scarica il PDF | Copertina con il logo Aurora, slide con condizioni, cinque **simulazioni del logo sul LED** con la striscia arancione, recap "Proposta A / B / C" con listino barrato e totale finale |
| 8 | admin | **Registra la scelta del cliente**: variante B | A e C "Non scelta", impegni rilasciati |
| 9 | admin | Caffè Monteverde (209): scarica il PDF | Documento club dell'Udinese: copertina, simulazione Jumbo sulla foto dell'Udinese con la striscia nera, slide personalizzata in due colonne, recap con lo sconto |
| 10 | admin | **Contenuti › Simulazioni LED** → Udinese → anteprima | Editor delle aree sulla foto, anteprima della simulazione |

⚠️ I passi 5–8 cambiano i dati (impegni, documenti): per ripeterli si torna all'istantanea "demo 4/12".

## 5. Prima della demo

- Istantanea "demo 4/12" fatta e provata (ripristino, ora della VM, login di agente1 e admin).
- Worker avviato nella seconda finestra prima di approvare.
- Le PR di Dependabot #74 (Inertia 3.11) e #104 (Browsershot 5.5) restano **ferme** fino alla demo.
- Nessuna `git pull` sulla VM dopo l'istantanea, salvo correzioni concordate.
