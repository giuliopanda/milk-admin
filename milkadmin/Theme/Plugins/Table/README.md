# Table Plugin - Documentazione Sistema di Paginazione

## Panoramica

Il sistema di tabelle di MilkAdmin è composto da tre componenti principali che lavorano insieme per gestire la visualizzazione dinamica dei dati, l'ordinamento, la selezione delle righe e **la paginazione AJAX**.

## Struttura File

```
milkadmin/Theme/Plugins/Table/
├── plugin.php         # Generatore HTML della tabella
├── pagination.php     # Generatore HTML della paginazione
├── table.js          # Gestione JavaScript interattiva
└── table.css         # Stili CSS
```

---

## Come Funziona la Paginazione

### 1. Identificazione Univoca della Tabella

Ogni tabella ha un **ID univoco** che permette al JavaScript di identificare quale tabella aggiornare quando cambia pagina.

**In [plugin.php:258-262](milkadmin/Theme/Plugins/Table/plugin.php#L258-L262):**
```php
if (!isset($page_info['id'])) {
    $table_id = 'tableId'.uniqid();
} else {
    $table_id = _r($page_info['id']);
}
```

L'ID viene poi inserito nel container principale:
```php
<div class="table-container js-table-container" id="<?php _p($table_id); ?>">
```

### 2. Generazione HTML della Paginazione

La paginazione viene generata in **[pagination.php](milkadmin/Theme/Plugins/Table/pagination.php)** e inclusa alla fine della tabella.

**In [plugin.php:458-460](milkadmin/Theme/Plugins/Table/plugin.php#L458-L460):**
```php
if (($page_info['pagination'] ?? true) && $page_info['total_record'] > 0) {
    echo App\Get::themePlugin('table/pagination', ['page_info' => $page_info]);
}
```

#### Elementi della Paginazione

**A. Bottoni numerici di pagina** - [pagination.php:41-73](milkadmin/Theme/Plugins/Table/pagination.php#L41-L73)
```php
<span class="page-link table-pagination-click js-pagination-click"
      data-table-page="<?php echo $i; ?>">
    <?php echo $i; ?>
</span>
```

**B. Select dropdown per pagine (quando ci sono più di 9 pagine)** - [pagination.php:75-155](milkadmin/Theme/Plugins/Table/pagination.php#L75-L155)
```php
<select class="form-select js-pagination-select" data-table-page="select">
    <option value="1">1</option>
    <option value="2">2</option>
    <!-- ... -->
</select>
```

**C. Select per elementi per pagina** - [pagination.php:158-178](milkadmin/Theme/Plugins/Table/pagination.php#L158-L178)
```php
<select class="form-select js-pagination-el-per-page" data-table-page="limit">
    <option value="10">10</option>
    <option value="20">20</option>
    <!-- ... -->
</select>
```

### 3. Form Hidden con Parametri della Tabella

Ogni tabella contiene un form nascosto con tutti i parametri necessari per ricaricare i dati.

**In [plugin.php:284-302](milkadmin/Theme/Plugins/Table/plugin.php#L284-L302):**
```php
<form method="post" action="...">
    <input type="hidden" name="table_id" value="<?php _p($table_id); ?>">
    <input type="hidden" name="<?php _p($table_id); ?>[page]"
           class="js-field-table-page" value="<?php _p($actual_page); ?>">
    <input type="hidden" name="<?php _p($table_id); ?>[limit]"
           class="js-field-table-limit" value="<?php _p($page_info['limit']); ?>">
    <input type="hidden" name="<?php _p($table_id); ?>[order_field]"
           class="js-field-table-order-field" value="<?php _p($order_field); ?>">
    <input type="hidden" name="<?php _p($table_id); ?>[order_dir]"
           class="js-field-table-order-dir" value="<?php _p($order_dir); ?>">
    <input type="hidden" name="<?php _p($table_id); ?>[filters]"
           class="js-field-table-filters" value="<?php _p($page_info['filters']); ?>">
    <!-- ... -->
</form>
```

### 4. Inizializzazione JavaScript

Quando la pagina viene caricata, il JavaScript trova tutte le tabelle e le inizializza.

**In [table.js:734-738](milkadmin/Theme/Plugins/Table/table.js#L734-L738):**
```javascript
window.onload = function() {
    document.querySelectorAll('.js-table-container').forEach((el) => {
        new Table(el);
    });
};
```

Durante l'inizializzazione, vengono registrati gli event listener per la paginazione.

**In [table.js:257-280](milkadmin/Theme/Plugins/Table/table.js#L257-L280):**
```javascript
initialize_pagination() {
    // Bottoni numerici di pagina
    const pagination_buttons = this.el_container.querySelectorAll('.js-pagination-click');
    pagination_buttons.forEach(element => {
        element.addEventListener('click', () => {
            this.paginationClick(element);
        });
    });

    // Select dropdown pagine
    const pagination_selects = this.el_container.querySelectorAll('.js-pagination-select');
    pagination_selects.forEach(element => {
        element.addEventListener('change', () => {
            this.paginationSelect(element);
        });
    });

    // Select elementi per pagina
    const items_per_page_selects = this.el_container.querySelectorAll('.js-pagination-el-per-page');
    items_per_page_selects.forEach(element => {
        element.addEventListener('change', () => {
            this.paginationElPerPage(element);
        });
    });
}
```

### 5. Gestione Click su Paginazione

Quando l'utente clicca su un numero di pagina:

**In [table.js:572-581](milkadmin/Theme/Plugins/Table/table.js#L572-L581):**
```javascript
paginationClick(el) {
    const input_page = this.el_container.querySelector('.js-field-table-page');
    const page_val = el.getAttribute('data-table-page');

    if (input_page && page_val) {
        input_page.value = page_val;  // Aggiorna il valore della pagina
        this.clearActionFields();      // Pulisce campi action/ids
        this.sendForm();               // Invia il form via AJAX
    }
}
```

**CHIAVE**: Il metodo `this.el_container.querySelector()` cerca SOLO all'interno del container della tabella corrente, identificato dall'ID univoco. Questo permette di avere **multiple tabelle sulla stessa pagina** senza conflitti.

### 6. Invio AJAX del Form

Il metodo `sendForm()` è il cuore del sistema di aggiornamento.

**In [table.js:658-730](milkadmin/Theme/Plugins/Table/table.js#L658-L730):**
```javascript
async sendForm() {
    const form = this.el_container.querySelector('.js-table-form');

    // 1. Mostra indicatore di caricamento
    if (this.plugin_loading) {
        this.plugin_loading.show();
    }

    try {
        // 2. Prepara e invia dati via fetch
        const form_data = new FormData(form);
        const response = await fetch(form.getAttribute('action'), {
            method: 'POST',
            credentials: 'same-origin',
            body: form_data
        });

        const data = await response.json();

        // 3. Aggiorna SOLO il contenuto di questa tabella
        if ('html' in data && data.html != '') {
            this.el_container.innerHTML = data.html;
            updateContainer(this.el_container);
        }

        // 4. Reinizializza la tabella con i nuovi dati
        new Table(this.el_container, this.custom_init_fn);

        // 5. Auto-scroll se necessario
        if (!this.el_container.classList.contains('js-no-auto-scroll')) {
            if (!this.isElementTopVisible(this.el_scroll)) {
                this.el_scroll.scrollIntoView({ behavior: "smooth" });
            }
        }

    } catch (error) {
        console.error('Table form submission failed:', error);
        this.plugin_loading.hide();
    }
}
```

---

## Flusso Completo: Esempio Click su Pagina 3

```
1. USER CLICK
   ↓
   <span class="js-pagination-click" data-table-page="3">3</span>

2. JAVASCRIPT (table.js:572)
   ↓
   paginationClick(element)
   • Legge data-table-page="3"
   • Trova input nascosto: this.el_container.querySelector('.js-field-table-page')
   • Imposta: input_page.value = "3"

3. INVIO AJAX (table.js:658)
   ↓
   sendForm()
   • Raccoglie tutti i dati del form (page=3, limit=20, order_field=id, ecc.)
   • Invia POST alla action URL

4. SERVER RESPONSE
   ↓
   {
     "success": true,
     "html": "<div class='table-container'>... nuova tabella ...</div>"
   }

5. AGGIORNAMENTO DOM (table.js:696)
   ↓
   this.el_container.innerHTML = data.html
   • Sostituisce SOLO il contenuto del container con ID specifico
   • Altre tabelle sulla pagina NON vengono toccate

6. REINIZIALIZZAZIONE (table.js:703)
   ↓
   new Table(this.el_container)
   • Registra nuovamente tutti gli event listener
   • La tabella è pronta per nuove interazioni
```

---

## Meccanismo di Isolamento tra Multiple Tabelle

### Problema Risolto
Come fa il sistema a capire quale tabella aggiornare quando ci sono più tabelle nella stessa pagina?

### Soluzione: Scope del Container

Ogni istanza della classe `Table` memorizza il riferimento al proprio container DOM:

**In [table.js:32-48](milkadmin/Theme/Plugins/Table/table.js#L32-L48):**
```javascript
constructor(el_container, custom_init_fn = null) {
    this.el_container = el_container;  // Riferimento al DIV specifico
    // ...
    this.init();
    this.el_container.__itoComponent = this;  // Salva istanza sul DOM
}
```

Quando cerca elementi, usa SEMPRE `this.el_container.querySelector()`:

```javascript
// ✅ CORRETTO - Cerca SOLO nel container di questa tabella
const input_page = this.el_container.querySelector('.js-field-table-page');

// ❌ SBAGLIATO - Cercherebbe in tutta la pagina
const input_page = document.querySelector('.js-field-table-page');
```

### Esempio con 2 Tabelle

```html
<div id="table_users" class="js-table-container">
    <form class="js-table-form">
        <input class="js-field-table-page" value="1">
        <span class="js-pagination-click" data-table-page="2">2</span>
    </form>
</div>

<div id="table_orders" class="js-table-container">
    <form class="js-table-form">
        <input class="js-field-table-page" value="1">
        <span class="js-pagination-click" data-table-page="3">3</span>
    </form>
</div>
```

**Click su pagina 3 della tabella orders:**
1. Event listener trova l'elemento cliccato
2. JavaScript risale al `this.el_container` (div con id="table_orders")
3. Cerca input SOLO in quel container: `this.el_container.querySelector('.js-field-table-page')`
4. Aggiorna solo la tabella orders

---

## Configurazione PageInfo

Il sistema di paginazione richiede questi parametri nell'array `$page_info`:

```php
$page_info = [
    'id' => 'users_table',           // ID univoco tabella
    'total_record' => 150,           // Totale record
    'limit' => 20,                   // Record per pagina
    'limit_start' => 0,              // Offset di partenza
    'order_field' => 'id',           // Campo ordinamento
    'order_dir' => 'asc',            // Direzione ordinamento
    'filters' => '',                 // Filtri JSON
    'pagination' => true,            // Mostra paginazione
    'pag-goto-show' => true,         // Mostra select vai a pagina
    'pag-number-show' => true,       // Mostra bottoni numerici
    'pag-elperpage-show' => true,    // Mostra select elementi/pagina
    'pag-total-show' => true,        // Mostra totale record
    'pagination-limit' => 14,        // Max bottoni numerici visibili
];
```

---

## Caratteristiche Avanzate della Paginazione

### 1. Navigazione Rapida (pagination.php:44-70)

Frecce `«` e `»` permettono salti di 20 pagine:

```php
// Freccia sinistra: torna indietro di 20 pagine
$prev_page = max(1, $actual_page - 20);

// Freccia destra: avanti di 20 pagine
$next_page = min($pages, $actual_page + 20);
```

### 2. Ottimizzazione Select con Molte Pagine (pagination.php:83-152)

Per dataset con > 200 pagine, il select mostra solo:
- Prime 20 pagine
- 20 pagine intorno alla pagina corrente
- Ultime 20 pagine
- ~10 pagine intermedie distribuite uniformemente

### 3. Cambio Elementi per Pagina (table.js:600-615)

Quando si cambia il numero di elementi per pagina, torna automaticamente alla pagina 1:

```javascript
paginationElPerPage(el) {
    const page_input = this.el_container.querySelector('.js-field-table-page');
    if (page_input) {
        page_input.value = '1';  // Reset a pagina 1
    }

    const input_limit = this.el_container.querySelector('.js-field-table-limit');
    input_limit.value = el[el.selectedIndex].value;
    this.sendForm();
}
```

### 4. Auto-scroll Intelligente (table.js:711-715)

Se la tabella non è visibile dopo l'aggiornamento, fa scroll automatico:

```javascript
if (!this.isElementTopVisible(this.el_scroll)) {
    this.el_scroll.scrollIntoView({ behavior: "smooth" });
}
```

Si può disabilitare aggiungendo la classe `js-no-auto-scroll`:
```php
$page_info['auto-scroll'] = false;
```

---

## API Pubblica JavaScript

### Ricaricare la Tabella
```javascript
// Ottieni istanza della tabella
const table = document.getElementById('my_table').__itoComponent;

// Ricarica dati
table.reload();
```

### Cambiare Pagina Programmaticamente
```javascript
table.set_page(5);  // Vai a pagina 5
table.reload();     // Ricarica
```

### Aggiungere/Rimuovere Filtri
```javascript
table.filter_add('status=active');
table.filter_remove('status=active');
table.filter_clear();
table.reload();
```

---

## Eventi e Hook

Il sistema supporta hook custom per estendere funzionalità:

```javascript
// Hook chiamato dopo inizializzazione
callHook('table-init', tableInstance);

// Hook chiamato quando cambiano checkbox selezionate
callHook(`${table_id}-checkbox-change`, selectedIds, tableInstance);

// Hook chiamato prima di eseguire un'azione
callHook(`table-action-${action_name}`, ids, element, form, shouldProceed);
```

---

## Gestione Errori

```javascript
try {
    // Invio AJAX
} catch (error) {
    console.error('Table form submission failed:', error);

    // Nasconde loading
    this.plugin_loading.hide();

    // Mostra toast errore
    if (typeof window.toasts !== 'undefined') {
        window.toasts.show('An error occurred while updating the table', 'danger');
    }
}
```

---

## Performance e Best Practices

### 1. Prevenzione Doppie Inizializzazioni
```javascript
if (this.is_init) {
    return;  // Già inizializzato
}
```

### 2. Riutilizzo Istanze
L'istanza viene salvata sul DOM per accesso rapido:
```javascript
this.el_container.__itoComponent = this;
```

### 3. Async/Await per AJAX
Usa fetch API moderno invece di XMLHttpRequest legacy.

### 4. Event Delegation
Gli eventi sono registrati su elementi specifici, non sul document.

---

## Conclusione

Il sistema di paginazione di MilkAdmin è un esempio di architettura modulare che:

✅ Supporta **multiple tabelle indipendenti** sulla stessa pagina
✅ Usa **AJAX** per aggiornamenti senza reload completo
✅ Mantiene **stato** (ordinamento, filtri) tra le pagine
✅ Fornisce **feedback visivo** con loading indicators
✅ È **estensibile** tramite hook custom
✅ Gestisce **grandi dataset** con paginazione ottimizzata

La chiave è l'uso di **container scoped** (`this.el_container`) che isola completamente ogni tabella dalle altre.
