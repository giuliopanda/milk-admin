I plugin sono blocchi js/css html/php per la gestione delle funzionalità del template

/tema/
  /plugins/
    /UploadFiles/
      init.php         ← caricato sempre
      plugin.php       ← caricato quando richiami load_plugin("UploadFiles")
      helpers.php      ← eventuali funzioni interne
      config.php       ← eventuale configurazione



Convenzioni:

Cartella plugin → PascalCase (UploadFiles, UserAuth, ImageGallery)

File principale eseguibile dal load_plugin() → plugin.php (sempre lo stesso nome, così non devi indovinare)

File di bootstrap sempre caricato → init.php

Altri file interni → minuscolo, con underscore se necessario (helpers.php, config.php, routes.php)