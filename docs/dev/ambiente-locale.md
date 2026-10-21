# Ambiente locale di collaudo (VirtualBox su Windows)

Stato: ✅ D-261. Serve ad Antonio per provare ogni PR finché lo staging su server non è disponibile.
Tempo della prima installazione: circa un'ora.

Regole:
- solo **dati fittizi** (seeder del repository); il dump del vecchio sistema non entra nella VM;
- la VM **non si espone su internet** (niente port forwarding sul router, niente ngrok o tunnel simili);
- il repository si clona **dentro la VM**, non in una cartella condivisa con Windows, che è lenta e dà problemi di permessi.

## 1. Creare la VM

1. Installa **VirtualBox 7** da virtualbox.org e scarica l'immagine **Ubuntu Server 24.04 LTS** (ubuntu.com/download/server).
2. ⚠️ Se su Windows sono attivi Hyper-V, WSL 2 o Docker Desktop, VirtualBox funziona lo stesso ma più lento (icona della tartaruga in basso a destra). Se la VM è troppo lenta, disattiva "Piattaforma macchina virtuale" e "Hyper-V" in *Attiva o disattiva funzionalità di Windows* e riavvia.
3. Nuova macchina virtuale:

   | Voce | Valore |
   |---|---|
   | Nome | `infront-portale` |
   | Immagine ISO | Ubuntu Server 24.04 LTS |
   | Processori | 4 |
   | Memoria | 8192 MB |
   | Disco | 40 GB, allocazione dinamica |
   | Rete | NAT |

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

Chiave per GitHub (il repository è privato):

```bash
ssh-keygen -t ed25519 -C "vm-infront-portale"
cat ~/.ssh/id_ed25519.pub
```

Copia la chiave in GitHub → *Settings* → *SSH and GPG keys* → *New SSH key*.

## 3. Prima installazione del portale

```bash
git clone git@github.com:bitandsolution/infront-portale-commerciale.git
cd infront-portale-commerciale
cp .env.example .env
sed -i 's#^APP_URL=.*#APP_URL=http://localhost:8080#' .env

# Dipendenze PHP con il container di Sail: nella VM non serve installare PHP
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install

echo "alias sail='./vendor/bin/sail'" >> ~/.bashrc && source ~/.bashrc

sail up -d                     # la prima volta costruisce l'immagine: 5-10 minuti
sail artisan key:generate
sail artisan migrate --seed
sail npm ci
sail npm run build
```

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
| Ricreare il database con i dati di prova | `sail artisan migrate:fresh --seed` |
| Spegnere la VM | `sudo poweroff` (oppure "Salva lo stato" da VirtualBox) |

💡 Prima di provare una PR che cambia molto, fai un'**istantanea** della VM da VirtualBox (*Macchina* → *Istantanea*): se qualcosa va storto torni indietro in un clic.

## 5. Più avanti

- **Font Interstate** e **Chromium**: arrivano con la fetta S1. La PR di S1 aggiungerà il servizio Chromium a `compose.yaml` e la cartella dei font da indicare in `DOCUMENT_FONTS_PATH`, fuori dal repository. I file dei font non vanno mai nel repository.
- **Demo a Infront (gennaio 2027)**: dal tuo PC, con `sail artisan migrate:fresh --seed` per partire da dati di prova puliti.
- **Staging su server**: quando l'ambiente R1–R7 sarà pronto (entro il 26/2/2027 per la milestone B), si attiva il deploy automatico della PR-09 (`docs/dev/ci.md` §2).
