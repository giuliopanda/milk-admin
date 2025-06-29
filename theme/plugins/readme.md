I plugin sono blocchi js/css html/php per la gestione delle funzionalità del template

Ogni plugin è una cartella con un file php, un css e un js. I plugin possono essere tabelle, form ecc. I plugin vengono richiamati con la Classe Get 
es:   echo Get::theme_plugin('table',  ['info' => $info, 'rows' => $rows, 'page_info' => $page_info]); // questo richiama plugins/table/table.php
si possono richiamare file diversi per cartella scrivendo
require echo Get::theme_plugin('folder/file'); 


I css e i js stanno dentro il modulo e vengono caricati dinamicamente dalla classe del tema (get_css e get_js).

Il php viene caricato con un normale require. Le variabili necessarie sono descritte nei commenti del file php.


Caratteristiche che devono essere soddisfatte:

- I plugin devono essere indipendenti tra loro
- Deve essere possibile caricare più volte lo stesso plugin nella pagina.
- Le pagine devono avere dichiarate le variabilii necessarie per il funzionamento del plugin.
- Se una variabile non è dichiarata correttamente deve essere gestita con un alert o un default.



