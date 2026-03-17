# `tests/Unit/App`

Questa cartella raccoglie i test PHPUnit del core applicativo di MilkAdmin.

## Sottocartelle principali

### `Analytics`
Copre primitive e pipeline di aggregazione, grouping, resampling e manipolazione di serie dati.

### `Database`
Copre query, relazioni, result set, `getByIdAndUpdate()`, `save()`, `prepareData()`, campi speciali, regressioni del model layer e test documentativi stabili come timezone e `buildTable()`.

### `ExpressionParser`
Copre parser, evaluator, dot notation, validazione espressiva e calculated fields.

## File test al livello root di `App`

I file nel root di questa cartella coprono classi core singole o piccoli gruppi coesi, ad esempio:
- `ConfigTest.php`
- `RequestTest.php`
- `ResponseTest.php`
- `RouteTest.php`
- `SettingsTest.php`
- `FileTest.php`
- `FileSecurityTest.php`
- `ExtensionLoaderTest.php`

## Regola pratica

Se il codice testato vive sotto `milkadmin/App/<Area>`, il test dovrebbe stare sotto `tests/Unit/App/<Area>`.
Se invece testa una classe core isolata senza una sottostruttura chiara, il test puo' restare direttamente qui al root di `tests/Unit/App`.
