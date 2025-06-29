# Template per il progetto.

Il template non deve essere solo l'html, ma tutta la logica per la grafica.

## Struttura del progetto:

su assets_php/functions.php: vengono definite la costanti tra cui
- `THEME_DIR` (percorso della cartella del tema)
- `THEME_CLASS` (classe per la gestione del template)


**files:**
- assets_php/functions.php (file con tutte le funzioni)
- page-*.php (file del template oer ogni pagina)
- test-*.php (file di test per le elementi grafici)

**cartelle:**
- assets_php/ (file php con funzioni e classi)
- assets/ (file css e file js comuni a tutto il sito (vengono caricati in automatico appena inseriti))
- templates_parts/ (file con parti di template generiche come header, footer ecc...)
- bootstrap/ (file bootstrap e icone)
- plugins/ I plugin. Ogni modulo è una cartella con un file php, un css e un js. I moduli possono essere tabelle, form ecc. I moduli vengono caricati tramite la Classe Get
es:   echo Get::theme_plugin('table/pagination', [ 'page_info' => $page_info]);
- page_assets/ (js o altri file per una pagina specifica)


# Lavorazione dei css e js
In fase di progettazione sono tutti separati, poi però dovrò pensare a come unirli e minificarli (gulp?)



# NOTE:
trovo esempi utili sulla pagina:
https://getbootstrap.com/docs/5.3/examples/
le icone di bootstra le trovo qui:
https://icons.getbootstrap.com/

perfect-scrollbar:
https://perfectscrollbar.com/how-to-use.html

Un template da copiare:
https://zuramai.github.io/mazer/demo/