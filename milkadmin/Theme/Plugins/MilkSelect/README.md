# MilkSelect Plugin

MilkSelect è un plugin di autocomplete select per MilkAdmin che supporta sia la selezione singola che multipla con un'interfaccia utente moderna e intuitiva.

## Caratteristiche

- ✅ **Selezione Singola**: Seleziona un solo valore dalla lista
- ✅ **Selezione Multipla**: Seleziona più valori contemporaneamente
- ✅ **Autocomplete**: Filtra le opzioni mentre digiti
- ✅ **Navigazione da tastiera**: Frecce, Tab (ciclico), Enter, Escape
- ✅ **Array Associativi**: Supporto completo per array con chiavi numeriche (es: ID database)
- ✅ **Valori preselezionati**: Supporto per valori di default
- ✅ **Posizionamento intelligente**: Il dropdown si apre sopra quando è vicino al fondo pagina
- ✅ **Aggiornamento dinamico**: Cambia le opzioni programmaticamente
- ✅ **Eventi personalizzati**: Listener per cambiamenti e aggiornamenti
- ✅ **Validazione form**: Supporto attributo `required`

## Installazione

Il plugin è già integrato in MilkAdmin. Non è necessaria alcuna installazione aggiuntiva.

## Utilizzo Base

### Selezione Singola

```php
Get::themePlugin('MilkSelect', [
    'id' => 'city-select',
    'options' => ['Roma', 'Milano', 'Napoli', 'Torino', 'Firenze'],
    'type' => 'single',
    'name' => 'city'
]);
```

**Output HTML generato:**
```html
<input type="hidden" id="city-select" name="city" data-select-type="single">
<script>
  const select = new MilkSelect(document.getElementById('city-select'), ['Roma', 'Milano', 'Napoli', 'Torino', 'Firenze']);
</script>
```

### Selezione Multipla

```php
Get::themePlugin('MilkSelect', [
    'id' => 'languages-select',
    'options' => ['JavaScript', 'Python', 'PHP', 'Java', 'C++'],
    'type' => 'multiple',
    'name' => 'languages'
]);
```

## Parametri

| Parametro | Tipo | Default | Descrizione |
|-----------|------|---------|-------------|
| `id` | string | auto-generato | ID univoco per l'elemento (formato: `milkselect_xxxxx`) |
| `options` | array | `[]` | Array di opzioni. Può essere indicizzato `['A', 'B']` o associativo `[1 => 'A', 2 => 'B']` |
| `type` | string | `'single'` | Tipo di selezione: `'single'` o `'multiple'` |
| `value` | mixed | `null` | Valore(i) preselezionato(i). Può essere stringa, array di stringhe, o chiave numerica (per array associativi) |
| `name` | string | `''` | Attributo name per l'input hidden (utile nei form) |
| `placeholder` | string | auto | Testo placeholder (default: "Cerca o seleziona..." per single, "Aggiungi valore..." per multiple) |
| `required` | bool | `false` | Se true, rende il campo obbligatorio per la validazione form |
| `class` | string | `''` | Classi CSS aggiuntive per l'input hidden |

## Esempi Avanzati

### Array Associativi (Caso d'uso più comune)

Gli array associativi sono ideali quando si lavora con dati provenienti da database che hanno ID numerici.

#### Single Select con ID Database

```php
// Dati dal database: ID => Nome
$cities = [
    1 => 'Milano',
    2 => 'Roma',
    3 => 'Napoli',
    4 => 'Torino',
    5 => 'Firenze'
];

Get::themePlugin('MilkSelect', [
    'id' => 'city-select',
    'options' => $cities,
    'type' => 'single',
    'value' => 2,  // Preseleziona Roma usando l'ID
    'name' => 'city_id'
]);
```

**Output salvato**: Il valore sarà `"Roma"` (il testo visualizzato all'utente).

#### Multiple Select con Array Associativo

```php
$languages = [
    10 => 'PHP',
    20 => 'JavaScript',
    30 => 'Python',
    40 => 'Java'
];

Get::themePlugin('MilkSelect', [
    'id' => 'lang-select',
    'options' => $languages,
    'type' => 'multiple',
    'value' => ['PHP', 'JavaScript'],  // Preseleziona usando i valori
    'name' => 'language_ids'
]);
```

#### Esempio Completo Database

```php
// Simula query database
$countries = [
    100 => 'Italia',
    200 => 'Francia',
    300 => 'Germania',
    400 => 'Spagna'
];

// Utente corrente ha country_id = 100
$current_country_id = 100;

Get::themePlugin('MilkSelect', [
    'id' => 'country-select',
    'options' => $countries,
    'type' => 'single',
    'value' => $current_country_id,  // Preseleziona Italia
    'name' => 'country_id',
    'required' => true
]);
```

### Con Valore Preselezionato (Single)

```php
Get::themePlugin('MilkSelect', [
    'id' => 'city-preset',
    'options' => ['Roma', 'Milano', 'Napoli', 'Torino'],
    'type' => 'single',
    'value' => 'Milano',  // Valore già selezionato
    'name' => 'city'
]);
```

### Con Valori Preselezionati (Multiple)

```php
Get::themePlugin('MilkSelect', [
    'id' => 'skills-preset',
    'options' => ['HTML', 'CSS', 'JavaScript', 'PHP', 'SQL'],
    'type' => 'multiple',
    'value' => ['PHP', 'JavaScript'],  // Array di valori preselezionati
    'name' => 'skills'
]);
```

### Con Campo Required

```php
Get::themePlugin('MilkSelect', [
    'id' => 'country-select',
    'options' => ['Italia', 'Francia', 'Germania', 'Spagna'],
    'type' => 'single',
    'name' => 'country',
    'required' => true,  // Campo obbligatorio
    'placeholder' => 'Seleziona il tuo paese...'
]);
```

### In un Form Completo

```php
<form method="POST" action="/save">
    <h3>Informazioni Personali</h3>

    <label>Città preferita:</label>
    <?php
    Get::themePlugin('MilkSelect', [
        'id' => 'favorite-city',
        'options' => ['Roma', 'Milano', 'Napoli', 'Torino', 'Firenze'],
        'type' => 'single',
        'name' => 'favorite_city',
        'required' => true
    ]);
    ?>

    <label>Competenze tecniche:</label>
    <?php
    Get::themePlugin('MilkSelect', [
        'id' => 'tech-skills',
        'options' => ['PHP', 'JavaScript', 'Python', 'SQL', 'HTML', 'CSS'],
        'type' => 'multiple',
        'name' => 'skills'
    ]);
    ?>

    <button type="submit">Salva</button>
</form>
```

## API JavaScript

### Accesso all'Istanza

Ogni MilkSelect crea un'istanza accessibile tramite l'elemento:

```javascript
const element = document.getElementById('city-select');
const milkSelectInstance = element.milkSelectInstance;
```

### Metodi Disponibili

#### `updateOptions(newOptions)`

Aggiorna dinamicamente le opzioni del select.

```javascript
const element = document.getElementById('city-select');
const instance = element.milkSelectInstance;

// Cambia le opzioni
instance.updateOptions(['Nuova Opzione 1', 'Nuova Opzione 2', 'Nuova Opzione 3']);
```

**Esempio PHP + JS:**

```php
<?php
Get::themePlugin('MilkSelect', [
    'id' => 'dynamic-select',
    'options' => ['Opzione 1', 'Opzione 2'],
    'type' => 'single'
]);
?>

<button onclick="updateOptions()">Aggiorna Opzioni</button>

<script>
function updateOptions() {
    const element = document.getElementById('dynamic-select');
    element.milkSelectInstance.updateOptions(['Nuova A', 'Nuova B', 'Nuova C']);
}
</script>
```

### Eventi

#### `change`

Scatta quando il valore cambia.

```javascript
const element = document.getElementById('city-select');

element.addEventListener('change', function() {
    const value = this.value;
    const parsed = JSON.parse(value);
    console.log('Valore selezionato:', parsed);
});
```

#### `optionsUpdated`

Scatta quando le opzioni vengono aggiornate tramite `updateOptions()`.

```javascript
const element = document.getElementById('city-select');
const container = element.nextElementSibling; // Il container MilkSelect

container.addEventListener('optionsUpdated', function(event) {
    console.log('Nuove opzioni:', event.detail);
});
```

## Formato dei Dati

### Selezione Singola

Il valore viene salvato come **JSON string** contenente il valore selezionato:

```javascript
// Valore nell'input hidden:
"Milano"

// Leggere il valore in JavaScript:
const element = document.getElementById('city-select');
const value = JSON.parse(element.value); // "Milano"
```

```php
// Leggere il valore in PHP:
$city = json_decode($_POST['city']); // "Milano"
```

### Selezione Multipla

I valori vengono salvati come **JSON array**:

```javascript
// Valore nell'input hidden:
["PHP","JavaScript","Python"]

// Leggere il valore in JavaScript:
const element = document.getElementById('languages-select');
const values = JSON.parse(element.value); // ["PHP", "JavaScript", "Python"]
```

```php
// Leggere il valore in PHP:
$languages = json_decode($_POST['languages']); // ["PHP", "JavaScript", "Python"]
```

## Navigazione da Tastiera

| Tasto | Azione |
|-------|--------|
| `↓` | Sposta la selezione in basso |
| `↑` | Sposta la selezione in alto |
| `Tab` | Cicla tra le opzioni (quando il dropdown è aperto). Arrivato all'ultima opzione ricomincia dalla prima |
| `Enter` | Seleziona l'opzione attiva. Se nessuna opzione è attiva, seleziona la prima della lista |
| `Escape` | Chiude il dropdown |

## Styling

Il plugin utilizza le seguenti classi CSS (definite in `milkselect.css`):

- `.cs-autocomplete-container` - Container principale
- `.cs-autocomplete-wrapper-single` - Wrapper per selezione singola
- `.cs-autocomplete-wrapper-multiple` - Wrapper per selezione multipla
- `.cs-autocomplete-input` - Input per selezione multipla
- `.cs-autocomplete-input-single` - Input per selezione singola
- `.cs-autocomplete-dropdown` - Dropdown delle opzioni
- `.cs-autocomplete-item` - Singola opzione nel dropdown
- `.cs-autocomplete-selected-item` - Item selezionato (multiple)
- `.cs-clear-button` - Bottone per cancellare (single)

## Test

Esegui la suite di test completa visitando:

```
/test/test-milkselect-plugin.php
```

La suite di test include:
- ✅ Selezione singola
- ✅ Selezione multipla
- ✅ Valori preselezionati (single e multiple)
- ✅ Aggiornamento dinamico opzioni
- ✅ Integrazione con form
- ✅ Validazione required

## Compatibilità

- **Browser**: Chrome, Firefox, Safari, Edge (versioni moderne)
- **PHP**: 7.4+
- **MilkAdmin**: Tutte le versioni recenti

## Troubleshooting

### Il select non appare

Verifica che il file CSS sia caricato:

```html
<link rel="stylesheet" href="milkadmin/Theme/Plugins/MilkSelect/milkselect.css">
```

### Le opzioni non vengono visualizzate

Controlla che l'array `options` non sia vuoto:

```php
// ❌ Sbagliato
Get::themePlugin('MilkSelect', [
    'options' => []  // Nessuna opzione
]);

// ✅ Corretto
Get::themePlugin('MilkSelect', [
    'options' => ['Opzione 1', 'Opzione 2']
]);
```

### Il valore non viene salvato nel form

Assicurati di aver specificato l'attributo `name`:

```php
Get::themePlugin('MilkSelect', [
    'id' => 'my-select',
    'options' => ['A', 'B', 'C'],
    'name' => 'my_field'  // Importante per il submit del form
]);
```

## Esempi Completi

### Esempio 1: Select Dipendenti (Regione → Provincia → Città)

```php
<h3>Regione:</h3>
<?php
Get::themePlugin('MilkSelect', [
    'id' => 'region-select',
    'options' => ['Lazio', 'Lombardia', 'Campania'],
    'type' => 'single',
    'name' => 'region'
]);
?>

<h3>Provincia:</h3>
<?php
Get::themePlugin('MilkSelect', [
    'id' => 'province-select',
    'options' => [],  // Sarà popolato dinamicamente
    'type' => 'single',
    'name' => 'province'
]);
?>

<script>
const provinces = {
    'Lazio': ['Roma', 'Frosinone', 'Latina', 'Rieti', 'Viterbo'],
    'Lombardia': ['Milano', 'Bergamo', 'Brescia', 'Como', 'Cremona'],
    'Campania': ['Napoli', 'Salerno', 'Avellino', 'Benevento', 'Caserta']
};

document.getElementById('region-select').addEventListener('change', function() {
    const region = JSON.parse(this.value);
    const provinceSelect = document.getElementById('province-select');

    if (region && provinces[region]) {
        provinceSelect.milkSelectInstance.updateOptions(provinces[region]);
    }
});
</script>
```

### Esempio 2: Filtro di Ricerca con Risultati Dinamici

```php
<h3>Cerca Utenti:</h3>
<?php
Get::themePlugin('MilkSelect', [
    'id' => 'user-search',
    'options' => [],  // Verrà popolato da AJAX
    'type' => 'single',
    'name' => 'user_id',
    'placeholder' => 'Cerca utente per nome...'
]);
?>

<script>
const userSelect = document.getElementById('user-search');

// Simula una chiamata AJAX
function searchUsers(query) {
    // In produzione, usa fetch() per chiamare l'API
    setTimeout(() => {
        const users = ['Mario Rossi', 'Luigi Verdi', 'Anna Bianchi', 'Giulia Neri'];
        const filtered = users.filter(u => u.toLowerCase().includes(query.toLowerCase()));
        userSelect.milkSelectInstance.updateOptions(filtered);
    }, 300);
}

// Quando l'utente digita
userSelect.nextElementSibling.querySelector('input').addEventListener('input', function(e) {
    if (e.target.value.length >= 2) {
        searchUsers(e.target.value);
    }
});
</script>
```

## Licenza

Parte del framework MilkAdmin.

## Supporto

Per bug e richieste di funzionalità, contatta il team di sviluppo MilkAdmin.
