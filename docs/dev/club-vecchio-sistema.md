# Club dal vecchio sistema (F1-06)

Stato: approvato (D-229, D-318, D-329). Comando una tantum: crea i club del vecchio sistema, con gli alias, i colori e i riferimenti `idoc`. Iscrizioni, stadi e loghi **non** si importano: si inseriscono a mano (M11, D-230).

## 1. Estrazione dal dump (sul computer di Antonio)

Il dump resta fuori dal repository e fuori dal server (D-318). Sul database dove è caricato, esegui questa query:

```sql
SELECT id_team, team_name, alias, main_color, team_top, deleted, team_category
FROM idoc__teams_list
ORDER BY id_team;
```

Esporta il risultato in **CSV**:
- con la riga di intestazione;
- separatore `,` oppure `;`;
- codifica UTF-8.

Le colonne devono avere esattamente questi nomi, in qualsiasi ordine. Le entità HTML del vecchio sistema (`&Agrave;`…) le converte il comando.

## 2. Esecuzione

1. Copia il file in `storage/app/private/` del portale, per esempio `storage/app/private/club-idoc.csv`. La cartella è esclusa da git: il file non va mai committato.
2. Fai prima una prova senza salvare:

   ```bash
   ./vendor/bin/sail artisan sports:import-legacy-clubs storage/app/private/club-idoc.csv --merge=21,35 --dry-run
   ```
3. Leggi il report: club creati, club già presenti, alias, riferimenti e anomalie.
4. Se va bene, rilancia senza `--dry-run`. Poi cancella il file.

## 3. Regole

- **Righe con lo stesso nome normalizzato** formano un solo club: Frosinone 11/39, Atalanta 8/20, Roma 25/36.
- **Altri duplicati** si uniscono con `--merge=id,id`, da ripetere per ogni gruppo. Bologna 21/35 è scritto `xxx__BOLOGNA__xxx`.
- **Riga principale** del gruppo: la prima con `deleted = 0`, altrimenti la prima per id.
- **`deleted`**: `0` = club Infront; `1` = avversario, attivo (M11).
- **Codice**: dall'alias, se c'è, altrimenti dal nome; in maiuscolo, senza accenti, con `_` al posto degli spazi. Per esempio alias "Lazio" → `LAZIO`. **Nome breve** = alias, oppure il nome (D-329).
- **Codice ripetuto o non valido**: il comando **non scrive nulla** ed elenca i casi. Si sistema correggendo l'alias nel CSV o unendo i club con `--merge`.
- **Colore**: `r,g,b` → `#RRGGBB`. `0,0,0`, il valore predefinito del vecchio sistema, vuol dire nessun colore.
- **Alias**: nomi e alias delle righe del gruppo diventano alias con origine `legacy`. Fanno eccezione il nome del club stesso e i nomi che indicano già un altro club, segnalati come anomalia.
- **Riferimenti**: ogni `id_team` diventa un riferimento `idoc` in `external_references`.
- **Top match**: il flag segue solo l'elenco di D-257: LAZIO, INTER, MILAN, JUVENTUS, ROMA, NAPOLI. Il report segnala le differenze con `team_top` e i club dell'elenco che mancano.
- **Club già presenti**, riconosciuti dal riferimento `idoc` o dal nome normalizzato: non si modificano. Si aggiungono solo alias e riferimenti mancanti, quindi il comando si può rilanciare.
- **Anomalie**: la categoria 3, che non esiste nel vecchio sistema, finisce nel report.
- **Transazione unica** e **un solo evento di audit** `club.legacy_imported`, con autore "sistema" e i conteggi.
