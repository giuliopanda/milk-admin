'use strict'
/**
 * Esempio di scrittura di un modulo.
 * V 1.0
 */
class Example {
    // container dell'elemento
    el_container = null;
    // messaggio di alert
    data_alert_msg = '';

    constructor(el) {
        this.el_container = el
        this.data_alert_msg = el.getAttribute('data-alert')
        this.init()
    }
    /**
     * Nell'init vanno messi tutti i listener che si vogliono attaccare all'elemento
     * @returns void
     */
    init() {
        // scrivendo la funzione come arrow function posso usare this della classe all'interno della funzione
        this.el_container.querySelector('.js-btn').addEventListener('click', (ev) => {
                // preferisco usare ev.currentTarget invece di ev.target perche' mi assicuro di prendere l'elemento giusto ovvero quello che ha il listener
                this.click(ev.currentTarget)
         })   
    }

    click(el) {
        // posso usare el.closest('.my-class') per trovare il primo elemento sopra di me che ha la classe my-class
        // posso usare el.querySelector('.my-class') per trovare il primo elemento sotto di me che ha la classe my-class
        alert(this.data_alert_msg);
    }
}

/**
 * Attacco il mio modulo a tutti gli elementi con la classe js_example
 */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.js_example_btn').forEach(function(el) {
        new Example(el);
    });
});