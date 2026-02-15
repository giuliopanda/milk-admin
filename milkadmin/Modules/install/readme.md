L'istallazione o l'aggiornamento del sistema è gestito attraverso una serie di hook così ogni modulo può gestire l'aggiornamento o l'istallazione della versione.

La versione è indicata come una data (AAMMGG dove A è l'anno, M il mese e G il giorno)
la nuova versione è gestita dalla costante NEW_VERSION.
NEW_VERSION è dentro install.controller.php

dentro config.php c'è invece la versione corrente

Il config.php di prima installazione deve avere come home page install

La versione non va per singoli moduli, ma per intera installazione!
