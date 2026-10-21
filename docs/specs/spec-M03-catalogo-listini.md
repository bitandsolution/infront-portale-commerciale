# Specifica funzionale: M03 Catalogo e listini

| Campo | Valore |
|---|---|
| Codice | M03 |
| Nome | Catalogo e listini |
| Versione | 1.3 (approvata D-059; RB-02 da D-063; F-08 da D-117; RB-06 da D-147) |
| Data | 2026-10-10 |
| Decisioni di riferimento | D-011, D-020, D-021, D-029, D-030, D-041, D-044…D-058 |

Legenda: ✅ confermato (con riferimento) · 💡 proposta · ❓ da chiarire · ⚠️ rischio

> **Note di allineamento (repository, 2026-10-21)**
> - **F-12** (copia del listino dalla stagione precedente con +%): spostata nel **rilascio 1.1** (D-236). Il **criterio di accettazione 5** si collauda nel 1.1.
> - Modello: un listino per stagione in bozza o pubblicato; "non disponibile" = nessuna riga di prezzo; prezzo LED in decimali esatti, arrotondato a 2 decimali solo sul prezzo del club (D-190).
> - Perimetro del gestore: sola consultazione (D-137).

---

## 1. Descrizione
Il modulo definisce cosa si vende e a quale prezzo, per club e per stagione:
- tipi di visibilità;
- format LED;
- livelli di partnership con i relativi benefit;
- hospitality.

Alimenta il configuratore (M06) con opzioni e prezzi di listino, e il documento (M09) con benefit e importi. Le quantità disponibili sono gestite in M04.

## 2. Obiettivi
- Un listino unico per club e stagione, gestito solo dall'admin (✅ D-020).
- Opzioni del configuratore guidate dai dati: un format o un livello non disponibile per un club non compare.
- Benefit per club e livello modificabili da backoffice, senza copie di PPTX tra club e stagioni.

## 3. Attori
| Attore | Ruolo |
|---|---|
| Admin | Gestione completa di catalogo e listini |
| Gestore | 💡 Sola consultazione (❓ perimetro in M01) |
| Agente | Nessun accesso al modulo; vede solo prezzi di listino nel configuratore (✅ D-011) |

## 4. Funzionalità
| ID | Funzionalità | Stato |
|---|---|---|
| F-01 | Tipi di visibilità: Classica, Virtual (Virtual disattivata) | ✅ D-021 |
| F-02 | Territorio: Italia (attivo), Estero (previsto, non attivo) | ✅ D-030 |
| F-03 | Format LED: Jumbo, Domination, 1ª fila, 2ª fila, con descrizione e bacini consumati | ✅ D-051 |
| F-04 | Bacini Corner bassi e Sotto tribuna previsti nel modello ma disattivati | ✅ D-046 |
| F-05 | Listino LED per club, stagione e format: **costo per 1′ a partita**, oppure "non disponibile" | ✅ D-054 |
| F-06 | Livelli di partnership: Libera, Top, Premium, Official, Official 2.0 (ordinabili, attivabili) | ✅ D-050 |
| F-07 | Listino partnership per club, stagione e livello: prezzo fisso stagionale, oppure "non disponibile" | ✅ D-029 |
| F-08 | Benefit per club, livello e stagione: blocchi di testo per categoria — **facoltativi**, riservati alla futura landing web; nei documenti i benefit sono slide statiche (M08) | ✅ D-117 (modifica D-044) |
| F-09 | Hospitality: varianti Premium e Base; listino prezzo a posto **per stagione**, per club e variante | ✅ D-055 |
| F-10 | Asset on top (branded content social, match sponsor, attivazioni): solo testi descrittivi per il documento | ✅ D-049 |
| F-11 | Stato del listino per stagione: bozza / pubblicato | 💡 |
| F-12 | Copia dalla stagione precedente, con aumento % opzionale sui prezzi | 💡 — **rilascio 1.1 (D-236)** |
| F-13 | Copia dei benefit da un club a un altro, come base di partenza | 💡 |

**Categorie di benefit** (💡, dai deck 2026-27): IP rights · Brand exposure stadio e centro sportivo · Digital & social · Ticketing & hospitality · Esperienze esclusive · Merchandising · Servizi · Note (es. esclusività, autorizzazioni).

## 5. Flussi utente
**FL-01 Preparazione nuova stagione**
1. L'admin crea il listino della nuova stagione copiandolo dalla precedente (F-12). Il listino nasce in bozza.
2. Corregge costi LED, prezzi partnership, prezzi hospitality e benefit, club per club.
3. Pubblica: da quel momento il listino è visibile nel configuratore per proposte di quella stagione.

**FL-02 Variazione di prezzo in corso di stagione**
1. L'admin modifica un prezzo.
2. Le proposte approvate restano invariate (✅ D-041).
3. Le bozze si ricalcolano e l'agente vede un avviso alla riapertura (✅ D-045).

**FL-03 Correzione dei benefit**
1. L'admin modifica il testo di una categoria per un club e livello.
2. Le proposte già generate non cambiano. I nuovi documenti usano il testo aggiornato (dettaglio in M09).

## 6. Dati richiesti (concettuali)
| Entità | Attributi principali |
|---|---|
| Format LED | Nome, descrizione, bacini consumati, attivo |
| Prezzo LED | Club, stagione, format, costo per 1′ a partita (2 decimali, ✅ D-057), disponibile sì/no |
| Livello partnership | Nome, ordine, attivo; flag "Libera" (senza prezzo, senza benefit, senza contatore) |
| Prezzo partnership | Club, stagione, livello, prezzo stagionale, disponibile sì/no |
| Benefit | Club, stagione, livello, categoria, testo formattato, ordine |
| Variante hospitality | Premium, Base |
| Prezzo hospitality | Club, stagione, variante, prezzo a posto per stagione |
| Testo on top | Club (o generale), stagione, titolo, testo |

## 7. Regole di business
| ID | Regola | Stato |
|---|---|---|
| RB-01 | **Prezzo LED** = costo per 1′ a partita × minuti a partita × partite in casa nel periodo (partite da M02) | ✅ D-054 |
| RB-02 | I minuti si esprimono a passi di 30″ (0,5′) | ✅ D-063 (modifica post-approvazione) |
| RB-03 | **Prezzo partnership** = prezzo stagionale fisso, anche a stagione iniziata | ✅ D-029 |
| RB-04 | Il pacchetto "Libera" non ha prezzo né benefit: la proposta comprende solo visibilità LED / hospitality | ✅ D-052 |
| RB-05 | **Prezzo hospitality** = prezzo a posto (stagionale) × posti. Selezionabile sempre, indipendentemente dalla partnership; se l'agente non la inserisce non compare e costa 0 | ✅ D-055 |
| RB-05b | Prezzo hospitality pieno anche a stagione iniziata; aggiustamenti solo con sconto admin | ✅ D-058 |
| RB-06 | Massimo 6 posti hospitality per club in una proposta | ✅ D-147 (modifica D-056) |
| RB-07 | L'agente vede e usa solo prezzi di listino; sconti e modifiche di prezzo le fa solo l'admin, in approvazione | ✅ D-011, D-041 |
| RB-08 | Prezzi IVA esclusa | 💡 |
| RB-09 | Un listino in bozza non è utilizzabile dal configuratore | 💡 |
| RB-10 | Un format, livello o variante "non disponibile" per un club non compare nel configuratore per quel club | 💡 |
| RB-11 | Le variazioni di listino non toccano le proposte approvate; le bozze si ricalcolano con avviso | ✅ D-045 |
| RB-12 | La partnership non consuma inventario LED; i minuti LED citati nei benefit sono solo testo | ✅ D-051 |

## 8. Validazioni
- Costo LED, prezzo partnership e prezzo hospitality: numeri ≥ 0 con al massimo 2 decimali.
- Un listino si pubblica solo se ogni club Infront iscritto nella stagione ha almeno un'offerta disponibile (LED o partnership): **avviso**, non blocco.
- Un livello di partnership disponibile per un club deve avere almeno una categoria di benefit compilata: **avviso** prima della pubblicazione.
- Non si può cancellare un format, un livello o una variante usati in proposte esistenti: solo disattivazione.

## 9. Eccezioni
| Caso | Gestione |
|---|---|
| ⚠️ Arrotondamento del costo/cad (3.158 × 19 = 60.002 € contro 60.000 € di listino) | ✅ Costo/cad con 2 decimali (3.157,89); l'eventuale scarto residuo lo corregge l'admin in approvazione (D-057) |
| Format offerto solo da alcuni club (es. Jumbo solo Lazio) | Gestito con "non disponibile" per club (RB-10) |
| Official 2.0 vuoto per molti club | Livello "non disponibile" per quei club |
| Club retrocesso o promosso | Il listino resta legato a club e stagione; le partite standard derivano dalla nuova competizione (M02) |
| Benefit con refusi copiati da altri club | Correzione centralizzata in F-08; copia tra club (F-13) solo come base da rivedere |

## 10. Dipendenze
- **Dipende da:** M02 (club, stagioni, partite nel periodo).
- **Usato da:**
  - M04 (contatori partnership, posti hospitality, bacini LED);
  - M06 (opzioni e calcolo prezzi);
  - M07 (prezzo di listino in approvazione);
  - M09 (benefit, importi, testi on top).

## 11. Criteri di accettazione
1. Con il costo 1ª fila Lazio = 3.157,89 €, una proposta da 1′ per 19 partite restituisce 60.000 € circa (scarto ≤ 1 €); con 1′30″ restituisce 1,5 volte quell'importo.
2. Con data di inizio a stagione in corso e 10 partite rimanenti, il prezzo LED è costo × minuti × 10.
3. Il prezzo della partnership Premium Lazio resta 135.000 € indipendentemente dalla data di inizio.
4. Un format segnato "non disponibile" per un club non compare nel configuratore per quel club.
5. Copiando il listino 2026-27 nel 2027-28 con +5%, tutti i prezzi risultano aumentati del 5% e il nuovo listino è in bozza. *(Rilascio 1.1, D-236)*
6. Modificando un prezzo, una proposta approvata mantiene il prezzo precedente; una bozza mostra il nuovo prezzo e un avviso.
7. Un benefit modificato per Lazio Top non cambia i benefit di altri club o livelli.
8. Nel configuratore non è possibile inserire più di 6 posti hospitality per club.
9. Con 2 posti Premium Lazio a prezzo stagionale P, il totale hospitality è 2 × P, con o senza partnership.

## 12. Questioni aperte
- ❓ Perimetro del ruolo Gestore → M01.
- ❓ Esclusività citata nei benefit Top/Premium (D-022): resta solo testo nella categoria "Note"; da allineare con Infront.
