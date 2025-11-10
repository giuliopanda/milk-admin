'use strict'
/**
 * Crea una maschera sopra ad un elemento html 
 * per indicare che è in corso un'operazione di caricamento
 */
class Loading {
    el_container = null;
    component_name = 'Loading';
    // segno una variabile se è stato applicato già l'init oppure no
    is_init = false;
    is_show = false; 
    constructor(el){
        if (el == null) {
            console.error('Plugin Loading: Element not found');
            return;
        }
        if (el.__itoComponent != null) {
            return el.__itoComponent;
        }
        this.el_container = eI(el);
        this.el_container.__itoComponent = this;
    }

    show() {
        this.el_container.classList.add('ito-loading-active');
        window.setTimeout(() => {
            this.el_container.style.opacity = .8;
        }, 10);
        this.is_show = true;
    }

    hide() {
        this.el_container.style.opacity = 0;
        this.el_container.classList.remove('ito-loading-active');
        this.is_show = false;
    }


}