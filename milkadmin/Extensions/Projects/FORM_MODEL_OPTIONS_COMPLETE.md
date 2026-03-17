# Form Generation Options: AbstractModel (RuleBuilder) + FormBuilder

Documento tecnico completo delle opzioni reali disponibili nel codice per la generazione e configurazione dei form.

Riferimenti principali:
- `milkadmin/App/Abstracts/RuleBuilder.php`
- `milkadmin/App/Abstracts/AbstractModel.php`
- `milkadmin/App/ModelValidator.php`
- `milkadmin/Builders/FormBuilder.php`
- `milkadmin/Builders/Traits/FormBuilder/*.php`
- `milkadmin/App/ObjectToForm.php`
- `milkadmin/App/Form.php`

## 1) AbstractModel / RuleBuilder (opzioni Model-side)

Questa sezione copre tutte le opzioni configurabili nel Model tramite `configure(RuleBuilder $rule)`.

### 1.1 Opzioni globali modello (non legate a un campo)

| Metodo | Parametri | Effetto |
|---|---|---|
| `table(string $name)` | nome tabella | Imposta tabella SQL del model |
| `db(string $name)` | `db`, `db2`, `array`, `arraydb` | Imposta connessione DB |
| `renameField(string $from, string $to)` | mappa rename | Richiesta rename campo in schema |
| `extensions(array $extensions)` | lista extension | Carica estensioni model |
| `removePrimaryKeys()` | - | Rimuove il campo primary dai rules (utile audit/cloni schema) |
| `getTable()` | - | Legge tabella |
| `getPrimaryKey()` | - | Legge primary key |
| `getDbType()` | - | Legge db type |
| `getExtensions()` | - | Legge extensions |
| `getRenameFields()` | - | Legge mappa rename |
| `setRules(array $rules)` | rules completi | Sovrascrive rules interni |
| `clear()` | - | Azzera rules |
| `getAllHasMeta()` | - | Raccoglie tutte le config `hasMeta` |

### 1.2 Definizione campi (metodi creator)

| Metodo | Parametri | Note |
|---|---|---|
| `field(string $name, string $type = 'string')` | nome + tipo base | Crea campo base e imposta `current_field` |
| `ChangeCurrentField($name)` | nome campo | Cambia campo corrente per chaining |
| `id(string $name = 'id')` | nome | Tipo `id`, primary, hidden, not-null |
| `primaryKey(string $name)` | nome | Alias di `id(name)` |
| `array(string $name)` | nome | Campo array (salvato come text/json) |
| `string(string $name, int $length = 255)` | nome, lunghezza | Tipo string |
| `title(string $name = 'title', int $length = 255)` | nome, lunghezza | String "title", marca `_is_title_field`, required nel form |
| `text(string $name)` | nome | Tipo text |
| `int(string $name)` | nome | Tipo int |
| `decimal(string $name, int $length = 10, int $precision = 2)` | nome, digits, precision | Tipo float + pattern + `formType(number)` + errore default |
| `datetime(string $name)` | nome | Tipo datetime + `timezone_conversion=true` |
| `created_at(string $name = 'created_at')` | nome | Datetime auto + hidden da edit (`hideFromEdit`) |
| `date(string $name)` | nome | Tipo date |
| `time(string $name)` | nome | Tipo time |
| `timestamp(string $name)` | nome | Tipo timestamp |
| `email(string $name)` | nome | String(255) + `formType(email)` |
| `tel(string $name)` | nome | String(25) + `formType(tel)` |
| `url(string $name)` | nome | String(255) + `formType(url)` |
| `file(string $name)` | nome | Tipo array + `formType(file)` |
| `image(string $name)` | nome | Tipo array + `formType(image)` + `accept=image/*` |
| `boolean(string $name)` | nome | Tipo bool |
| `checkbox(string $name)` | nome | Alias di `boolean()` |
| `checkboxes(string $name, array $options)` | nome, opzioni | Tipo array + `formType(checkboxes)` |
| `radio(string $name, array $options)` | nome, opzioni | Tipo radio |
| `list(string $name, array $options)` | nome, opzioni | Tipo list/select |
| `select(string $name, array $options)` | nome, opzioni | Alias di `list()` |
| `enum(string $name, array $options)` | nome, opzioni | Tipo enum |

### 1.3 Configurazione campo (fluent)

| Metodo | Parametri | Effetto |
|---|---|---|
| `label(string $label)` | label | Label generale campo |
| `default($value)` | default | Valore default |
| `options(array $options)` | opzioni | Opzioni lista/radio/checkboxes |
| `apiUrl(string $url, ?string $display_field = null)` | endpoint + display field | Caricamento opzioni remoto (milkSelect) |
| `checkboxValues($checked, $unchecked = null)` | checked/unchecked | Valori custom checkbox |
| `saveValue($value)` | valore forzato | Sempre salvato quel valore |
| `changeType($name, string $type)` | campo + tipo | Cambia tipo di un campo gia creato |
| `nullable(bool $nullable = true)` | bool | Permette/nulla il null |
| `required()` | - | Imposta `form-params.required=true` |
| `requireIf(string $expression)` | expr | Required condizionale server-side (`required_expr`) |
| `primary($primary_key)` | nome pk | Marca primary sul campo corrente |
| `unique()` | - | Unique index |
| `index()` | - | Index |
| `hideFromList()` | - | `list=false` |
| `hideFromEdit()` | - | `edit=false` |
| `hideFromView()` | - | `view=false` |
| `hide()` | - | `list/edit/view=false` |
| `excludeFromDatabase()` | - | `sql=false` |
| `formType(string $type)` | tipo form | Override tipo input |
| `formLabel(string $label)` | label form | Override label form |
| `formParams(array $params)` | hash parametri | Parametri HTML + sync pattern/min/max length |
| `error(string $message)` | messaggio | `form-params.invalid-feedback` |
| `calcExpr(string $expression)` | expr | Campo calcolato (server + mapping client) |
| `validateExpr(string $expression, ?string $message = null)` | expr + msg | Validazione a espressione |
| `step($value)` | numero | `form-params.step` |
| `min($value)` | numero o nome campo | Min numerico/data o min length (string), supporta ref ad altro campo |
| `max($value)` | numero o nome campo | Max numerico/data o max length (string), supporta ref ad altro campo |
| `unsigned()` | - | Marca unsigned |
| `noTimezoneConversion()` | - | Disattiva timezone conversion del datetime/date/time |
| `property(string $key, $value)` | key/value | Aggiunge chiave custom nel rule |
| `properties(array $properties)` | array | Merge multiproprieta |
| `customize(callable $callback)` | callback | Modifica raw del rule campo |

### 1.4 Callback su valore campo

| Metodo | Parametri | Effetto |
|---|---|---|
| `getter(callable $fn)` | callable | Trasformazione valore in lettura (`_get`) |
| `rawGetter(callable $fn)` | callable | Getter raw (`_get_raw`) - deprecato |
| `setter(callable $fn)` | callable | Trasformazione valore in scrittura (`_set`) |
| `editor(callable $fn)` | callable | Handler edit (`_edit`) |

### 1.5 Relazioni

| Metodo | Parametri | Effetto |
|---|---|---|
| `belongsTo(string $alias, string $related_model, ?string $related_key = 'id')` | alias/modello/key | FK nel model corrente -> modello correlato |
| `hasOne(string $alias, string $related_model, string $foreign_key_in_related, string $onDelete = 'CASCADE', bool $allowCascadeSave = false)` | alias/modello/fk | 1:1, FK nel modello figlio |
| `hasMany(string $alias, string $related_model, string $foreign_key_in_related, string $onDelete = 'CASCADE', bool $allowCascadeSave = false)` | alias/modello/fk | 1:N, FK nel modello figlio |
| `withCount(string $alias, string $related_model, string $foreign_key_in_related)` | alias/modello/fk | Campo virtuale count con subquery |
| `hasMeta(string $alias, string $related_model, ?string $foreign_key = null, ?string $local_key = null, string $meta_key_column = 'meta_key', string $meta_value_column = 'meta_value', ?string $meta_key_value = null)` | config meta | Relazione EAV/meta |
| `where(string $condition, array $params = [])` | SQL where + bind | Filtro sulla relazione appena definita |

`onDelete` accetta: `CASCADE`, `SET NULL`, `RESTRICT`.

### 1.6 Chiavi rule effettive (output `getRules()`)

Chiavi principali presenti nei rules campo:

- `type`, `length`, `precision`, `nullable`, `default`
- `primary`, `unique`, `index`
- `label`, `options`
- `list`, `edit`, `view`, `sql`
- `form-type`, `form-label`, `form-params`
- `timezone_conversion`
- `checkbox_checked`, `checkbox_unchecked`

Chiavi avanzate possibili:

- `required_expr`, `calc_expr`, `validate_expr`
- `min_length`, `max_length`, `min_field`, `max_field`, `pattern`, `regex`
- `save_value`, `unsigned`
- `api_url`, `api_display_field`
- `_is_title_field`, `_auto_created_at`
- `_get`, `_get_raw`, `_set`, `_edit`
- `relationship`, `withCount`, `hasMeta`, `_meta_config`, `virtual`

### 1.7 Opzioni validate/runtime usate da AbstractModel + ModelValidator

- Required: `form-params.required` e/o `required_expr`
- Nullable: `nullable` o `form-params.nullable`
- Numeric/date min/max: `form-params.min`, `form-params.max`, `min_field`, `max_field`
- String min/max: `form-params.minlength|maxlength|min_length|max_length`, `length`
- Pattern: `form-params.pattern`, oppure `pattern`, oppure `regex`
- Step: `form-params.step`
- Validation expression: `validate_expr` (fallback compat: `validation_expr`, `validation`)
- Validation message: `form-params.invalid-feedback` (fallback: `validation_message`)

## 2) FormBuilder (opzioni Form-side)

Questa sezione copre tutte le opzioni disponibili nel builder che genera/renderizza il form.

### 2.1 Bootstrap/creazione e contesto

| Metodo | Parametri | Effetto |
|---|---|---|
| `FormBuilder::create($model, $page = '', $url_success_or_json = null, $url_error = null)` | factory | Istanzia builder; con `false` come terzo parametro attiva JSON mode |
| `setPage(string $page_name)` / `page(string $page)` | pagina | Imposta page |
| `urlSuccess(string $url)` / `urlError(string $url)` | url | Redirect success/error |
| `currentAction(string $current_action)` | azione | Imposta action corrente |
| `formAttributes(array $attributes)` | attr form | Merge attributi `<form>` |
| `setId($formId)` | id html | Imposta id form |
| `url($url_success_or_json = null, $url_error = null)` | legacy | Set rapido url |
| `activeFetch()` | - | Forza risposta ajax/json |
| `setData(AbstractModel $model)` | model data | Usa oggetto custom come source dati |
| `setIdRequest($id)` | key request | Nome parametro id (default `id`) |
| `getModel()` | - | Restituisce model corrente usato dal builder |
| `getPage()` | - | Restituisce page corrente |
| `customData(string $key, $value)` | key/value | Hidden input top-level extra |
| `setMessageSuccess(string $message)` | testo | Messaggio custom save ok |
| `setMessageError(string $message)` | testo | Messaggio custom save ko |

### 2.2 API field-first (configurazione campo)

| Metodo | Parametri | Effetto |
|---|---|---|
| `field(string $key)` | nome campo | Seleziona/crea campo corrente |
| `type(string $type)` | tipo dati | Imposta `type` |
| `formType(string $formType)` | tipo input | Imposta `form-type` |
| `label(string $label)` | label | Imposta label |
| `options(array $options)` | opzioni | Imposta options |
| `required(bool $required = true)` | bool | Imposta required e `form-params.required` |
| `helpText(string $helpText)` | testo | `form-params.help-text` |
| `value(mixed $value)` | valore | `set_value` forzato |
| `default(mixed $value)` | valore | default campo |
| `disabled(bool $disabled = true)` | bool | `form-params.disabled` |
| `readonly(bool $readonly = true)` | bool | `form-params.readonly` |
| `class(string $class)` | css class | `form-params.class` |
| `errorMessage(string $message)` | msg | `form-params.invalid-feedback` |
| `calcExpr(string $expression)` | expr | `form-params.data-milk-expr` |
| `defaultExpr(string $expression)` | expr | `form-params.data-milk-default-expr` |
| `validateExpr(string $expression, ?string $message = null)` | expr + msg | `data-milk-validate-expr` + `data-milk-message` |
| `requireIf(string $expression, ?string $message = null)` | expr + msg | `data-milk-required-if` + `data-milk-message` |
| `hide()` | - | Set `form-type=hidden` (sul campo corrente) |
| `showIf(string $field_or_expression, ?string $expression = null)` | expr | `form-params.data-milk-show` |
| `removeField(?string $name = null)` | nome o corrente | Rimuove campo dal form |
| `resetFields()` | - | Nasconde quasi tutti i campi modello, mantenendo PK |
| `moveBefore(string $fieldName)` / `before(string $fieldName)` | target | Riordina campo |
| `moveAfter(string $fieldName)` / `after(string $fieldName)` | target | Riordina campo |
| `debug()` | - | Dump campo corrente + die |

### 2.3 Gestione campi/layout/html

| Metodo | Parametri | Effetto |
|---|---|---|
| `fieldOrder(array $order)` | ordine | Forza ordine rendering |
| `addFieldsFromObject(object $object, string $context = 'edit', array $values = [])` | source rules + context | Importa campi dal model/object e merge con override builder |
| `addField($field_name, $type, $options = [])` | nome/tipo/opzioni | Aggiunge campo custom |
| `modifyField(string $field_name, array $options, string $position_before = '')` | modifica + posizione | Merge opzioni e opzionale riposizionamento |
| `removeFieldCondition(string $field_name)` | nome | Rimuove `data-milk-show` |
| `addHtmlBeforeFields(string $html)` | html | blocchi html prima campi |
| `addHtmlAfterFields(string $html)` | html | blocchi html dopo campi |
| `addHtmlBeforeSubmit(string $html)` | html | blocchi html prima bottoni |
| `addHtml($html, $field_name = '')` | html + nome opzionale | Inserisce campo virtuale html |
| `addContainer(string $id, array $fields, $cols, string $position_before = '', string $title = '', array $attributes = [])` | container config | Layout bootstrap a colonne con campi/inline html |
| `addRelatedField(string $field_path, ?string $label = null, string $position_before = '')` | `relation.field` | Aggiunge campo da relazione hasOne/belongsTo |

`$cols` in `addContainer` puo essere:
- int: numero colonne uguali (`12/$cols`)
- array: dimensioni bootstrap per riga (es. `[4,5,3]`)

### 2.4 Azioni (bottoni form)

| Metodo | Parametri | Effetto |
|---|---|---|
| `setActions(array $actions)` | config azioni | Sostituisce tutte le azioni |
| `addActions(array $actions)` | config azioni | Aggiunge azioni |
| `addStandardActions(bool $include_delete = false, ?string $cancel_link = null)` | flag/link | Save + Cancel (+Delete opzionale) |
| `submit(string $text = 'Save', array $attributes = [])` | legacy | fallback submit semplice |
| `getPressedAction()` | - | Azione premuta |
| `getFunctionResults()` | - | Output callback azione |

Config supportata per ogni action (`setActions`/`addActions`):
- `label`
- `type` (`submit`, `button`, `link`)
- `class`
- `validate` (`false` => `formnovalidate`)
- `attributes` (attr html custom)
- `action` callback `function(FormBuilder $form_builder, array $request): array`
- `link` (solo `type=link`)
- `confirm`
- `onclick`
- `target`
- `showIf` (array condizione)

`showIf` operatori supportati per bottoni: `empty`, `not_empty`, `=`, `==`, `!=`, `<>`, `>`, `<`, `>=`, `<=`.

Helper statici:
- `saveAction()`
- `reloadAction()`
- `deleteAction(?string $redirect_success = null, ?string $redirect_error = null)`
- `hasExistingRecord(object $model)`
- `fieldCondition(string $field_name, string $operator, $value)`

### 2.5 Render/response mode

| Metodo | Parametri | Effetto |
|---|---|---|
| `render()` | - | Genera HTML form completo |
| `getHtml()` / `getForm()` / `__toString()` | - | Alias di render |
| `asOffcanvas()` | - | Risposta strutturata tipo offcanvas |
| `asModal()` | - | Risposta strutturata tipo modal |
| `asDom(string $id)` | selector id | Risposta su elemento DOM |
| `getResponse()` | - | Ritorna payload completo (stato, msg, form, reload list) |
| `setTitle(string $new, ?string $edit = null)` | titoli | Titolo new/edit nelle response |
| `dataListId(string $id)` | id tabella/lista | Abilita reload lista dopo action |
| `markReload(bool $reload = true)` | bool | Forza `show` invece di `hide` in modal/offcanvas/dom |
| `size(string $size)` | `sm`, `lg`, `xl`, `fullscreen` | Dimensione modal/offcanvas |

### 2.6 Operazioni salvataggio/cancellazione/upload

| Metodo | Parametri | Effetto |
|---|---|---|
| `save(array $request)` | dati form | Validazione + save + hook extension |
| `delete(array $request, ?string $redirect_success = null, ?string $redirect_error = null)` | request | Cancellazione record |
| `moveUploadedFile($obj)` | model | Gestione campi `file`/`image` e move temp->media |
| `applyFieldConfigToModel(object $model)` | model | Sincronizza config FormBuilder nei rules model |
| `ActionExecution()` | - | Esegue callback bottone premuto |

### 2.7 Extension API FormBuilder

| Metodo | Parametri | Effetto |
|---|---|---|
| `extensions(array $extensions)` | lista extension | Carica/merge extension builder |
| `getLoadedExtension(string $extension_name)` | nome extension | Ritorna istanza extension |
| `getFields()` | - | Espone fields correnti (usato da extension) |

Hook invocati dal builder:
- `configure`
- `beforeSave` (pipeline mutabile request)
- `afterSave`
- `beforeRender` (pipeline mutabile fields)

## 3) Tipi input e `form-params` effettivi (runtime form)

Queste opzioni valgono sia quando arrivi da RuleBuilder, sia quando modifichi da FormBuilder.

### 3.1 Tipi `form-type`/`type` supportati dal renderer

Supportati in `ObjectToForm::getInput()`:

- `hidden`
- `string`
- `int`
- `number`
- `float`
- `text`, `textarea`
- `password`
- `email`
- `url`
- `tel`
- `date`
- `datetime`, `datetime-local`
- `time`
- `timestamp`
- `select`, `list`
- `milkSelect`
- `enum`
- `checkbox`
- `checkboxes`
- `radio`, `radios`
- `file`
- `image`
- `milk-select`
- `editor`
- `html`
- `openTag`, `closeTag`
- custom hook `form-<type>`

### 3.2 `form-params` (opzioni piu usate)

Opzioni comuni utili:

- Validazione HTML: `required`, `min`, `max`, `minlength`, `maxlength`, `pattern`, `step`
- Stato input: `disabled`, `readonly`, `hidden`, `autocomplete`, `autofocus`
- UX: `placeholder`, `class`, `help-text`, `invalid-feedback`
- Toggle legacy: `toggle-field`, `toggle-value`
- Espressioni MilkForm: `data-milk-show`, `data-milk-expr`, `data-milk-default-expr`, `data-milk-validate-expr`, `data-milk-required-if`, `data-milk-message`, `data-milk-precision`
- File/image upload: `accept`, `multiple`, `max-files`, `max-size`, `upload-dir`
- Select/MilkSelect: `api_url`, `display_value`, `type` (`single|multiple`), `floating`

Nota: `Form::attr()` passa come attributi HTML tutti i valori scalari non riservati, quindi puoi aggiungere anche `data-*` custom direttamente in `form-params`.

---

Questo file e pensato come riferimento "completo" per sviluppo e per allineare JSON schema, Model e FormBuilder in un unico contesto.
