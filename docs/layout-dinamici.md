# Layout dinamici — verifica sui documenti di esempio (D-226)

Stato: **✅ approvato** (blocco 6.3, D-249…D-254) · 2026-10-21. Le indicazioni marcate 💡 in questo documento sono approvate con D-254.
Riferimenti: spec-M08 §4.3, spec-M09 §6–7, progettazione-tecnica §5.5.3, D-116, D-202, D-204, D-224.

Legenda: ✅ confermato · 💡 proposta · ❓ da chiarire · ⚠️ rischio

Questo documento è il riferimento di sviluppo per i 9 layout dinamici (fette S1 e F7). Le misure sono in **pixel sulla tela 1920×1080** (D-112). I PDF di esempio misurano 1440 × 807,87 pt: 1 pt = 1,3333 px.

---

## 1. Materiale analizzato

| File | Contenuto | Uso |
|---|---|---|
| `pdf_multi-club.pdf` | Documento network Bologna + Como, 15 pagine, FPDF 1.86, 4,5 MB | Misure dei layout network |
| `pdf_singolo-club.pdf` | Documento club Udinese, Premium Partner + Domination 1′30″, 30 pagine, 6,2 MB | Misure dei layout club |
| `ADV Multiclub & Partnership_Infront Network_2026-27.pptx` | Template network, 45 slide | Font, colori, struttura delle slide 37–42 (parte dinamica) |
| `TOP PARTNER - SS Lazio_2026-27.pptx` (struttura identica a Premium e Official) | Template club, 30 slide | Confronto tra la grafica di due club |
| Cartella `Interstate Fonts` | 27 file TTF | Glifi, licenza dichiarata, incorporamento |
| `db_docs2infrontcorporate.sql` (`idoc__calendar`, `idoc__teams_list`) | Calendario Serie A 2026-27 e club | Verifica del conteggio dei top match |

Il vecchio sistema compone ogni pagina da un **JPEG di sfondo 1920×1080** (circa 150–300 KB) più testi e loghi sovrapposti. È lo stesso principio del nostro modello: slide statica oppure sfondo + layout (D-116). Il peso dei PDF (circa 200 KB a pagina) conferma la stima di 5–15 MB per 40 slide (D-113).

---

## 2. Esiti trasversali

### 2.1 Font
| Font usato nei PDF | Dove | Esito |
|---|---|---|
| Interstate **Bold** | Date, nomi dei club, tabelle, KPI di campagna | ✅ File presente, contiene €, accenti italiani, virgolette e trattini tipografici |
| Interstate **Black** | Valori dei KPI network, totali digital, intestazioni di tabella | ✅ Compreso (D-249). File valido presente, con gli stessi glifi |
| **Calibri** e Calibri Bold | Prezzi del recap (listino barrato e totale finale) | ✅ **Sostituito** da Interstate Bold e Black (D-249): font Microsoft, non disponibile su Linux |
| Interstate Regular | Testi nei PPTX, assente dai testi dinamici dei PDF | ✅ Utile per note e testi lunghi |

- Tutti i file Interstate dichiarano l'incorporamento "anteprima e stampa" (fsType 4). È compatibile con un PDF in sola lettura come il nostro. Licenza per l'uso su server **confermata** (D-256).
- ⚠️ Il file Bold e il file Black dichiarano internamente la sottofamiglia "Regular", come il Regular. Se fossero installati come font di sistema, Chromium li confonderebbe. 💡 **Regola**: i font si caricano solo **da file**, con `@font-face` e `font-weight` esplicito (400 Regular, 700 Bold, 900 Black). Non si installano mai nel sistema.
- `Interstate-black-euro.ttf` non è un font valido e non si usa: il simbolo € è già presente nei file originali.
- 💡 I file dei font **non vanno nel repository**, per via della licenza. Stanno in un percorso configurato (`DOCUMENT_FONTS_PATH`), predisposto sul server da Antonio. In CI e nei test si usa un font libero sostitutivo, per esempio DejaVu Sans.

### 2.2 Formati
| Dato | Formato osservato | 💡 Regola per il nuovo sistema |
|---|---|---|
| KPI e follower | "1,1 mln", "19,5 mln", "745 k", "638 k" (minuscolo con spazio); nel PPTX Lazio "1 MLN", "29K", "11,8mln" (incoerente) | **Un solo formato**: ≥ 1.000.000 → una cifra decimale + " mln" ("1,1 mln"); ≥ 1.000 → intero + " k" ("745 k"); sotto 1.000 → intero. Valore mancante → "n.d." (RB-10 M09) |
| Importi | "107.996,00 €" | Formato italiano con 2 decimali e "€" dopo, con uno spazio; nessuna dicitura IVA nel recap (vedi §4, nota IVA) |
| Minuti LED | "1'00"", "28' 30"" | `m'ss"` per partita, `m' ss"` per i totali, da secondi interi (C6) |
| Date | "01/10/2026" | `gg/mm/aaaa` |
| Mesi di campagna | "10 Mesi" + "Agosto 2026 - Maggio 2027" | Mese della prima partita → mese dell'ultima partita del periodo |

### 2.3 Colori
| Elemento | Network | Club (Udinese) |
|---|---|---|
| Intestazione della tabella recap | #CC0033 | #97989B = colore principale del club nel vecchio database (`main_color` "151,152,155") |
| Testi nelle tabelle | #333333 (network) | #000000 |
| Testi su fascia scura o rossa | #FFFFFF | — |

💡 Nel documento club il colore d'accento è il **colore principale del club** (`clubs.primary_color`); nel network è il rosso Infront.

### 2.4 Difetti dei documenti attuali da non riprodurre
1. **Intestazioni della tabella recap illeggibili**: testo rosso su fondo rosso (network) e grigio su grigio (club).
2. **Prezzo di listino barrato sovrapposto** al totale finale nel recap club.
3. **"Total / season"** in inglese nel documento club, "Totale / stagione" nel network.
4. **Numeri inseriti dentro una frase stampata sullo sfondo** (KPI di campagna: "38 partite casalinghe … di cui 10 top matches"), con spaziatura sbagliata ("di cui10").
5. Totale TikTok disallineato di 8 px rispetto agli altri totali digital.
6. Refuso nello sfondo della copertina network ("Partenership").
7. KPI del documento club (tifosi, social) **dentro le immagini statiche**: invecchiano a ogni aggiornamento (RB-09 M08).

---

## 3. Club diversi, grafiche diverse (⚠️ rischio principale)

I documenti club usano la **grafica di ciascun club**, e le posizioni cambiano da un club all'altro:
- logo del cliente in copertina: Udinese [37,536 549×181], network [170,706 324×181]. La copertina Lazio ha un'impaginazione ancora diversa;
- KPI club: Udinese li mostra in un solo riquadro ("600 mila tifosi"), Lazio in una griglia di 6 valori con icone, in blu #083978;
- colori dei testi: blu Lazio #003876, grigio Udinese.

Con posizioni fisse per layout (D-116: "l'admin carica solo lo sfondo") ogni club dovrebbe ridisegnare gli sfondi delle slide dinamiche sulla stessa griglia.

✅ **Decisione (D-250)**: posizioni e stile restano definiti nel codice, ma ogni slide dinamica accetta poche **opzioni controllate**, salvate in una nuova colonna JSON `slides.layout_options`, validata e con valori predefiniti:

| Opzione | Layout | Predefinito |
|---|---|---|
| `logo_box` (x, y, larghezza, altezza) | Copertina | Riquadro del layout |
| `date_color` | Copertina | #FFFFFF |
| `accent_color` | Recap club, KPI club, digital club | `clubs.primary_color` |
| `text_color` | Tutti | Colore del layout |

Il simulatore (M08 F-04) mostra l'anteprima con le opzioni applicate. Così il gestore adatta copertina e colori alla grafica di ogni club senza modificare il codice.

⚠️ Per **KPI club** e **digital club**, oggi statici e diversi per club, serve comunque uno **sfondo standard** con le aree libere nelle posizioni del layout (§4.6, §4.7). Il grafico di Infront lo prepara per ogni club 2027-28 entro il 10/3/2027 (milestone C, D-250).

---

## 4. I 9 layout

Per ogni layout: dati, misure rilevate, regole e limiti di capienza. "Sfondo" indica ciò che è stampato nell'immagine caricata dal gestore: titoli, etichette fisse, fonti, decorazioni.

### 4.1 Copertina (network e club)
| Elemento | Misura (px) | Stile |
|---|---|---|
| Data | Allineata a destra a x = 1849, top 21 | Interstate Bold 17,3 px (13 pt), #FFFFFF |
| Logo del cliente | Network [170,706 324×181] · club Udinese [37,536 549×181] | Contenuto nel riquadro, proporzioni mantenute, centrato |
| Stagione, "Infront Opportunity" | — | Nello sfondo |

- La **ragione sociale non compare** nei documenti attuali: c'è solo il logo. Lo stesso vale per la stagione, già nello sfondo. ✅ La copertina resta così: logo e data, senza ragione sociale (D-252).
- 💡 La data è quella di **approvazione della versione** (`frozen_at`) e non quella di generazione: così la rigenerazione (D-205) non cambia la data del documento.
- 💡 Il logo viene ricodificato al caricamento (D-218). Se il logo ha il fondo bianco, il riquadro bianco dello sfondo network lo contiene già.

### 4.2 Club coinvolti (network)
Nel network di esempio non esiste una slide a sé: i club sono elencati in testa ai KPI network (§4.3). Nel template PPTX la slide "Infront Network" (16, 30, 31) è statica, con tutti i club Infront.

💡 Il layout resta (spec M08 approvata) e mostra loghi e nomi brevi dei club della proposta su una griglia:

| Club nella proposta | Griglia | Logo | Nome |
|---|---|---|---|
| 2–4 | 2 × 2 | 220 px | Interstate Bold 32 px |
| 5–9 | 3 × 3 | 160 px | Interstate Bold 26,7 px |
| 10–16 | 4 × 4 | 120 px | Interstate Bold 21,3 px |

Area utile [113,250 1694×700], celle centrate. Oltre 16 club l'avviso compare nel simulatore e in generazione (M09 F-08).

### 4.3 KPI network (una slide per competizione)
Misure dal PDF (pagina 11, "Infront Football Network Serie A"):

| Elemento | Misura (px) | Stile |
|---|---|---|
| Elenco club | Logo 94×94 a (113,264), nome a x = 230, top 296; secondo club a +510 px (logo 623) | Interstate Bold 26,7 px, #333333 |
| Sommatoria tifosi | Centro x = 1345, top 302 | Interstate Black 93,3 px, #000000 |
| Totale audience live | Centro x = 1345, top 536 | Idem |
| Sommatoria spettatori stadio | Centro x = 1345, top 744 | Idem |
| Etichette e fonti (`*`, `**`) | — | Nello sfondo |

- 💡 Elenco club su **2 colonne** (step 510 px) × fino a 6 righe (step 110 px): massimo 12 club per slide.
- ⚠️ **Ambito dei dati**: le slide KPI network sono una per competizione (condizione `competition_tier`). Nell'esempio Serie A + Serie B escono due slide (CA 4 di M08). ✅ Quando la slide ha una condizione sulla competizione, elenco club e somme considerano **solo i club della proposta in quella competizione**. Senza condizione considerano tutti i club. È un'estensione di D-118 (D-251).
- Somme: solo i club con valore presente; i mancanti vengono segnalati (RB-05 M08).

### 4.4 KPI campagna (network)
| Elemento | Centro x | Top | Stile |
|---|---|---|---|
| "{mesi} Mesi" | 1038 | 332 | Interstate Bold 53,3 px, #FFFFFF |
| "{mese inizio} {anno} - {mese fine} {anno}" | 1038 | 398 | Interstate Bold 26,7 px |
| "{n} Squadre" | 993 | 528 | 53,3 px |
| "{partite} Partite" | 952 | 679 | 53,3 px |
| "Campionato" | 952 | 746 | 26,7 px |
| "{spot} SPOT" | 914 | 830 | 40 px |
| "Passaggi LED totali Stagione {stagione}" | 914 | 883 | 40 px |
| "(format 15 secondi cad.)" | 914 | 942 | 26,7 px |
| Frase laterale "{partite} partite casalinghe … di cui {top} top matches*" | Riquadro [1250,740 600×130] | — | 💡 Interstate Bold 33,3 px, #000000, **scritta tutta dal sistema** (`layout_texts`) e non più numeri inseriti nella frase dello sfondo |

- Le fasce sono inclinate: il centro si sposta di circa 40 px a sinistra per ogni fascia. Le posizioni sopra lo riproducono.
- Calcoli (verificati sull'esempio): partite = somma delle partite di campionato in casa dei club nel periodo (19 + 19 = 38); spot = Σ (secondi a partita / 15 × partite) = 152; mesi = dal mese della prima partita al mese dell'ultima (agosto–maggio = 10).
- Top match solo per la Serie A (D-036). ⚠️ Vedi §5 sulla regola di conteggio.
- ⚠️ Lo sfondo contiene il logo **Serie A**: per una proposta Serie A + Serie B serve uno sfondo neutro, oppure una slide per competizione come per i KPI network. ✅ Stessa regola di ambito di §4.3 (D-251): partite, squadre e top match contano solo i club della competizione della slide.

### 4.5 Digital key figures (network)
| Elemento | Misura (px) | Stile |
|---|---|---|
| Tabella | x da 113 a 1790; colonne 355 + 5 × 264; righe alte 45 | Bordi neri sottili |
| Intestazione | Riga a top 245; nomi delle piattaforme centrati | Interstate Black 24 px, #333333 |
| Righe club | Da top 291; logo 30 px a x = 124; nome a x = 173; valori centrati nella colonna | Interstate Bold 21,3 px, #333333 |
| Totali per piattaforma | Centri x = 285 · 659 · 999 · 1366 · 1710; top 872 | Interstate Black 46,7 px, #333333 |
| Icone ed etichette (LIKES, FOLLOWERS, SUBSCRIBERS) | — | Nello sfondo |

- Capienza: 12 righe club (fino a top 831, prima dei totali). Oltre 12 club la tabella prosegue su una slide di continuazione con lo stesso sfondo, e i totali compaiono solo nell'ultima.
- 💡 Le etichette dei totali restano nello sfondo. Nel documento attuale Facebook e Instagram riportano entrambi "LIKES": va corretto lo sfondo, non il codice.

### 4.6 KPI club (club)
Nei PDF attuali questi valori sono **dentro le immagini statiche**: Udinese "600 mila tifosi"; Lazio con 6 valori (abbonati, tifosi medi, presenze, audience, trasmissioni, fanbase).

💡 Griglia standard 3 × 2 con i 6 KPI club (D-108), da usare come guida per il grafico:

| Posizione | KPI | Riquadro valore (px) |
|---|---|---|
| Riga 1 | Fanbase Italia · Tifosi medi a partita · Presenze annuali | [140,360 500×110] · [710,360 500×110] · [1280,360 500×110] |
| Riga 2 | Audience casalinga cumulata · Trasmissioni dedicate · Abbonati | [140,700 500×110] · [710,700 500×110] · [1280,700 500×110] |

Valori in Interstate Black 72 px, centrati, colore `accent_color`. Icone ed etichette stanno nello sfondo, sotto ogni riquadro (y + 120). Fonti e periodo in nota a piè di pagina [75,1000 1770×40], Interstate Regular 16 px, generate dai campi fonte e periodo dei KPI (D-203).

### 4.7 Digital club (club)
💡 Griglia standard, sul modello della slide 7 del PPTX Lazio:

| Elemento | Riquadro (px) | Stile |
|---|---|---|
| Follower Facebook, Instagram, X, YouTube, TikTok | 5 riquadri 200×90 da x = 960, step 160, top 625 | Interstate Bold 28 px, centrato |
| Utenti digitali | [208,520 680×60] | Interstate Bold 28 px |
| Contatti DEM | [208,637 680×60] | Interstate Bold 28 px |
| Totale fanbase digitale = somma dei social + utenti digitali | [170,837 1595×80] | Interstate Black 56 px, centrato |

Icone ed etichette nello sfondo. ❓ La somma del totale va confermata con Infront: nel PPTX Lazio "3,5 mln" non coincide con la somma dei valori esposti.

### 4.8 Recap economico network
Misure dal PDF (pagina 14):

| Elemento | Misura (px) |
|---|---|
| Colonne | Club 56–510 · Format 510–789 · N. match 789–994 · Min./partita 994–1198 · N. spot/partita 1198–1402 · Proposta economica 1402–1855 |
| Intestazione | Top 226, alta 105, fondo #CC0033 |
| Righe club | Da top 332, alte 45; logo 37 px a x = 68; testi Interstate Bold 20 px, #333333 |
| Riga dei totali | Alta 90; "Totale / stagione" allineato a destra a x = 733; totali di match, minuti e spot in Interstate Bold 37,3 px |
| Proposta economica | Cella unica sulla colonna 6 con il totale, Calibri Bold 46,7 px nel vecchio documento; nel nuovo Interstate Black (D-249) |
| Nota sulle squadre retrocesse | [56,941 1790×30], Interstate Bold 21,3 px, da `layout_texts` |

💡 Adeguamenti richiesti dalle specifiche approvate (RB-05 M09, D-103), senza cambiare la grafica:
- l'ultima colonna mostra il **prezzo di listino di ogni club** (partnership + LED + hospitality) su ogni riga;
- la cella dei totali mostra **totale di listino**, poi **sconto** ("Sconto 15%" o "Sconto 10.000,00 €"), poi **totale finale**, su tre righe **separate** (niente sovrapposizioni). Senza sconto compare solo il totale;
- colonna Format: "{LIVELLO} · {format}" quando c'è una partnership ("TOP PARTNER · 1ª fila"), solo il format se Libera, "—" senza LED;
- l'hospitality ha una riga secondaria sotto il club ("Hospitality Premium × 2"), Interstate Bold 16 px, con prezzo nella stessa riga;
- intestazioni leggibili: testo #FFFFFF in Interstate Black 20 px.

Capienza: **10 righe** per slide, con il totale solo nell'ultima. Oltre 10 righe (club o righe hospitality) la tabella continua su una slide successiva con lo stesso sfondo.

### 4.9 Recap economico club
Misure dal PDF Udinese (pagina 29): stessa griglia di colonne del network; intestazione a top 453, alta 105, nel colore del club (#97989B); riga club da top 559, alta 60; riga dei totali da top 619, alta 90.

💡 Righe del nuovo recap club (RB-05 M09, M08 §4.3):

| Riga | Format | N. match | Min./partita | Spot | Prezzo |
|---|---|---|---|---|---|
| Partnership "{LIVELLO}" | — | — | — | — | Prezzo partnership |
| LED "{format}" | Format | Partite | m'ss" | Spot a partita | Prezzo LED |
| Hospitality "{variante} × {posti}" | — | — | — | — | Prezzo hospitality |
| Totale / stagione | — | Partite | Minuti totali | Spot totali | Listino · sconto · totale finale |

Le righe senza contenuto (per esempio nessuna hospitality) non compaiono. Testi su fondo `accent_color` in #FFFFFF.

### 4.10 Nota IVA
Il PPTX club riporta "€ 180.000 + IVA", i PDF solo l'importo. 💡 Il recap riporta la nota fissa "Importi IVA esclusa" (RB-08 M03) da `layout_texts`, Interstate Regular 16 px, sotto la tabella.

---

## 5. Conteggio dei top match (⚠️ verificato sul dump)

- Nel vecchio database il flag `team_top` vale 1 per **Napoli, Inter, Juventus, Roma, Milan** (Roma due volte, club duplicato), ma **non per la Lazio**.
- Calendario 2026-27 del dump: Bologna e Como giocano in casa 5 partite ciascuno contro squadre con il flag, **5 + 5 = 10**. È il valore del PDF di esempio, quindi la regola RB-06 di M02 (ospite con il flag) riproduce il vecchio calcolo.
- La nota del PDF e del PPTX dice invece "partite che coinvolgono almeno una delle seguenti squadre: **Lazio**, Inter, Milan, Juventus, Roma e Napoli". Con la Lazio il conteggio darebbe 12 e non 10.
- ⚠️ Per una proposta sulla Lazio la differenza pesa di più: con "ospite con il flag" i top match in casa sono 5; con "almeno una delle due squadre" sarebbero tutte e 19 le partite casalinghe.

✅ **Decisione (D-253)**: RB-06 resta invariata (conta l'ospite con il flag) e la **Lazio** riceve il flag. Bologna + Como danno quindi 12 top match; in un documento della Lazio contano le sue partite in casa contro gli altri club con il flag. Club con il flag confermati da D-257: Lazio, Inter, Milan, Juventus, Roma, Napoli.

---

## 6. Vincoli per lo sviluppo (S1, F7)

1. Tela HTML 1920×1080 per pagina; sfondo `<img>` a piena pagina; testi in posizione assoluta con le misure di questo documento, raccolte in **un file di costanti per layout** (non sparse nei template Blade).
2. Font solo locali da `DOCUMENT_FONTS_PATH`, con `@font-face` e peso esplicito; Chromium senza rete (D-176).
3. Testi lunghi (nomi club, ragione sociale): riduzione del corpo fino al 70%, poi troncamento con "…". Mai a capo fuori dal riquadro.
4. Tabelle con capienza massima e slide di continuazione (§4.5, §4.8).
5. **Test di regressione visiva**: per ogni layout un caso di prova (2 club, 12 club, valori mancanti, nomi lunghi) generato in PNG e confrontato con un'immagine di riferimento approvata da Antonio (S1, F7).
6. Il simulatore usa lo stesso codice di composizione (D-220).
