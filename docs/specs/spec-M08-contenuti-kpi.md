# Specifica funzionale: M08 Contenuti club, KPI e configuratore slide

| Campo | Valore |
|---|---|
| Codice | M08 |
| Nome | Contenuti club, KPI e configuratore slide |
| Versione | 1.2 (approvata, D-119) |
| Data | 2026-10-10 |
| Decisioni di riferimento | D-003, D-036, D-049, D-079, D-107…D-118, D-124 |

Legenda: ✅ confermato (con riferimento) · 💡 proposta · ❓ da chiarire · ⚠️ rischio

> **Note di allineamento (repository, 2026-10-21)**
> - Stato corretto in "approvata" (D-119); nel Project l'intestazione riportava ancora "bozza".
> - **F-07** (copia di slide, condizioni e KPI dalla stagione precedente): spostata nel **rilascio 1.1** (D-236).
> - Modello: condizioni a codici stabili, OR nello stesso tipo e AND tra tipi diversi; testi fissi dei layout per lingua (D-202); KPI come definizioni + valori con fonte, periodo e data di aggiornamento (D-203); i primi valori arrivano dal vecchio sistema dopo verifica (D-206).
> - **Layout dinamici (§4.3)**: misure, font e regole in `docs/layout-dinamici.md` (D-226, D-254). Decisioni che integrano questa specifica:
>   - opzioni per slide in `slides.layout_options` (riquadro del logo, colori) e sfondi standard per KPI club e digital club (D-250);
>   - con condizione sulla competizione, i dati delle slide network considerano solo i club di quella competizione (D-251);
>   - copertina con logo del cliente e data di approvazione, **senza ragione sociale** (D-252).
> - Font: Interstate Regular, Bold e Black (D-249).

---

## 1. Descrizione
Il modulo raccoglie i contenuti che compongono i documenti commerciali secondo il **modello ibrido** (✅ D-107):
- **dati strutturati** per KPI, digital, fonti e identità club;
- **slide caricate** in PNG 1920×1080 (✅ D-112), gestite da un **configuratore delle slide** (✅ D-115). Ogni slide porta con sé le condizioni che la fanno entrare in un documento: in generazione il sistema seleziona solo le slide coerenti con la proposta (per esempio, solo le slide "Top Partner" se il livello scelto è Top).

## 2. Obiettivi
- Eliminare i deck copiati di stagione in stagione e i refusi tra club.
- Comporre in automatico il documento giusto per ogni combinazione di club, livello, format e hospitality.
- Aggiornare un KPI in un solo punto, con effetto su tutti i nuovi documenti.
- Calcolare in automatico le aggregazioni del documento network.

## 3. Attori
| Attore | Ruolo |
|---|---|
| Admin | Gestione completa (✅ D-109) |
| Gestore | Gestione completa di slide, contenuti e KPI (✅ D-109) |
| Agente | Nessun accesso; riceve il documento composto |

## 4. Configuratore delle slide (✅ D-115)

### 4.1 Attributi di una slide
| Attributo | Valori | Note |
|---|---|---|
| Ambito | Infront · Club (quale) | |
| Stagione | es. 2026-27 | Copiabile dalla stagione precedente |
| Tipo documento | Network · Club · Entrambi | |
| Sezione | es. Introduzione, Club, Partnership, Benefit, Brand exposure, Recap, Chiusura | Raggruppa e ordina |
| Ordine | Numero | Posizione nella sezione |
| **Tipo slide** | **Statica** · **Dinamica** | ✅ D-116 |
| Immagine | PNG 1920×1080 | Per le dinamiche è lo sfondo grafico |
| Layout dati (solo dinamiche) | Uno dei layout predefiniti (§4.3) | ✅ D-116 |
| **Condizioni di inclusione** | Vedi §4.2 | Nessuna condizione = sempre inclusa |
| Lingua | IT (unica attiva) · EN (predisposta) | ✅ D-124 |
| Attiva | Sì / No | |

### 4.2 Condizioni di inclusione (💡)
Una slide entra nel documento se **tutte** le condizioni impostate sono vere. Per ogni condizione si possono scegliere **uno o più valori**, in alternativa tra loro: per esempio "livello = Top **o** Premium".

| Condizione | Valori possibili | Esempio d'uso |
|---|---|---|
| Livello partnership | Libera, Top, Premium, Official, Official 2.0 | Slide benefit "Top Partner" solo se il livello è Top |
| Partnership presente | Sì / No | Slide "Partnership – IP rights" solo se c'è un livello diverso da Libera |
| Format LED | Jumbo, Domination, 1ª fila, 2ª fila | Slide "Domination" solo se il format scelto è Domination |
| LED presente | Sì / No | Slide "Brand exposure LED" solo se c'è un format LED |
| Hospitality presente | Sì / No | Slide "Ticketing & Hospitality" solo se ci sono posti |
| Competizione | Serie A, Serie B, Serie C | Slide KPI "Infront Football Network Serie B" solo se nella proposta c'è un club di Serie B |

Nel documento **network**, le condizioni sulle slide Infront si valutano sull'insieme dei club: è vera se **almeno un club** della proposta la soddisfa (✅ D-118). Esempio: Lazio Top + Genoa Premium → slide benefit Top e Premium.

**Slide dei benefit** (✅ D-117): sono **immagini statiche** per club e livello, con condizione sul livello, nel rispetto della linea grafica e comunicativa attuale. Non vengono generate da testo.

### 4.3 Layout dinamici predefiniti (✅ D-116)
Tutte le slide mantengono la struttura grafica attuale. Le slide dinamiche usano layout definiti in sviluppo: posizione, font e stile dei dati sono fissi per layout. L'admin carica solo lo sfondo grafico. Misure e regole di dettaglio in `docs/layout-dinamici.md`.

| Layout | Dati scritti dal sistema | Documento |
|---|---|---|
| Copertina | Logo cliente, data di approvazione della versione; stagione nello sfondo (D-252) | Entrambi |
| Club coinvolti | Loghi dei club della proposta | Network |
| KPI network | Sommatoria tifosi, audience live, spettatori stadio, con fonti | Network |
| KPI campagna | Mesi, club, partite, spot totali, top match (solo Serie A) | Network |
| Digital key figures | Follower per social e per club, totali | Network |
| KPI club | Fanbase, tifosi medi, presenze, audience, abbonati, trasmissioni | Club |
| Digital club | Follower per social, utenti digitali, DEM | Club |
| Recap economico network | Tabella club / format / partite / min per partita / spot per partita / prezzo; totale di listino, sconto, totale finale | Network |
| Recap economico club | Partnership, LED, hospitality a listino; totale, sconto, totale finale | Club |

## 5. Dati strutturati

| Blocco | Livello | Contenuto |
|---|---|---|
| KPI club | Club × stagione | ✅ D-108: fanbase Italia, tifosi medi a partita, presenze annuali, audience casalinga cumulata, trasmissioni dedicate, abbonati |
| Digital club | Club × stagione | ✅ D-108: follower Facebook, Instagram, X, YouTube, TikTok; utenti digitali; contatti DEM |
| Fonti e note | Per KPI | Fonte, periodo di riferimento, data di aggiornamento; note standard (es. squadre retrocesse) |
| Identità club | Club | Logo alta risoluzione (M02), colori principali |

## 6. Funzionalità
| ID | Funzionalità | Stato |
|---|---|---|
| F-01 | Libreria slide per ambito e stagione, con attributi e condizioni (§4) | ✅ D-115 |
| F-02 | Caricamento PNG 1920×1080 con verifica di formato e dimensioni | ✅ D-112 |
| F-03 | Slide dinamiche: scelta del layout e caricamento dello sfondo | ✅ D-116 |
| F-04 | **Simulatore di composizione**: scelti club, livello, format e hospitality, mostra quali slide entrerebbero nel documento e in che ordine | 💡 |
| F-05 | Scheda KPI club e scheda digital club per stagione, con fonte e data di aggiornamento | ✅ D-108 |
| F-06 | Aggregazioni automatiche per il network | ✅ D-108 |
| F-07 | Copia di slide, condizioni e KPI dalla stagione precedente | 💡 — **rilascio 1.1 (D-236)** |
| F-08 | Avviso se un KPI non è aggiornato da oltre 6 mesi | 💡 (✅ D-109 frequenza) |
| F-09 | Controllo di completezza: per ogni club vendibile, presenza di KPI e di almeno una slide per ogni livello e format disponibile a listino | 💡 |
| F-10 | Sezioni Infront del documento network uguali per tutti i documenti della stagione | ✅ D-114 |

## 7. Flussi utente
**FL-01 Preparazione di un club per la stagione**
1. Il gestore copia le slide della stagione precedente.
2. Sostituisce le immagini aggiornate.
3. Verifica le condizioni: per esempio le slide benefit Top, Premium e Official, ciascuna con il proprio livello.
4. Aggiorna KPI e digital.
5. Usa il simulatore (F-04) per verificare la composizione con "Lazio, Top, Domination, hospitality sì".

**FL-02 Aggiornamento periodico dei KPI (ogni 4-6 mesi, ✅ D-109)**
1. Aggiornamento dei valori, con data registrata in automatico.
2. Effetto sui nuovi documenti. I documenti già generati restano invariati (✅ D-111).

## 8. Regole di business
| ID | Regola | Stato |
|---|---|---|
| RB-01 | Una slide entra nel documento se tutte le sue condizioni sono vere; condizioni assenti = sempre inclusa | ✅ D-115 |
| RB-02 | Nel network, una condizione su slide Infront è vera se almeno un club la soddisfa | ✅ D-118 |
| RB-03 | Ordine del documento = ordine delle sezioni, poi ordine delle slide nella sezione | 💡 |
| RB-04 | Le slide dinamiche mostrano i dati della proposta approvata (ultima versione) e i KPI in vigore alla generazione, salvati in snapshot | ✅ D-090, D-111 |
| RB-05 | Aggregazioni network: somma solo dei club con valore presente; club mancanti segnalati | 💡 |
| RB-06 | Top match solo per la Serie A | ✅ D-036 |
| RB-07 | Sezioni Infront del network uguali per tutti i documenti | ✅ D-114 |
| RB-08 | Immagini di proprietà Infront: nessun vincolo di diritti | ✅ D-110 |
| RB-10 | Le slide dei benefit sono statiche per livello; i testi benefit di M03 non entrano nei documenti | ✅ D-117 |
| RB-09 | 💡 Le slide statiche non devono contenere KPI scritti nella grafica (invecchiano): i numeri vanno nelle slide dinamiche | 💡 |

## 9. Validazioni
- Immagini: PNG, 1920×1080 esatti; 💡 massimo 10 MB per file.
- Slide dinamica: layout obbligatorio.
- KPI: numeri ≥ 0, valori assoluti (la formattazione "1 MLN", "29K" la applica il layout).
- Colori: codici esadecimali validi.

## 10. Eccezioni
| Caso | Gestione |
|---|---|
| Nessuna slide per una combinazione (es. Official 2.0 senza slide benefit) | Avviso di completezza (F-09); in generazione il documento si crea comunque, con avviso a chi genera |
| Due slide con stesse condizioni e stesso ordine | Ordinamento per data di caricamento; avviso in libreria |
| Club senza KPI in una proposta network | Aggregazione sui club disponibili, con avviso (RB-05) |
| Club promosso senza contenuti per la stagione | Avviso di completezza; 💡 possibilità di copiare dalla stagione precedente |

## 11. Dipendenze
- **Dipende da:**
  - M02 (club, stagioni, competizioni, loghi, top match);
  - M03 (livelli, format, benefit);
  - M05 (logo cliente).
- **Usato da:**
  - M09 (composizione e generazione del PDF ottimizzato per il web, ✅ D-113);
  - futura landing web (D-079).

## 12. Criteri di accettazione
1. Una proposta Lazio con livello Top include le slide benefit Top ed esclude quelle Premium e Official.
2. Una proposta senza hospitality non include le slide con condizione "Hospitality presente = Sì".
3. Una slide con condizione "Livello = Top o Premium" è inclusa per entrambi i livelli.
4. In una proposta network Bologna (Serie A) + Empoli (Serie B) sono incluse le slide KPI Serie A e Serie B; in una proposta solo Serie A la slide Serie B è esclusa.
5. Il simulatore mostra per una combinazione l'elenco ordinato delle slide che entrerebbero nel documento.
6. Una slide dinamica "Recap economico club" mostra listino, sconto e totale finale della proposta approvata.
7. Aggiornando un KPI, i documenti già generati non cambiano.
8. Un PNG 1600×900 viene rifiutato.

## 13. Questioni aperte
Nessuna. Le slide si preparano come oggi (linea grafica Infront e club) ed esportate in PNG 1920×1080.
