### Guida alle opzioni configurabili in `DrawJsTable`

La classe `DrawJsTable` permette di personalizzare vari aspetti della tabella generata utilizzando l'oggetto `options` passato al costruttore. Di seguito sono descritte le opzioni disponibili:

#### 1. **`tableClass`**
   - **Descrizione**: Imposta le classi CSS per la tabella.
   - **Predefinito**: `"table table-striped table-bordered"`
   - **Esempio**:
     ```javascript
     new DrawJsTable(data, 'containerId', { tableClass: 'table table-hover' });
     ```

#### 2. **`headerClass`**
   - **Descrizione**: Imposta le classi CSS per l'elemento `<thead>`.
   - **Predefinito**: `""` (nessuna classe aggiuntiva)
   - **Esempio**:
     ```javascript
     new DrawJsTable(data, 'containerId', { headerClass: 'thead-dark' });
     ```

#### 3. **`rowClass`**
   - **Descrizione**: Imposta le classi CSS per tutte le righe della tabella (`<tr>`).
   - **Predefinito**: `""` (nessuna classe aggiuntiva)
   - **Esempio**:
     ```javascript
     new DrawJsTable(data, 'containerId', { rowClass: 'align-middle' });
     ```

#### 4. **`cellClass`**
   - **Descrizione**: Imposta le classi CSS per tutte le celle della tabella (`<th>` e `<td>`).
   - **Predefinito**: `""` (nessuna classe aggiuntiva)
   - **Esempio**:
     ```javascript
     new DrawJsTable(data, 'containerId', { cellClass: 'text-center' });
     ```

### Esempio completo
```javascript
const data = {
  labels: [0, 1, 2, 3, 4],
  datasets: {
    label: 'Example Data',
    data: [10, 20, 30, 40, 50],
  },
};

const options = {
  tableClass: 'table table-hover table-sm',
  headerClass: 'thead-light',
  rowClass: 'text-start',
  cellClass: 'text-end',
};

const tableInstance = new DrawJsTable(data, 'tableContainer', options);
tableInstance.render();
```


### Risultato
Con l'esempio sopra, la tabella avrà:
- Stile Bootstrap per `hover` e dimensioni compatte.
- Intestazione con il tema `light`.
- Righe allineate a sinistra.
- Celle con testo allineato a destra.

Puoi combinare queste opzioni per adattare lo stile della tabella alle tue esigenze grafiche.


### Preset

Per facilitare la personalizzazione, sono stati definiti alcuni preset di stili predefiniti che possono essere utilizzati al posto delle classi CSS personalizzate. I preset disponibili sono:
default, compact, dark, o hoverable

#### 1. **`preset`**
   - **Descrizione**: Imposta un preset di stili predefiniti.
   - **Predefinito**: `""` (nessun preset)
   - **Esempio**:
     ```javascript
     new DrawJsTable(data, 'containerId', { preset: 'compact' });
     ```

### firstCellText
    - **Descrizione**: Imposta il testo della prima cella della tabella.
    - **Predefinito**: `""` (nessun testo)
    - **Esempio**:
      ```javascript
      new DrawJsTable(data, 'containerId', { firstCellText: 'X' });
      ```