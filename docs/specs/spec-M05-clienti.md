# Specifica funzionale: M05 Clienti

| Campo | Valore |
|---|---|
| Codice | M05 |
| Nome | Clienti |
| Versione | 1.1 (approvata D-084; RB-03 precisata da D-197) |
| Data | 2026-10-21 |
| Decisioni di riferimento | D-022, D-041, D-069, D-074…D-083, D-196, D-197 |

Legenda: ✅ confermato (con riferimento) · 💡 proposta · ❓ da chiarire · ⚠️ rischio

> **Note di allineamento (repository, 2026-10-21)**
> - Stato corretto in "approvata" (D-084); nel Project l'intestazione riportava ancora "bozza".
> - **D-197 recepita** in RB-03, §9 e CA 8: con P.IVA presenti e diverse, la stessa ragione sociale normalizzata non blocca la creazione.
> - **Nessuna migrazione** (D-227): i clienti li ricreano gli agenti al primo uso (D-229). I riferimenti a M11 nei §6, §9 e §10 non si applicano.
> - Modello: P.IVA univoca come vincolo del database; ragione sociale normalizzata controllata nel codice; unione dei duplicati con riferimento al cliente che resta; file di logo mai sovrascritti (D-196).
> - Gestore: vede tutti i clienti in consultazione (D-076, matrice M01).

---

## 1. Descrizione
Anagrafica condivisa delle aziende destinatarie delle proposte (✅ D-074). Fornisce:
- ragione sociale e logo per la copertina del documento (M09);
- e-mail per il futuro invio del link alla proposta web (✅ D-079);
- settore merceologico per classificazione e statistiche (✅ D-075).

## 2. Obiettivi
- Inserire un cliente una sola volta e riutilizzarlo su più proposte.
- Impedire i duplicati (✅ D-077).
- Mantenere separato il portafoglio clienti di ciascun agente (✅ D-076).

## 3. Attori
| Attore | Ruolo |
|---|---|
| Agente | Crea clienti, vede e modifica **solo i propri** (✅ D-076, D-078) |
| Admin | Vede e gestisce tutti i clienti; gestisce i settori merceologici; assegna/riassegna i clienti (✅ D-082); 💡 unisce duplicati |
| Gestore | 💡 Vede tutti i clienti in consultazione |

## 4. Funzionalità
| ID | Funzionalità | Stato |
|---|---|---|
| F-01 | Anagrafica clienti condivisa e riutilizzabile | ✅ D-074 |
| F-02 | Nel configuratore: ricerca con suggerimenti tra i propri clienti, oppure creazione al volo | 💡 |
| F-03 | Blocco della creazione se il cliente è già presente, con messaggio | ✅ D-077 |
| F-04 | Modifica del cliente da parte dell'agente che l'ha creato | ✅ D-078 |
| F-05 | Settori merceologici: lista gestita dall'admin; con "ALTRO/VARIE" campo testo libero obbligatorio | ✅ D-075 |
| F-06 | Scheda cliente con elenco delle proposte collegate | 💡 |
| F-07 | Assegnazione/riassegnazione di un cliente a un altro agente (admin); le proposte esistenti restano all'agente originale | ✅ D-082, D-083 |
| F-08 | Unione di due clienti duplicati, con spostamento delle proposte (admin) | 💡 |
| F-09 | Disattivazione (non cancellazione) dei clienti con proposte collegate | 💡 |

## 5. Flussi utente
**FL-01 Nuova proposta per un cliente esistente**
1. Nello step cliente l'agente digita la ragione sociale.
2. Il sistema suggerisce i suoi clienti.
3. L'agente seleziona e prosegue.

**FL-02 Nuovo cliente**
1. Non trovando il cliente, l'agente compila ragione sociale, e-mail, logo e settore (più il testo libero se "ALTRO/VARIE").
2. Il sistema verifica i duplicati su **tutta** l'anagrafica, non solo sui clienti dell'agente.
3. Nessun duplicato: il cliente viene creato e associato all'agente.
4. Duplicato: **blocco** con messaggio "Cliente già presente in anagrafica: contatta l'amministratore". ⚠️ Il messaggio non deve rivelare a quale agente appartiene il cliente (💡).
5. L'admin assegna il cliente all'agente che ne ha bisogno (F-07). Le proposte già fatte dall'agente precedente restano sue (D-083). Non esiste condivisione di un cliente tra più agenti (D-082).

**FL-03 Modifica del cliente**
1. L'agente aggiorna logo, e-mail o settore di un proprio cliente.
2. I documenti già generati restano invariati; i nuovi usano i dati aggiornati (💡 snapshot, coerente con D-041).

## 6. Dati richiesti
| Campo | Obbligatorio | Note |
|---|---|---|
| Ragione sociale | Sì | ✅ |
| E-mail | Sì | ✅ D-079; formato validato |
| Logo | Sì | ✅ PNG/JPG, max 2 MB; 💡 consigliato PNG con fondo trasparente |
| Settore merceologico | Sì | ✅ D-075, da lista |
| Settore (testo libero) | Sì, solo con "ALTRO/VARIE" | ✅ D-075 |
| P.IVA | No | ✅ D-081; se presente è codice univoco (D-080) |
| Referente | No | ✅ D-081 |
| Agente proprietario | Automatico | Utente che ha creato il cliente |

**Settori merceologici iniziali** (✅ D-075, dal sistema attuale):
Abbigliamento · Abitazione · Alimentari · Automobili · Betting · Bevande/Alcoolici · Cura persona · Distribuzione · Elettrodomestici · Enti/Istituzioni · Farmaceutici/Sanitari · Finanza/Assicurazioni · Gestione casa · Giochi/Art. scuola · Industria/Edilizia/Attività · Informatica · Media/Editoria · Moto/Veicoli · Oggetti personali · Servizi professionali · Tempo libero · Toiletries · Turismo viaggi · Altro/Varie.

⚠️ Nel sistema attuale la voce "INDUSTRIA/EDILIZIA/ATTIVIT&Agrave;" è salvata con un'entità HTML al posto della "À": lo script dei dati iniziali (D-229) la corregge; nel nuovo sistema UTF-8 end-to-end.

## 7. Regole di business
| ID | Regola | Stato |
|---|---|---|
| RB-01 | L'agente vede, seleziona e modifica solo i clienti che ha creato | ✅ D-076, D-078 |
| RB-02 | Il controllo duplicati vale su tutta l'anagrafica ed è bloccante | ✅ D-077 |
| RB-03 | **Criterio di duplicato:** stessa P.IVA, se presente, **oppure** stessa ragione sociale normalizzata (maiuscole, spazi, punteggiatura e forma societaria ignorati: "Adecco S.p.A." = "ADECCO SPA" = "Adecco"), **salvo che entrambi i clienti abbiano una P.IVA e le due siano diverse** | ✅ D-080, D-197 |
| RB-04 | Con "ALTRO/VARIE" il testo libero è obbligatorio | ✅ D-075 |
| RB-05 | Il settore merceologico non genera vincoli di esclusività | ✅ D-022 |
| RB-06 | Cliente con proposte collegate: non cancellabile, solo disattivabile | 💡 |
| RB-07 | Modifiche al cliente non alterano i documenti già generati | 💡 |
| RB-08 | Un settore usato da clienti non si cancella: si disattiva | 💡 |

## 8. Validazioni
- E-mail in formato valido.
- Logo: solo PNG/JPG, massimo 2 MB; 💡 dimensione minima consigliata 300 px di lato per una resa corretta in copertina.
- Ragione sociale: minimo 2 caratteri.
- P.IVA, se inserita: 11 cifre con controllo formale.

## 9. Eccezioni
| Caso | Gestione |
|---|---|
| Agente B prova a creare un cliente che appartiene all'agente A | Blocco; messaggio neutro; l'admin assegna il cliente (F-07, D-082) |
| Ragioni sociali simili ma aziende diverse ("Rossi Srl" e "Rossi Spa") | ✅ Con P.IVA diverse inserite su entrambi il sistema **non blocca** (D-197). Senza P.IVA su uno dei due il blocco resta e il cliente lo crea l'admin |
| Cliente da disattivare con proposte in attesa | 💡 Avviso; le proposte restano valide |
| Agente disattivato o uscito dall'azienda | 💡 L'admin riassegna i suoi clienti a un altro agente (F-07) |
| ~~Duplicati nello storico~~ | Non applicabile: nessuna migrazione (D-227) |

## 10. Dipendenze
- **Dipende da:** M01 (utenti, ruoli e proprietà del cliente).
- **Usato da:**
  - M06 (step cliente del configuratore);
  - M09 (logo e ragione sociale in copertina; e-mail per la futura landing web);
  - M10 (statistiche per settore).

## 11. Criteri di accettazione
1. Un cliente creato dall'agente A non compare nella ricerca dell'agente B.
2. L'agente B che inserisce "Adecco S.p.A." quando esiste "ADECCO" riceve un blocco con messaggio neutro.
3. Selezionando "ALTRO/VARIE" il campo libero compare ed è obbligatorio.
4. Un logo PDF o di 3 MB viene rifiutato con messaggio chiaro.
5. L'agente A modifica il logo del proprio cliente; i documenti già generati mantengono il logo precedente.
6. L'admin riassegna un cliente da A a B: B lo vede e lo usa, A non lo vede più, ma conserva le proposte già fatte.
7. Un cliente con proposte non è cancellabile, solo disattivabile.
8. *(D-197)* "Rossi Srl" con P.IVA 01234567897 e "Rossi Spa" con P.IVA 09876543210 si creano entrambi; "Rossi Spa" senza P.IVA, quando esiste "Rossi Srl", viene bloccato.

## 12. Questioni aperte
- ~~💡 P.IVA diverse non bloccano~~ → D-197.
- 💡 Landing web della proposta con invio del link via e-mail (D-079): evoluzione EV-01.
