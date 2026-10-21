# Specifica funzionale: M09 Generazione documentale

| Campo | Valore |
|---|---|
| Codice | M09 |
| Nome | Generazione documentale |
| Versione | 1.0 (approvata, D-125) |
| Data | 2026-10-10 |
| Decisioni di riferimento | D-036, D-041, D-079, D-090, D-096, D-103, D-111…D-118, D-120…D-124 |

Legenda: ✅ confermato (con riferimento) · 💡 proposta · ❓ da chiarire · ⚠️ rischio

> **Note di allineamento (repository, 2026-10-21)**
> - Pipeline: HTML 1920×1080 → Chromium headless (Browsershot) senza rete → qpdf; JPEG q85 convertiti una volta e riusati; code con tentativi e lavori idempotenti; massimo 2 documenti in parallelo (D-220, D-222).
> - Snapshot dei KPI e modello di pagine salvati sul documento (D-204); alla rigenerazione il documento precedente resta come "sostituito", scaricabile solo dall'admin (D-205).
> - E-mail di approvazione dopo il documento (D-221).
> - Font Interstate Regular, Bold e Black, caricati da file (D-249); misure dei layout in `docs/layout-dinamici.md` (D-226, D-254).
> - CA 3 aggiornato da D-252: la copertina non riporta la ragione sociale.

---

## 1. Descrizione
Il modulo produce il documento commerciale di una proposta **approvata** (✅ D-090) e lo archivia. Mette insieme:
- le slide statiche e dinamiche del configuratore delle slide (M08);
- i dati dell'ultima versione della proposta (M06/M07);
- lo snapshot dei KPI (✅ D-111).

L'output è un **PDF 16:9 ottimizzato per il web** (✅ D-113, D-122).

## 2. Obiettivi
- Generare in automatico un documento conforme alla linea grafica Infront e dei club, senza interventi manuali.
- Rendere i documenti leggeri e veloci da aprire anche da web e da mobile.
- Mantenere un archivio di tutte le versioni.
- Predisporre l'architettura a evoluzioni già note: PPTX, landing web, inglese.

## 3. Attori
| Attore | Ruolo |
|---|---|
| Agente | Scarica i documenti delle proprie proposte approvate |
| Gestore | Scarica tutti i documenti |
| Admin | Scarica tutti i documenti; 💡 forza la rigenerazione |

## 4. Tipi di documento (✅ D-121)

| Proposta | Documento | Slide usate |
|---|---|---|
| 1 club | **Documento club** (template del club, co-branded Infront) | Slide del club con tipo "Club" o "Entrambi" e condizioni vere |
| 2 o più club | **Documento network** (template Infront) | Slide Infront con tipo "Network" o "Entrambi" e condizioni vere su almeno un club (✅ D-118) |

Esempio: Lazio Top + Genoa Premium → documento network con le slide benefit Top e Premium.

## 5. Funzionalità
| ID | Funzionalità | Stato |
|---|---|---|
| F-01 | Generazione automatica all'approvazione e a ogni nuova versione | ✅ D-120 |
| F-02 | Composizione: selezione delle slide per ambito, stagione, tipo documento, lingua e condizioni; ordinamento per sezione e posizione | ✅ D-115 |
| F-03 | Scrittura dei dati sui layout dinamici (copertina, KPI, digital, recap economico…) | ✅ D-116 |
| F-04 | Ottimizzazione per il web (§7) | ✅ D-113 |
| F-05 | Archivio dei documenti per proposta e versione; ultima versione evidenziata | ✅ D-120 |
| F-06 | Download da scheda proposta (agente: solo proprie) | ✅ D-090 |
| F-07 | Rigenerazione manuale (admin), per esempio dopo la correzione di una slide; sostituisce il documento della stessa versione, con traccia nell'audit | ✅ D-125 |
| F-08 | Avviso se mancano slide per una combinazione (completezza M08) | 💡 |
| F-09 | **Predisposizione** export PPTX lato admin (non attivo) | ✅ D-122 |
| F-10 | **Predisposizione** output web/landing (non attivo) | ✅ D-123 |
| F-11 | **Predisposizione** lingua inglese (non attiva) | ✅ D-124 |

## 6. Flusso di generazione
1. **Evento:** approvazione o nuova versione (M07), oppure rigenerazione manuale dell'admin.
2. **Tipo documento:** club o network, in base al numero di club (D-121).
3. **Selezione slide:** ambito, stagione della proposta, tipo documento, lingua (IT) e condizioni (M08 §4.2).
4. **Ordinamento:** per sezione, poi per posizione.
5. **Dati:** dalla versione corrente (prezzi congelati, sconto sul totale, D-103) e dallo snapshot KPI, salvato al momento della generazione.
6. **Composizione** delle pagine in un **modello indipendente dal formato** (💡 D-123): pagina, sfondo, elementi testuali e grafici.
7. **Rendering PDF** dal modello e **ottimizzazione** (§7).
8. **Archiviazione:** il file è collegato a proposta e versione; l'agente viene avvisato tramite la notifica di approvazione già prevista in M07.

⚠️ Il passaggio 6 è la chiave delle predisposizioni: dallo stesso modello di pagine si potranno poi produrre PPTX (D-122) e la pagina web (D-123) senza rifare la composizione.

## 7. Ottimizzazione per il web (✅ D-113, parametri 💡)
| Intervento | Valore proposto |
|---|---|
| Conversione delle slide PNG nel PDF | JPEG qualità ~85%, risoluzione 1920×1080 mantenuta |
| Testi dinamici | Vettoriali (nitidi e selezionabili) |
| Font | Incorporati, solo i caratteri usati (subset) |
| Linearizzazione | Sì ("fast web view": apertura progressiva da browser) |
| Metadati | Titolo, autore "Infront Italy", stagione |
| Peso indicativo | 5–15 MB per circa 40 slide |

## 8. Regole di business
| ID | Regola | Stato |
|---|---|---|
| RB-01 | Si genera solo per proposte approvate; mai per bozze, proposte in attesa o rifiutate | ✅ D-090 |
| RB-02 | 1 club → club; ≥ 2 club → network | ✅ D-121 |
| RB-03 | Ogni versione approvata ha il proprio documento; i precedenti restano in archivio | ✅ D-120 |
| RB-04 | I KPI del documento sono quelli in vigore alla generazione, salvati in snapshot; non cambiano con aggiornamenti successivi | ✅ D-111 |
| RB-05 | Il recap economico mostra righe per club a listino, totale di listino, sconto e totale finale | ✅ D-103 |
| RB-06 | Top match solo per la Serie A | ✅ D-036 |
| RB-07 | Le modifiche successive alle slide non alterano i documenti archiviati; la rigenerazione è solo manuale (admin) | 💡 |
| RB-08 | Documento di proposta annullata: resta in archivio, con lo stato "Annullata" mostrato in piattaforma (non nel PDF) | 💡 |
| RB-09 | Nome file: `Infront_Proposta_[Cliente]_[Club o Network]_[Stagione]_v[n].pdf` | 💡 |
| RB-10 | Valore KPI mancante su slide dinamica: "n.d." | 💡 |
| RB-11 | Solo PDF e solo italiano nell'MVP | ✅ D-122, D-124 |

## 9. Validazioni e controlli
- Prima della generazione: la proposta è approvata e la stagione ha slide attive per il tipo di documento.
- Dopo la generazione: il PDF esiste, il numero di pagine è uguale al numero di slide selezionate e il peso è sotto la soglia (💡 avviso oltre 20 MB).

## 10. Eccezioni
| Caso | Gestione |
|---|---|
| Nessuna slide trovata per il tipo di documento | Generazione bloccata; avviso ad admin e gestore |
| Slide mancanti per alcune condizioni | Documento generato; avviso (F-08) |
| Errore di generazione (es. immagine corrotta) | Nuovo tentativo automatico; se fallisce, avviso all'admin; l'approvazione resta valida |
| Proposta molto grande (es. 10 club) | 💡 Generazione in background, documento disponibile al termine |

## 11. Dipendenze
- **Dipende da:**
  - M06/M07 (dati e versioni della proposta);
  - M08 (slide, layout, KPI);
  - M02 (loghi, top match);
  - M05 (logo e ragione sociale del cliente).
- **Usato da:**
  - M10 (statistiche sui documenti);
  - futura landing web (D-079, D-123).

## 12. Criteri di accettazione
1. All'approvazione di una proposta Udinese (1 club) si genera un documento club con template Udinese; per Bologna + Como si genera un documento network.
2. Per una proposta Lazio Top il documento contiene le slide benefit Top ed esclude Premium e Official.
3. La copertina riporta il logo del cliente e la data di approvazione della versione (D-252); il recap mostra listino, sconto e totale finale corretti.
4. Un deck di circa 40 slide pesa al massimo 15 MB e la prima pagina si apre in modo progressivo da browser.
5. Dopo una modifica dell'admin (versione 2) si genera un nuovo documento; quello della versione 1 resta scaricabile.
6. L'agente non può scaricare documenti di proposte di altri agenti, né documenti di bozze o proposte in attesa.
7. Aggiornando un KPI, il documento già generato non cambia; una rigenerazione manuale dell'admin usa i nuovi valori.

## 13. Questioni aperte
- 💡 Parametri di ottimizzazione (§7): da validare con un test su un deck reale in sviluppo (fetta S1, D-239).
- 💡 Controllo del numero di pagine (§9): le slide di continuazione delle tabelle (`docs/layout-dinamici.md` §4.5, §4.8) si contano nel totale atteso.
