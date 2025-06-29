# TODO:
Il completed mette la data con il fuso orario! Sbagliato! (??? forse no)

La lista sotto sbaglia la paginazione. 

Verificare che run all non esegua quelle in running o blocked.
Aggiungere gli hook per cambiare le configurazioni (tipo numero di tentativi per bloccare).


# INFO

status:
pending -> in attesa
running -> in esecuzione Non si può avviare un nuovo job! Lo si può solo bloccare!
completed -> completato
failed -> fallito Viene impostato quando la funzione che esegue il job torna false o throw una eccezione.
blocked -> bloccato. Se ci sono 3 tentativi falliti precedenti. Se è bloccato non si esegue più a meno che non premi il bottone esegui ora dalla pagina jobs. In quel caso lo esegue e se fallisce lo riblocca genera una nuova esecuzione con stato pending.

output -> output della funzione che esegue il job gli echo e i print
error -> da fare. I log degli errori impostati Da LOGS! DA FARE!
metadata -> json con eventuali metadati impostati nella configurazione della funzione.

TODO Gestire il timeout