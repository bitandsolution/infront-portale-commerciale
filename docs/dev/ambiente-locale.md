# Ambiente locale di collaudo (VirtualBox su Windows)

Stato: ✅ D-261. Serve ad Antonio per provare ogni PR finché lo staging su server non è disponibile.
Tempo della prima installazione: circa un'ora.

Regole:
- solo **dati fittizi** (seeder del repository); il dump del vecchio sistema non entra nella VM;
- la VM **non si espone su internet** (niente port forwarding sul router, niente ngrok o tunnel simili);
- il repository si clona **dentro la VM**, non in una cartella condivisa con Windows, che è lenta e dà problemi di permessi.

## 1. Creare la VM

1. Installa **VirtualBox 7** da virtualbox.org e scarica l'immagine **Ubuntu Server LTS** (ubuntu.com/download/server): va bene la 24.04 o la 26.04.
2. ⚠️ Se su Windows sono attivi Hyper-V, WSL 2 o Docker Desktop, VirtualBox funziona lo stesso ma più lento (icona della tartaruga in basso a destra). Se la VM è troppo lenta, disattiva "Piattaforma macchina virtuale" e "Hyper-V" in *Attiva o disattiva funzionalità di Windows* e riavvia.
3. Nuova macchina virtuale:

   | Voce | Valore |
   |---|---|
   | Nome | `infront-portale` |
   | Immagine ISO | Ubuntu Server 24.04 o 26.04 LTS |
   | Processori | 4 |
   | Memoria | 8192 MB |
   | Disco | 40 GB, allocazione dinamica |
   | Rete | NAT, con "Cavo collegato" spuntato |
   | Sistema → Scheda madre | "Orologio hardware in tempo UTC" spuntato |

4. Installazione di Ubuntu: lingua a scelta, utente `antonio`, attiva **"Install OpenSSH server"**. Non selezionare i pacchetti aggiuntivi (il Docker "snap" in particolare: lo installiamo dal repository ufficiale).
5. VirtualBox → *Impostazioni* della VM → *Rete* → *Avanzate* → **Inoltro delle porte**:

   | Nome | IP host | Porta host | Porta guest |
   |---|---|---|---|
   | ssh | 127.0.0.1 | 2222 | 22 |
   | web | 127.0.0.1 | 8080 | 80 |
   | mailpit | 127.0.0.1 | 8025 | 8025 |

   L'IP host `127.0.0.1` fa sì che le porte siano raggiungibili solo dal tuo PC, non dalla rete dell'ufficio.

Da qui in poi conviene lavorare da **PowerShell** di Windows, collegati alla VM: `ssh -p 2222 antonio@127.0.0.1`.

## 2. Docker e Git nella VM

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y ca-certificates curl git
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" | sudo tee /etc/apt/sources.list.d/docker.list
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker $USER
```

Esci e rientra con `ssh`, poi verifica: `docker run --rm hello-world`.

Chiave per GitHub (il repository è privato). Nella VM si fanno solo `clone`, `fetch` e `checkout`, quindi basta una **deploy key in sola lettura**, valida solo per questo repository: se la VM venisse compromessa, gli altri repository dell'account restano al sicuro.

```bash
ssh-keygen -t ed25519 -C "vm-infront-portale" -f ~/.ssh/id_ed25519
cat ~/.ssh/id_ed25519.pub
```

Copia la chiave in GitHub → repository `bitandsolution/infront-portale-commerciale` → *Settings* → *Deploy keys* → *Add deploy key*, titolo `vm-infront-portale`, **senza** spuntare "Allow write access". Non usare *Settings* → *SSH and GPG keys* del profilo: darebbe accesso in scrittura a tutti i repository.

Verifica con `ssh -T git@github.com`: alla prima connessione conferma con `yes` se l'impronta è `SHA256:+DiY3wvvV6TuJJhbpZisF/zLDA0zPMSvHdkr4UvCOqU` (quella pubblicata da GitHub). Risposta attesa: `Hi bitandsolution/infront-portale-commerciale! You've successfully authenticated…`.

## 3. Prima installazione del portale

```bash
git clone git@github.com:bitandsolution/infront-portale-commerciale.git
cd infront-portale-commerciale
# Finché la PR-00 non è unita, main contiene solo la documentazione:
# git checkout feat/f0-skeleton
cp .env.example .env
sed -i 's#^APP_URL=.*#APP_URL=http://localhost:8080#' .env

# Dipendenze PHP con il container di Sail: nella VM non serve installare PHP
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install

echo "alias sail='./vendor/bin/sail'" >> ~/.bashrc && source ~/.bashrc

sail up -d                     # la prima volta costruisce l'immagine: 5-10 minuti
sail artisan key:generate
sail artisan migrate --seed
sail artisan users:create-admin   # primo admin: nome, cognome, e-mail, password (≥ 12 caratteri)
sail npm ci
sail npm run build
```

Al primo accesso (**http://localhost:8080**) il portale chiede e-mail e password, poi la configurazione della **verifica in due passaggi** (D-136): inquadri il codice QR con l'app di autenticazione dello smartphone, confermi con il codice a 6 cifre e salvi gli 8 codici di recupero, che vengono mostrati una sola volta. Da quel momento ogni accesso chiede il codice dell'app.

### Utenti di prova con password e chiave note (solo VM)

Per entrare come gestore o agente di prova senza configurare la verifica per ognuno, il `.env` della VM può dare a tutti gli utenti di prova una **password nota** e una **chiave TOTP condivisa** (PR-03). Le due variabili valgono solo con `APP_ENV=local`, non vanno mai su un server e non entrano nel repository.

```bash
# 1. Genera una chiave (16 caratteri, lettere A-Z e cifre 2-7)
sail artisan tinker --execute="echo (new PragmaRX\Google2FA\Google2FA)->generateSecretKey(16), PHP_EOL;"

# 2. Scrivila nel .env con una password di almeno 12 caratteri (solo per la VM)
grep -q '^DEV_USERS_PASSWORD=' .env || printf 'DEV_USERS_PASSWORD=\nDEV_USERS_TOTP_SECRET=\n' >> .env
sed -i 's#^DEV_USERS_PASSWORD=.*#DEV_USERS_PASSWORD=<password-di-prova>#' .env
sed -i 's#^DEV_USERS_TOTP_SECRET=.*#DEV_USERS_TOTP_SECRET=<chiave>#' .env

# 3. Ricrea gli utenti di prova (poi ricrea l'admin)
sail artisan config:clear
sail artisan migrate:fresh --seed
sail artisan users:create-admin
```

Nell'app dello smartphone aggiungi **una sola voce** inserendo la chiave a mano (account "Infront locale", tipo "basato sul tempo"): vale per `gestore1`, `gestore2`, `agente1` e `agente3` (disattivato). `agente2` resta senza verifica, per provare la configurazione obbligatoria. Senza le due variabili le password sono casuali e nessun utente di prova ha la verifica.

Apri nel browser di Windows:
- portale: **http://localhost:8080**
- e-mail intercettate (Mailpit): **http://localhost:8025**

## 4. Provare una PR

Per collaudare una PR **prima** del merge (il nome del branch è nella PR):

```bash
cd ~/infront-portale-commerciale
git fetch origin
git checkout <branch-della-pr>          # es. feat/f0-base-ui
sail composer install
sail artisan migrate                    # oppure: sail artisan migrate:fresh --seed
sail npm ci && sail npm run build
```

Dopo il merge, torna su `main` con `git checkout main && git pull` e ripeti gli stessi tre comandi.

Ogni PR indica nelle istruzioni di collaudo se serve `migrate:fresh --seed` (dati di prova ricreati da zero) o un comando in più.

Comandi utili:

| Cosa | Comando |
|---|---|
| Accendere / spegnere i servizi | `sail up -d` / `sail stop` |
| Eseguire i test | `sail artisan test` |
| Vedere i log dell'applicazione | `sail artisan pail` oppure `tail -f storage/logs/laravel.log` |
| Ricreare il database con i dati di prova | `sail artisan migrate:fresh --seed` (poi `sail artisan users:create-admin`) |
| Creare il primo admin | `sail artisan users:create-admin` (si rifiuta se un admin esiste già) |
| Sbloccare subito un accesso bloccato (5 tentativi) | `sail artisan cache:clear` (altrimenti si attendono 15 minuti) |
| Azzerare la verifica in due passaggi di un utente, anche dell'unico admin (PR-04) | `sail artisan users:reset-two-factor <e-mail>` (chiede conferma; l'utente la riconfigura al login successivo) |
| Leggere le e-mail con i link di attivazione e di reset (PR-04) | Mailpit: **http://localhost:8025**. Dalla PR-08 le e-mail passano dalla coda `mail`: arrivano solo con il worker avviato (riga "Avviare il worker delle code") |
| Vedere gli ultimi eventi di audit (PR-05) | `sail artisan audit:list` (filtri: `--area=access`, `--action=access.login_failed`, `--user=<e-mail>`, `--limit=50`) |
| Eseguire la conservazione dell'audit (PR-05) | `sail artisan audit:prune`: nella VM lo scheduler non gira, il comando si lancia a mano; `sail artisan schedule:list` mostra la pianificazione notturna |
| Aprire il database come amministratore (PR-05) | `sail exec mariadb mariadb -uroot -ppassword infront_portale` (la password di root è `DB_PASSWORD` del `.env`; per uscire `exit`) |
| Provare i controlli sui file caricati (PR-06) | `sail artisan files:try`: genera file di prova (loghi, slide, Excel, PDF, file travestiti), li passa al servizio e mostra esito e messaggio di ciascuno; i file accettati restano salvati. Esiste solo nell'ambiente di sviluppo |
| Scaricare un file salvato (PR-06) | **http://localhost:8080/file/&lt;id&gt;**, collegato come admin (fino alle fette che usano i file gli altri ruoli ricevono 403). I file stanno in `storage/app/private/files`, mai serviti direttamente |
| Pulire i file senza riga in `stored_files` (PR-06) | `sail artisan files:prune-orphans` (con `--dry-run` li elenca soltanto); cancella solo quelli più vecchi di 24 ore. In produzione gira ogni notte alle 3:45 |
| Scrivere le impostazioni di sistema mancanti (PR-07) | `sail artisan db:seed --class=SystemDataSeeder`: aggiunge solo le chiavi che non ci sono e non cambia i valori già salvati; lo esegue anche `migrate:fresh --seed`. Le impostazioni si vedono e si modificano da admin su **http://localhost:8080/impostazioni** |
| Avviare il worker delle code (PR-08) | In un **secondo terminale SSH** collegato alla VM: `cd ~/infront-portale-commerciale` e `sail artisan horizon`. Mostra `Horizon started successfully.` e resta in primo piano senza stampare i singoli lavori (si vedono nel pannello, sotto *Completed Jobs*); si ferma con **Ctrl+C**. Senza worker le e-mail restano in coda e non arrivano in Mailpit. Dopo un `git checkout` o un `git pull` fermalo e riavvialo, perché non legge il codice nuovo |
| Pannello delle code, Horizon (PR-08) | **http://localhost:8080/horizon**, collegato come admin (gestore e agente: 403; nessuna voce nel menu). Mostra le code `documents`, `mail` e `default`, i lavori recenti e quelli falliti |
| Vedere le ultime e-mail del registro (PR-08) | `sail artisan notifications:list` (filtri: `--status=failed`, `--user=<e-mail>`, `--limit=50`): tipo, destinatario, stato (In coda, Inviata, Non inviata), tentativi ed errore, mai il contenuto |
| Pulire il registro delle e-mail (PR-08) | `sail artisan notifications:prune`: cancella le righe più vecchie di 12 mesi. In produzione gira ogni domenica alle 4:00 |
| Provare "Password dimenticata?" (PR-08) | Dalla pagina di accesso, in una finestra anonima: il link arriva in Mailpit solo agli utenti attivi con verifica in due passaggi; il messaggio della pagina è sempre lo stesso. Al massimo 5 richieste ogni 15 minuti dallo stesso indirizzo IP e 3 link all'ora per indirizzo e-mail |
| Spegnere la VM | `sudo poweroff` (oppure "Salva lo stato" da VirtualBox) |

Utenti di prova creati da `migrate:fresh --seed` (dati fittizi, solo in locale): 2 gestori (`gestore1@`, `gestore2@example.test`) e 3 agenti (`agente1@`…`agente3@example.test`, il terzo disattivato). L'admin non è tra questi: lo crei tu con `users:create-admin`. Password e verifica in due passaggi: vedi §3, "Utenti di prova con password e chiave note".

💡 Prima di provare una PR che cambia molto, fai un'**istantanea** della VM da VirtualBox (*Macchina* → *Istantanea*): se qualcosa va storto torni indietro in un clic.

## 5. Problemi noti

| Sintomo | Causa | Soluzione |
|---|---|---|
| `ssh: kex_exchange_identification … Connection aborted` | Il server SSH non è installato o non è avviato nella VM | Dalla finestra della VM: `sudo apt install -y openssh-server && sudo systemctl enable --now ssh` |
| `Temporary failure resolving 'archive.ubuntu.com'` | DNS della VM non configurato | `sudo mkdir -p /etc/systemd/resolved.conf.d && printf "[Resolve]\nDNS=1.1.1.1 8.8.8.8\n" \| sudo tee /etc/systemd/resolved.conf.d/dns.conf && sudo systemctl restart systemd-resolved` |
| `ip -br a` non mostra `10.0.2.15` | Rete non configurata durante l'installazione | In `/etc/netplan/*.yaml` impostare `dhcp4: true` sull'interfaccia `enp0s3`, poi `sudo netplan apply` |
| `Release file … is not valid yet` | Orologio della VM indietro | `sudo date -s "AAAA-MM-GG HH:MM:SS"` con l'ora attuale, poi `sudo apt install -y chrony && sudo systemctl enable --now chrony` (dalla 25.10 Ubuntu usa chrony, non `systemd-timesyncd`) |
| La VM è molto lenta (tartaruga verde in basso) | Hyper-V attivo su Windows | Vedi §1, punto 2 |
| `sail artisan migrate` si ferma con `The users table contains rows` | La PR-02 rifà la tabella `users` e nel database c'è ancora l'utente di prova dello scheletro | `sail artisan migrate:fresh --seed`, poi `sail artisan users:create-admin` |
| L'admin creato prima della PR-03 non riesce più ad accedere | Dalla PR-03 le password sono in Argon2id (D-214) e quelle salvate prima non sono più accettate | `sail artisan migrate:fresh --seed`, poi `sail artisan users:create-admin` |
| Il codice dell'app viene sempre rifiutato | L'orologio della VM non è sincronizzato: i codici dipendono dall'ora | `timedatectl` deve mostrare `System clock synchronized: yes`; altrimenti vedi la riga `Release file … is not valid yet` |
| `sail artisan test` riporta 3 test falliti in `tests/Database/AuditUsersScriptTest.php` (righe 72 e 100, `DROP USER`) invece che saltati | Sulla VM l'utente di Sail non può creare né cancellare utenti MariaDB, e il test provava a cancellarli anche dopo aver deciso di saltare | Corretto con la PR-07: da quel branch i 3 test risultano *skipped*, come previsto (`docs/dev/database-utenti.md` §4) |
| `DEV_USERS_TOTP_SECRET must be a base32 key…` durante il seed | La chiave contiene caratteri diversi da A-Z e 2-7 o è più corta di 16 | Rigenera la chiave come al §3 |
| Dopo 2 ore di inattività il portale torna al login | Scadenza della sessione per ruolo (D-214): 2 ore per admin e gestore, 8 ore per l'agente | Comportamento previsto: accedi di nuovo |
| Codici di recupero dell'admin persi o smartphone cambiato | I codici si vedono una sola volta, dopo la configurazione della verifica | `sail artisan users:reset-two-factor <e-mail-dell-admin>` e conferma con `yes`; poi elimina la voce dall'app e riconfigura la verifica al login successivo (PR-04, D-276) |
| Il link dell'e-mail (attivazione o nuova password) porta alla dashboard invece che alla pagina del link | Nel browser sei già collegato con un altro utente: le pagine dei link sono solo per chi non è collegato | Apri il link in una **finestra anonima** (Ctrl+Maiusc+N) oppure esci prima dal portale |
| Il link dell'e-mail mostra "Link non valido" | Il link è scaduto (48 ore), è già stato usato, oppure ne è stato inviato uno più recente | Dalla scheda dell'utente: "Invia di nuovo il link" (utente da attivare) o di nuovo il reset |
| Mailpit resta vuoto dopo un'operazione che invia un'e-mail (PR-08) | Il worker delle code non è avviato: le e-mail sono in coda su Redis | Avvia `sail artisan horizon` in un secondo terminale: le e-mail in attesa partono subito. `sail artisan notifications:list` le mostra "In coda" finché il worker non le invia |
| Il worker non vede una modifica appena scaricata (PR-08) | Horizon carica il codice all'avvio | Ferma il worker con Ctrl+C e rilancia `sail artisan horizon` |
| `Class OwnedRecord located in ./tests/Feature/Authorization/OwnerScopeTest.php …` durante `sail composer install` | Avviso dell'autoload su una classe di prova definita dentro un test | Nessuna: non ha effetti |

## 6. Più avanti

- **Font Interstate** e **Chromium**: arrivano con la fetta S1. La PR di S1 aggiungerà il servizio Chromium a `compose.yaml` e la cartella dei font da indicare in `DOCUMENT_FONTS_PATH`, fuori dal repository. I file dei font non vanno mai nel repository.
- **Demo a Infront (gennaio 2027)**: dal tuo PC, con `sail artisan migrate:fresh --seed` per partire da dati di prova puliti.
- **Staging su server**: quando l'ambiente R1–R7 sarà pronto (entro il 26/2/2027 per la milestone B), si attiva il deploy automatico della PR-09 (`docs/dev/ci.md` §2).
