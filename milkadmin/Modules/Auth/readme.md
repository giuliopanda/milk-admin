# Gestione degli utenti e delle sessioni.

Se il modulo è disattivato non vengono gestiti utenti e sessioni.
I permessi sono centralizzati in permssions.class.php e ritornano di default sempre true.
Se il modulo auth è abilitato invece tornano sempre false per gli utenti guest o con i permessi settati per l'utente loggato.
