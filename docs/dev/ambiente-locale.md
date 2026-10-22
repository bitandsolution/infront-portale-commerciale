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

Fino all'accesso vero (PR-03) puoi entrare come un qualsiasi utente con l'**accesso di sviluppo** (D-268): imposta `DEV_LOGIN=true` nel `.env` della VM (`sed -i 's#^DEV_LOGIN=.*#DEV_LOGIN=true#' .env`) e apri **http://localhost:8080/sviluppo/accesso**. Funziona solo con `APP_ENV=local`: su staging e produzione la pagina non esiste.

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
| Entrare come un utente (fino alla PR-03) | `DEV_LOGIN=true` nel `.env`, poi **http://localhost:8080/sviluppo/accesso** |

Utenti di prova creati da `migrate:fresh --seed` (dati fittizi, solo in locale): 2 gestori (`gestore1@`, `gestore2@example.test`) e 3 agenti (`agente1@`…`agente3@example.test`, il terzo disattivato). L'admin non è tra questi: lo crei tu con `users:create-admin`.
| Spegnere la VM | `sudo poweroff` (oppure "Salva lo stato" da VirtualBox) |

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
| `/sviluppo/accesso` risponde 404 | `DEV_LOGIN` non è `true` nel `.env`, oppure `APP_ENV` non è `local` | Correggi il `.env`, poi `sail artisan config:clear` |

## 6. Più avanti

- **Font Interstate** e **Chromium**: arrivano con la fetta S1. La PR di S1 aggiungerà il servizio Chromium a `compose.yaml` e la cartella dei font da indicare in `DOCUMENT_FONTS_PATH`, fuori dal repository. I file dei font non vanno mai nel repository.
- **Demo a Infront (gennaio 2027)**: dal tuo PC, con `sail artisan migrate:fresh --seed` per partire da dati di prova puliti.
- **Staging su server**: quando l'ambiente R1–R7 sarà pronto (entro il 26/2/2027 per la milestone B), si attiva il deploy automatico della PR-09 (`docs/dev/ci.md` §2).
