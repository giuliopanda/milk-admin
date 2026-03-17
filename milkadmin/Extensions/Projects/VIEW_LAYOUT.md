# Projects Extension - Guida completa a `view_layout.json`

Questa guida documenta il comportamento reale del layout view della Projects extension, basandosi sul codice attuale in:

- `milkadmin/Extensions/Projects/Module.php`
- `milkadmin/Extensions/Projects/Classes/View/ViewSchemaParser.php`
- `milkadmin/Extensions/Projects/Classes/View/ViewPageRenderer.php`
- `milkadmin/Extensions/Projects/Classes/View/ViewBlockRenderer.php`
- `milkadmin/Extensions/Projects/Classes/Renderers/AutoViewRenderer.php`
- `milkadmin/Extensions/Projects/Classes/Renderers/AutoEditRenderer.php`

## 1. A cosa serve `view_layout.json`

`view_layout.json` definisce come comporre la pagina `*-view` del record root (dashboard del singolo record).

In pratica:

- decide quali blocchi/cards mostrare;
- decide come mostrare ogni form (`fields`, `icon`, `table`);
- permette di raggruppare piu form in una card (`type: "group"`).

## 2. Dove va messo

Percorso standard:

- `<ModuleDir>/Project/view_layout.json`

Il file viene cercato nella stessa cartella del `manifest.json` caricato dal modulo.

## 3. Quando viene usato davvero

`view_layout.json` viene usato solo quando esiste una route `*-view` attiva.

Nella pratica, la route `*-view` e attiva quando nel manifest del form root abiliti:

- `viewSingleRecord: true`

Alias accettati dal parser manifest:

- `view_single_record`
- `viewAction`
- `view_action`

Nota importante:

- il modulo forza `view_action` solo sul form root;
- sui form figli `view_action` viene disabilitato.

Quindi il layout e pensato per la view root.

## 4. Flusso di caricamento e precedence

Ordine effettivo:

1. Prova a leggere `Project/view_layout.json`.
2. Se valido, usa quello.
3. Se mancante o invalido, prova fallback da `manifest.json` chiave `view_layout`.
4. Se nessuno schema valido e disponibile, la view usa il renderer legacy (senza JSON layout).

Dettagli warning/error:

- Warning parser (non bloccanti) su file esterno vengono registrati come manifest errors.
- Errori strutturali bloccanti azzerano lo schema e attivano fallback.
- Nel fallback embedded da `manifest.json`, i warning parser non vengono propagati nei manifest errors.

## 5. Struttura JSON supportata

Schema logico:

```json
{
  "version": "1.0",
  "cards": [
    {
      "id": "main",
      "type": "single-table",
      "preHtml": "",
      "postHtml": "",
      "table": {
        "name": "RootFormName",
        "displayAs": "fields",
        "title": "Titolo",
        "icon": "bi bi-card-text",
        "hideSideTitle": false,
        "preHtml": "",
        "postHtml": ""
      }
    }
  ]
}
```

### 5.1 Root keys

- `version` (string, opzionale)
  - default: `"1.0"`
  - al momento non viene validata semantica della versione.
- `cards` (array, obbligatorio, non vuoto)
  - se assente o vuoto: errore bloccante.

### 5.2 Card object

Chiavi comuni:

- `id` (string, opzionale)
  - se assente: auto `card-{index+1}`
  - deve essere univoco (duplicati skippati con warning).
- `type` (string, opzionale)
  - valori supportati: `single-table`, `group`
  - default/fallback: `single-table`.
- `preHtml`, `postHtml` (string, opzionali)
  - HTML raw inserito prima/dopo il contenuto della card.

### 5.3 Card `single-table`

Richiede:

- `table` (object, obbligatorio)

Se `table` manca, la card viene scartata.

### 5.4 Card `group`

Chiavi:

- `title` (string, opzionale)
- `icon` (string, opzionale)
- `tables` (array, obbligatorio, non vuoto)

Se `tables` manca/vuoto/non valido, la card viene scartata.

### 5.5 Table config (`table` o elementi in `tables`)

Chiavi:

- `name` (string, obbligatorio)
  - ATTENZIONE: e il *form name* del manifest, non il nome tabella DB.
  - Esempio: se il ref e `Demographics.json`, usa `"name": "Demographics"`.
- `displayAs` (string, opzionale ma fortemente consigliato)
  - valori: `fields`, `icon`, `table`
  - default/fallback: `icon`.
- `title` (string, opzionale)
- `icon` (string, opzionale)
- `hideSideTitle` (bool-like, opzionale)
  - truthy: `true`, `1`, `"1"`, `"true"`, `"yes"`, `"on"`.
- `preHtml`, `postHtml` (string, opzionali)

## 6. Semantica di rendering

## 6.1 Regola base: root record

La pagina `*-view` lavora sul record root richiesto da `id`.

Ogni card viene renderizzata in ordine e wrappata in un container:

- `id="idViewCard{safeCardId}{recordId}"`

Questo id viene poi usato per reload parziali dopo save in modal/offcanvas.

## 6.2 `displayAs: "fields"`

Comportamento:

- Render key/value sui campi `view` del model (`getRules('view', true)` fallback `getRules()`).
- Per root form: usa il record root corrente.
- Per child form: cerca il primo record collegato via FK al root.

Azioni UI:

- se record presente: bottone `Edit` (se `edit_action` disponibile);
- se record assente (child): bottone `Add new`.

Uso consigliato:

- form single-record (`max_records: 1`) o root details.

Limitazione:

- su form multi-record mostra solo il primo record, non una lista.

## 6.3 `displayAs: "icon"`

Comportamento:

- Riga compatta con titolo form e link record.
- Se esistono record: icona check + link ai record.
- Se permesso: bottone `Add new` (rispetta `max_records`).

Uso consigliato:

- child form per navigazione rapida.

Limitazione pratica:

- richiede FK parent valida (quindi non adatto al root form).

## 6.4 `displayAs: "table"`

Comportamento:

- Tabella HTML con righe dei record child.
- Colonne dai `view` rules del model, con esclusione di:
  - `___action`
  - FK parent
  - `root_id`
  - alias `withCount` dei child diretti.
- Prima colonna linkata a edit (se disponibile).
- Bottone `Add new` se limite non raggiunto.

Nested automatico:

- aggiunge automaticamente colonne per child diretti del form in tabella (badge/count/icon), basandosi su `children_meta_by_alias` del manifest.

Uso consigliato:

- form multi-record.

Limitazione pratica:

- richiede FK parent valida (quindi non adatto al root form).

## 7. `single-table` vs `group`

### 7.1 `single-table`

- una card contiene un solo table config;
- utile per sezioni grandi e indipendenti;
- card header derivato da `table.title`/`table.icon`.

### 7.2 `group`

- una card con header unico (`title`/`icon`) e piu righe interne;
- ogni entry in `tables` viene renderizzata in sequenza.

Nota:

- in `group`, `displayAs: "fields"` e supportato ma renderizzato inline (senza card interna con bottone Edit dedicato).

## 8. Significato reale di `title`, `icon`, `hideSideTitle`, `preHtml`, `postHtml`

- `title`
  - `table` mode: usato per heading laterale (se non nascosto).
  - `single-table` + `icon/table`: usato anche come header card esterno.
  - `group` + `icon`: la label riga arriva dal `form_title` context; `table.title` non sovrascrive la label interna.
- `icon`
  - usato in header card quando previsto.
- `hideSideTitle`
  - usato solo in `displayAs: "table"` (nasconde colonna titolo laterale della riga tabella).
- `preHtml` / `postHtml`
  - card-level sempre applicati.
  - table-level applicati nei `group`.
  - table-level in `single-table` al momento non vengono applicati direttamente.

## 9. Validazioni, warning ed errori parser

## 9.1 Errori bloccanti (eccezione)

Esempi:

- file mancante/vuoto/JSON invalido;
- root non object;
- `cards` assente o vuoto;
- dopo parse non rimane nessuna card valida.

Effetto:

- schema non caricato;
- fallback ad embedded `view_layout` in manifest o renderer legacy.

## 9.2 Warning non bloccanti

Esempi:

- card non object;
- `id` mancante (auto-assegnato);
- `id` duplicato (card skippata);
- `type` mancante/sconosciuto (fallback `single-table`);
- `displayAs` mancante/sconosciuto (fallback `icon`);
- `table.name` mancante (table skippata);
- group senza `tables` valide (card skippata).

## 10. Reload parziale card dopo save (modal/offcanvas)

Quando edit avviene in fetch mode e save va a buon fine:

- se `view_layout` e attivo;
- se non e gia stato richiesto `reload_list_id`;

allora `AutoEditRenderer` prova a rigenerare una card specifica e restituisce:

- `element.selector = #idViewCard...`
- `element.innerHTML = ...`

Risoluzione card target:

1. prova form corrente;
2. poi parent form;
3. poi antenati (dal piu vicino al root).

Viene scelta la prima card che contiene quel form name.

Implicazione:

- se lo stesso form compare in piu card, viene aggiornata solo la prima match.

## 11. Interazione con `viewSingleRecord`

Con `viewSingleRecord: true` (root):

- viene registrata action `root-form-view`;
- list root tende a usare la navigazione verso view;
- child di primo livello possono essere gestiti dentro la view root;
- dopo save di form collegati alla root view, il target naturale di ritorno e la view root (non la list classica).

Con `viewSingleRecord: false`:

- niente `*-view` root;
- `view_layout` non entra nel flusso principale.

## 12. Best practice consigliate

- Usa `table.name` uguale al form name da `ref` manifest.
- Metti come prima card il root form in `displayAs: "fields"`.
- Usa `fields` su form single-record.
- Usa `table` su form multi-record.
- Usa `icon` per navigazione rapida su form brevi o secondari.
- Evita di configurare root form in `icon`/`table`.
- Evita riferimenti diretti a form molto profondi: il renderer e ottimizzato per root + figli diretti.
- Usa `hideSideTitle: true` con `displayAs: "table"` quando hai gia un header esterno.
- Usa `preHtml`/`postHtml` solo con HTML fidato (iniettato raw).

## 13. Esempio completo

```json
{
  "version": "1.0",
  "cards": [
    {
      "id": "main-record",
      "type": "single-table",
      "table": {
        "name": "LongitudinalDatabase",
        "displayAs": "fields",
        "title": "Longitudinal Database",
        "icon": "bi bi-clipboard2-pulse"
      }
    },
    {
      "id": "demographics",
      "type": "single-table",
      "table": {
        "name": "Demographics",
        "displayAs": "fields",
        "title": "Demographics",
        "icon": "bi bi-person-vcard"
      }
    },
    {
      "id": "baseline-data",
      "type": "single-table",
      "table": {
        "name": "BaselineData",
        "displayAs": "icon",
        "title": "Baseline Data",
        "icon": "bi bi-clipboard2-data"
      }
    },
    {
      "id": "visit-1",
      "type": "single-table",
      "table": {
        "name": "Visit1",
        "displayAs": "table",
        "title": "Visit 1",
        "icon": "bi bi-calendar-check",
        "hideSideTitle": true
      }
    },
    {
      "id": "secondary-group",
      "type": "group",
      "title": "Secondary Sections",
      "icon": "bi bi-grid-1x2",
      "tables": [
        {
          "name": "TitleOnlyTest",
          "displayAs": "table",
          "title": "Title Only Test",
          "hideSideTitle": true
        },
        {
          "name": "BaselineData",
          "displayAs": "icon"
        }
      ]
    }
  ]
}
```

## 14. Troubleshooting rapido

- Messaggio `Form context not found for ...`
  - `table.name` non corrisponde a un form manifest registrato.
- Messaggio `Missing FK for ...`
  - stai renderizzando in `icon`/`table` un form non compatibile con parent context corrente.
- Save in modal ma card non aggiornata
  - il form salvato non e mappato in nessuna card risolvibile;
  - oppure il target card matcha un'altra card prima (duplicati di stesso form).
- `view_layout.json` presente ma non usato
  - `viewSingleRecord` non attivo sul root.
