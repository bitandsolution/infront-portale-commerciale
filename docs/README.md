# Documentazione — Portale Commerciale Infront

Indice dei documenti di progetto copiati nel repository al blocco 6.3 (2026-10-21). L'originale di progettazione resta nel Project "PORTALE COMMERCIALE INFRONT" su claude.ai; da questo momento **lo sviluppo fa riferimento alla copia nel repository**.

| File | Contenuto |
|---|---|
| `registro-decisioni.md` | Decisioni D-001…D-259. Valgono solo le APPROVATE |
| `pianificazione.md` | Fase 6: perimetro MVP, fette F0…F9, milestone, calendario |
| `progettazione-tecnica.md` | Stack, hosting (R1–R7), modello dati, sicurezza e API, documenti e code, piano di avvio |
| `ux-architettura.md` | Principi UX, navigazione per ruolo, stati ed errori, breakpoint |
| `layout-dinamici.md` | Verifica sui PDF di esempio e specifica dei 9 layout dinamici |
| `features-evolutive.md` | Funzioni fuori perimetro (EV-01…EV-24) |
| `specs/spec-M01…M10-*.md` | Specifiche funzionali approvate, con le note di allineamento in testa |
| `specs/spec-M11-migrazione.md` | Non applicata (D-227); resta solo il perimetro degli script di dati iniziali |
| `dev/ci.md` | Pipeline di integrazione e deploy |
| `dev/pr-f0.md` | Elenco delle PR della fetta F0 |

**Mockup UX**: canvas "Portale Infront – Configuratore", 13 tavole → https://claude.ai/artifact/WnoZA4zUgQMJBHLJvF4B3c (privato).

## Come si aggiornano
- Una decisione nuova entra nel registro **solo con la conferma di Antonio**, con il numero progressivo successivo.
- Se una decisione cambia una specifica, la specifica si aggiorna nella stessa PR, con una riga nelle "Note di allineamento" in testa al file.
- Le PR di sola documentazione usano un branch `docs/…`.
