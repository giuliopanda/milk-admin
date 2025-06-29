(function () {
    'use strict'
    //Validazione di default dei form se il form ha la classe js-needs-validation
    document.addEventListener('DOMContentLoaded', function() {
        var forms = document.querySelectorAll('.js-needs-validation')
        // Loop over them and prevent submission
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault() // Prevent default first
                if (!form.checkValidity()) {
                    event.stopPropagation()
                } else {
                    form.submit() // Submit form if valid
                }
                form.classList.add('was-validated')
            }, false)
        })
        removeIsInvalid()
    })
})()


/**
 * Funzione per rimuovere la classe is-invalid quando un campo viene selezionato
 */
function removeIsInvalid() {
    var fields = document.querySelectorAll('.js-focus-remove-is-invalid')
    Array.prototype.slice.call(fields).forEach(function (field) {
        
        field.addEventListener('focus', function(event) {
            remove_is_invalid(event.currentTarget)
        })
        field.addEventListener('change', function(event) {
            remove_is_invalid(event.currentTarget)
        })
       
        field.classList.remove('js-focus-remove-is-invalid')
    }, false)

    // checkboxes
    var checkboxes = document.querySelectorAll('.js-checkbox-remove-is-invalid')
    Array.prototype.slice.call(checkboxes).forEach(function (boxCheckboxes) {
        // se i checkbox all'interno cambiano di stato rimuovo la classe is-invalid
        boxCheckboxes.querySelectorAll('input[type="checkbox"]');
        boxCheckboxes.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
            checkbox.addEventListener('change', function(event) {
                // salgo fino a trovare il div che li contiene con la classe js-checkbox-remove-is-invalid
                let div = event.currentTarget.closest('.js-checkbox-remove-is-invalid')
                div.classList.remove('is-invalid')
            })
        })
    })
    // radios
    var radios = document.querySelectorAll('.js-radio-remove-is-invalid')
    Array.prototype.slice.call(radios).forEach(function (boxRadios) {
        // se i radio all'interno cambiano di stato rimuovo la classe is-invalid
        boxRadios.querySelectorAll('input[type="radio"]');
        boxRadios.querySelectorAll('input[type="radio"]').forEach(function (radio) {
            radio.addEventListener('change', function(event) {
                let div = event.currentTarget.closest('.js-radio-remove-is-invalid')
                div.classList.remove('is-invalid')
            })
        })
    })
}

/**
 * Funzione interna chia
 * @param {} currentTarget 
 */
function remove_is_invalid(currentTarget) {
    currentTarget.classList.remove('is-invalid', 'is-valid')
    //currentTarget.setCustomValidity("");
    // cerco il campo con data-field="nome_campo" 
    let field_name = currentTarget.getAttribute('name')
    let div_msg_error = document.querySelector('[data-field="' + field_name + '"]')
    if (div_msg_error) {
        let container = div_msg_error.closest('.js-alert-container')
        elRemove(div_msg_error)      
        // verifico quanti figli ha il container se 0 lo elimino
        if (container) {
            setTimeout(() => {
                if (container.children.length == 0) {
                    elRemove(container)
                } 
            }, 300);
        }
    }
    

}


/**
 *  Gestore degli hook javascript
 */ 
const hooks = {};

/**
 *  Funzione per registrare un hook simile alla speculare funzione di php
 */
function registerHook(name, callback) {
  if (!hooks[name]) {
    hooks[name] = [];
  }
  hooks[name].push(callback);
}

/**
 * Funzione per richiamare un hook
 * Ritorna l'ultimo valore restituito da un hook
 * a differenza degli hook di php che ritorna il primo valore
 */
function callHook(name, ...args) {
    let lastValue = null;
    // find last value in args
    let argoments = args;
    if (args.length > 0) {
        lastValue = argoments.pop()
    }

    if (hooks[name]) {
        hooks[name].forEach(callback => {
            if (lastValue === null) {
            lastValue = callback(...argoments);
            } else {
                new_args = [...argoments, lastValue];
                lastValue = callback(...new_args);
            }
        });
    }
    return lastValue;
    
}
/**
 * Quando creo una classe e l'attacco ad un elemento html creo sull'elemento un attributo __itoComponent
 * Vengono escluse le classi di Bootstrap?
 */
function getComponent(id) {
    // faccio il sanitize dell'id accettando solo a-z, A-Z, 0-9, - e _
    id = id.replace('#', '');
    if (document.getElementById(id) == null) {
        return null;
    }
    return document.getElementById(id).__itoComponent;
}
/**
 * Trova un componente per nome
 * @param {} id 
 * @returns 
 */
function getComponentname(id) {
    let component = getComponent(id);
    if (component == null) {
        return null;
    }
    return component.component_name;
}


/**
 * Utility for creating DOM elements eI() and eIs()
 * @author Giulio Pandolfelli
 * @version 1.1.0
 */

/**
* 
* @param {} string a CSS selector or HTML tag or DOM element
* @param {*} options object with options
* @returns DOM element
*/
function eI(string, options = {}) {
    // if string is a group of DOM elements like NodeList
    if (string instanceof NodeList) {
        // return the first element
        elm = string[0];
    } else if (string instanceof HTMLElement) {
        // if string is already a DOM element
        elm = string;
    } else {
        $tags = ['div','span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'button', 'input', 'textarea', 'select', 'option', 'form', 'label', 'img', 'ul', 'li', 'ol', 'nav', 'header', 'footer', 'section', 'article', 'aside', 'main', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'caption', 'video', 'audio', 'canvas', 'iframe', 'svg', 'path', 'circle', 'rect', 'polygon', 'polyline', 'ellipse', 'line', 'g', 'defs', 'symbol', 'use', 'text', 'tspan', 'textPath', 'clipPath', 'mask', 'pattern', 'filter', 'foreignObject', 'linearGradient', 'radialGradient', 'stop', 'animate', 'animateMotion', 'animateTransform', 'circle', 'clipPath', 'defs', 'desc', 'ellipse', 'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite', 'feConvolveMatrix', 'feDiffuseLighting', 'feDisplacementMap', 'feDistantLight', 'feDropShadow', 'feFlood', 'feFuncA', 'feFuncB', 'feFuncG', 'feFuncR', 'feGaussianBlur', 'feImage', 'feMerge', 'feMergeNode', 'feMorphology', 'feOffset', 'fePointLight', 'feSpecularLighting', 'feSpotLight', 'feTile', 'feTurbulence', 'filter', 'foreignObject', 'g', 'image', 'line', 'linearGradient', 'marker', 'mask', 'metadata', 'mpath', 'path', 'pattern', 'polygon', 'polyline', 'radialGradient', 'rect', 'stop', 'svg', 'switch', 'symbol', 'text', 'textPath', 'title', 'tspan', 'use', 'view'];
        if ($tags.includes(string.toLowerCase())) {
            elm = document.createElement(string);
        } else if (string[0] !== '<') {
            elm = document.querySelector(string);
        } else {
            elm = _eIcreateEl(string, options);
        }
    }
    // if options is a string
    if (typeof options === 'string') {
        options = { class: options };
    }
    _eIOptions(elm, options);
    
    elm = _eIAddCustomFn(elm);
    return elm;
}

/**
 * Adds custom functions to elements
 */
function _eIAddCustomFn(elm) {
    if (!elm) return;
    elm.eI = function (selector, options = {}) {
        // if it is a CSS selector so it starts with a dot or a hash
        if (selector[0] === '.' || selector[0] === '#') {
            return elm.querySelector(selector);
        } else {
            delete options.to;
            delete options.after;
            delete options.before;
            delete options.replace;
            el = eI(selector, options);
            elm.appendChild(el);
            return el;
        }
        
    }

    elm.eIs = function (selector, fn) {
        if (typeof fn === 'function') {
            elm.querySelectorAll(selector).forEach((el, i) => {
                fn(el, i);
            });
        } else {
            return elm.querySelectorAll(selector);
        }  
    }
    return elm;
   
}

/**
* Executes a function for each selected element
* document.querySelectorAll(selector).forEach((el, i) => {});
* els('selector', (el, i) => {});
*/
function eIs(selector, fn) {
    if (selector instanceof NodeList) {
        if (typeof fn === 'function') {
            selector.forEach((el, i) => {
                fn(el, i);
            });
        } 
        return selector;
    }
    // if string is already a DOM element
    if (selector instanceof HTMLElement) {
        return selector;
    }
    const selectors = document.querySelectorAll(selector);
    if (typeof fn === 'function') {
        selectors.forEach((el, i) => {
            fn(el, i);
        });
    } 
    return selectors;
    

}

/**
* Creates a DOM element from an HTML string
* @param string htmlString 
* @returns DOM Element
* @example
* const newElement = _eIcreateEl('<button>Click me</button>');
*/
function _eIcreateEl(htmlString) {
    // Create the element
    const div = document.createElement('div');
    div.innerHTML = htmlString.trim();
    return div.firstChild;
}

/**
 * Applies CSS styles to an element
 * @param {} element 
 * @param {*} styles Accepts both a CSS style string or a style object
 */
function _eIStyle(element, styles) {
    if (!element) return;
    // If styles is a string, convert it to an object
    if (typeof styles === 'string') {
        // Split the string into individual style declarations
        const styleArray = styles.split(';').filter(style => style.trim());
        
        // Convert to object
        styles = styleArray.reduce((acc, style) => {
            const [property, value] = style.split(':').map(part => part.trim());
            if (property && value) {
                // Convert the property from kebab-case to camelCase if necessary
                const camelProperty = property.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
                acc[camelProperty] = value;
            }
            return acc;
        }, {});
    }

    for (let property in styles) {
        if (styles.hasOwnProperty(property)) {
            const value = styles[property];
            if (value === null) {
                // Remove the property if the value is null
                element.style.removeProperty(property);
            } else {
                const value = styles[property];
                if (value === null) {
                    // Remove the property if the value is null
                    element.style.removeProperty(property);
                } else {
                    // Apply the property with the provided value
                    element.style[property] = value;
                }
            }
        }
    }
}
/**
 * options
 * Adding options to the element
 *   
 *    to: container, // Optional: element to append to
 *    before: element, // Optional: element to insert before
 *    after: element, // Optional: element to insert after
 *    click: () => alert('Button clicked!'),
 *    mouseover: () => console.log('Mouse over button')
 *    ...
 *    style: { 
 *      color: 'red',
 *      backgroundColor: 'black'
 *    },
 *    class: "btn btn-primary"
 **/
function _eIOptions(element, options = {}) {
    if (!element) return;
    /**
     * Converts a string (CSS selector) to a DOM element.
     * If the option is already a DOM element, returns it directly.
     * @param {string|HTMLElement} target - A string (CSS selector) or DOM element.
     * @returns {HTMLElement|null} - The corresponding DOM element or null if not found.
     */
    function getElement(target) {
        if (typeof target === 'string') {
            const elm = document.querySelector(target);
            if (!elm) {
                console.warn(`El not found: ${target}`);
                return null;
            }
            return elm;
        }
        return target;
    }

    // If an element to append to is specified
    if (options.to) {
        const parent = getElement(options.to);
        if (parent) {
            parent.appendChild(element);
        }
    }

    // If an element to insert before is specified
    if (options.before) {
        const reference = getElement(options.before);
        if (reference && reference.parentNode) {
            reference.parentNode.insertBefore(element, reference);
        }
    }

    // If an element to insert after is specified
    if (options.after) {
        const reference = getElement(options.after);
        if (reference && reference.parentNode) {
            reference.parentNode.insertBefore(element, reference.nextSibling);
        }
    }

    // If an element to replace is specified
    if (options.replace) {
        const target = getElement(options.replace);
        if (target) {
            target.innerHTML = '';
            target.appendChild(element);
        }
    }

    // If an element to completely replace is specified
    if (options.replaceChild) {
        var target = getElement(options.replaceChild);
        if (target && target.parentNode) {
            target.parentNode.replaceChild(element, target);
        }
    }

    // If an element to remove is specified
    if (options.remove) {
        if (element && element.parentNode) {
            element.parentNode.removeChild(element);
        }
    }


    // Handling direct events
    const eventTypes = ['click', 'mouseover', 'mouseout', 'change', 'input', 'keydown', 'keyup', 'focus', 'blur'];
    for (const eventType of eventTypes) {
        if (options[eventType]) {
            element.addEventListener(eventType, options[eventType]);
        }
    }

    // Apply CSS styles
    if (options.style) {
        _eIStyle(element, options.style);
    }

    // Add a class
    if (options.class) {
        if (Array.isArray(options.class)) {
            element.classList.add(...options.class);
            // if it is a string composed of multiple classes with space between each class
        } else if(options.class.includes(' ')){
            const classes = options.class.split(' ').filter(className => className.trim() !== '');
            element.classList.add(...classes);
        } else {
            element.classList.add(options.class);
        }
    }

    // Remove a class
    if ('removeClass' in options) {
        element.classList.remove(options.removeClass);
    }

    // Replace a class with another
    if ('replaceClass' in options) {
        element.classList.replace(options.replaceClass[0], options.replaceClass[1]);
    }

    // Set the element's ID
    if ('id' in options) {
        element.id = options.id;
    }

    // Set the element's text
    if ('text' in options) {
        element.textContent = options.text;
    }

    // Set the element's inner HTML
    if ('html' in options) {
        element.innerHTML = options.html;
    }

    const attributes = ['name', 'value', 'type', 'placeholder', 'href', 'src', 'alt', 'title', 'target', 'download', 'for', 'id', 'disabled', 'selected', 'checked', 'autocomplete', 'autofocus', 'min', 'max', 'step', 'rows', 'cols', 'maxlength', 'minlength', 'pattern', 'required', 'multiple', 'accept', 'accept-charset', 'action', 'enctype', 'method', 'novalidate', 'target', 'rel'];
    for (const attr of attributes) {
        if (attr in options) {
            element.setAttribute(attr, options[attr]);
        }
    }
    // data-* or aria-* attributes
    for (const key in options) {
        if (key.startsWith('data-') || key.startsWith('aria-') || key.startsWith('v-')) {
            element.setAttribute(key, options[key]);
        }
    }
}

// FINE EI!

// Funzione per rimuovere i tag HTML da una stringa
function stripHtml(htmlString) {
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = htmlString;
    return tempDiv.textContent || tempDiv.innerText || '';
}


function manageRequiredFields(el, isVisible) {
    // Trova tutti gli elementi form con required o data-was-required
    const formElements = el.querySelectorAll('input, select, textarea');
    
    formElements.forEach(field => {
        if (isVisible) {
            // Se l'elemento diventa visibile, ripristina i required salvati
            if (field.hasAttribute('data-was-required')) {
                field.setAttribute('required', '');
                field.removeAttribute('data-was-required');
            }
        } else {
            // Se l'elemento viene nascosto, salva e rimuovi i required
            if (field.hasAttribute('required')) {
                field.setAttribute('data-was-required', 'true');
                field.removeAttribute('required');
            }
        }
    });
}

/**
 * Ogni volta che viene chiamata questa funzione verifica se l'elemento deve essere mostrato oppure no.
 * @param {*} el l'elemento da mostrare/nascondere
 * @param {*} el_form opzionale l'elemento del form che deve essere verificato
 * @param {*} value opzionale il valore da confrontare
 * Mostra o nasconde un elemento. Fa un trigger sull'elemento mostrato o nascosto
 */
function toggleEl(el, el_form, compare_value) {
    el = eI(el);
    
    if (el_form == undefined) {   
        if (el.style.display === 'none') {
            el.style.display = 'block';
            // Gestisci i required quando mostri l'elemento
            manageRequiredFields(el, true);
        } else {
            el.style.display = 'none';
            // Gestisci i required quando nascondi l'elemento
            manageRequiredFields(el, false);
        }
    } else {
        let value = null;
        // se el_form è un checkbox
        if (el_form.tagName == 'INPUT' && el_form.type == 'checkbox') {
              if (el_form.checked) {
                value = el_form.value;
              }
        } else {
            value = el_form.value;
        }
        
        if (value === compare_value) {
            el.style.display = 'block';
            // Gestisci i required quando mostri l'elemento
            manageRequiredFields(el, true);
            // trigger event el show
            let event = new Event('show');
            el.dispatchEvent(event);
        } else {
            el.style.display = 'none';
            // Gestisci i required quando nascondi l'elemento
            manageRequiredFields(el, false);
            // trigger event el hide
            let event = new Event('hide');
            el.dispatchEvent(event);
        }
    }
}
/**
 * Nasconde un elemento
 * @param DOM el 
 * @param Function fn Quando l'elemento è nascosto viene chiamata questa funzione
 */
function elHide(el, fn) {
    el = eI(el);
    // con animazione fade
    // aggiungo una classe fade
    el.classList.add('fade');
    el.classList.remove('show');
    // dopo 1 secondo nascondo l'elemento, rimuovo la classe fade e chiamo la funzione
    setTimeout(() => {
        el.classList.add('d-none');
        el.classList.remove('fade');
        if (fn !== undefined) {
            fn.call(el);
        }
    }, 200);
}

/**
 * Nascondo e poi rimuovo l'elemento
 * @param {} el 
 */
function elRemove(el) {
    el = eI(el);
    // con animazione fade
    // aggiungo una classe fade
    el.classList.add('fade');
    el.classList.remove('show');
    // dopo 1 secondo nascondo l'elemento, rimuovo la classe fade e chiamo la funzione
    setTimeout(() => {
        el.remove();
    }, 200);
}

/**
 * Mostra un elemento
 * @param DOM el 
 * @param Function fn  Quando l'elemento è mostrato viene chiamata questa funzione
 */
function elShow(el, fn) {
    el = eI(el);
    el.classList.add('fade');
    el.classList.remove('d-none');
    setTimeout(() => { el.classList.add('show');}, 10);
    setTimeout(() => {
        if (fn !== undefined) {
            fn.call(el);
        }
    }, 200);
}


/**
 * Se un elemento ha l'attributo data-togglefield allora viene creato un toggleEl per il campo 
 * con il nome indicato in data-togglefield ad esempio:
 * <input type="text" name="field1" >
 * <div data-togglefield="field1" data-togglevalue="1">Show in value = 1</div>
 * In questo modo si mostrano o nasconono gli elementi senza chiamare il javascript
 */
class toggleEls 
{
    el = null
    constructor(el) {
        el = eI(el);
        this.el = el
        // prendo l'attributo data-toggleField
        let el_toggle_field = el.dataset.togglefield
        if (el_toggle_field == null) {
            return
        }
        let el_toggle = document.querySelector('[name="' + el_toggle_field + '"]')
        if (el_toggle_field == null) {
            return
        }
        this.el_toggle = el_toggle
        this.value = el.dataset.togglevalue
        this.el_toggle.addEventListener('input', () => {
            toggleEl(el, el_toggle, this.value)
        });
        toggleEl(el, el_toggle, this.value)
    }
}


/**
 * funzione di servizio per trasformare una query string in un oggetto FormData
 * @param string queryString 
 * @returns 
 */
function getFormData(queryString) {
    const params = new URLSearchParams(queryString.startsWith('?') ? queryString.substring(1) : queryString);
    const formData = new FormData();
    // Itera su ogni coppia chiave-valore e la aggiunge a formData
    params.forEach((value, key) => {
        formData.append(key, value);
    });
    return formData;
}


/**
 * Classe per la gestione dell'offcanvas che appare dalla destra
 */
class Offcanvas_end
{
    offcanvasEl = null;
    is_show = false;
    constructor() {
        this.el_container = document.getElementById('offCanvasEnd')
        if (this.el_container == null) {
            return;
        }
        let preload = this.el_container.querySelector('.js-ito-loading')
        this.preload = new Loading(preload)
        this.offcanvasEl = new bootstrap.Offcanvas( 
            document.getElementById('offCanvasEnd'), 
            { keyboard: false, backdrop: 'static', scroll: true }
        );
    }

    /**
     * @param {string} size 'xl' | empty
     */
    size(size) {
        this.el_container.classList.remove('offcanvas-size-ito')
        this.el_container.classList.remove('offcanvas-size-ito-xl')
        this.el_container.classList.remove('offcanvas-size-ito-l')
        if (size == 'xl') {
            this.el_container.classList.add('offcanvas-size-ito-xl')
        } else  if (size == 'l') {
            this.el_container.classList.add('offcanvas-size-ito-l')
        } else {
            this.el_container.classList.add('offcanvas-size-ito')
        }
    }

    show() {
        if (!this.is_show) {
            document.getElementById('offCanvasBody').innerHTML = ''
            document.getElementById('offCanvasTitle').innerHTML = ''
        }
        this.offcanvasEl.show()
        this.is_show = true;
    }

    hide() {
        this.offcanvasEl.hide()
        setTimeout(() => {
            document.getElementById('offCanvasBody').innerHTML = ''
            document.getElementById('offCanvasTitle').innerHTML = ''
        }, 100);
        this.is_show = false;
    }

    loading_show() {
        this.preload.show()
    }
    loading_hide() {
        this.preload.hide()
    }

    title(html) {
        document.getElementById('offCanvasTitle').innerHTML = html
    }

    body(html) {
        document.getElementById('offCanvasBody').innerHTML = html
    }
}


class Toasts {
    toastEl = null;
    toastBody = null;
    toastType = null;
    constructor()  {
        if (document.getElementById('toastUp') != null) {
            this.toastEl = new bootstrap.Toast(document.getElementById('toastUp'));
        }
    }

    show(html, type) {
        if (typeof html !== 'undefined' && html.trim() !== '') {
            if (typeof type == 'undefined' || type.trim() == '') {
                type = this.toastType ?? 'primary';
            }
            this.body(html, type);
        }
        this.toastEl.show();
    }

    hide() {
        this.toastEl.hide();
        document.getElementById('toastBodyTxt').innerHTML = ''
    }
    /**
     * 
     * @param {html} html 
     * @param {string} type success | danger | warning 
     */
    body(html, type) {
        this.toastType = type;
        this.toastBody = html;
        document.getElementById('toastBody').classList.remove('text-bg-success');
        document.getElementById('toastBody').classList.remove('text-bg-danger');
        document.getElementById('toastBody').classList.remove('text-bg-warning');
        document.getElementById('toastBody').classList.remove('text-bg-primary');
        switch (type) {
            case 'success':
                document.getElementById('toastBody').classList.add('text-bg-success')
                break;
            case 'danger':
            case 'error':
                document.getElementById('toastBody').classList.add('text-bg-danger')
                break;
            case 'warning':
                document.getElementById('toastBody').classList.add('text-bg-warning')
                break;
            case 'primary':
                document.getElementById('toastBody').classList.add('text-bg-primary')
                break;
        }
       
        document.getElementById('toastBodyTxt').innerHTML = html
     
    }
}

class Modal {
    modal_el = null
    dom_el = null
    el_body = null
    el_title = null
    el_footer = null
    constructor()  {
        this.dom_el = document.getElementById('itoModal')
        if (this.dom_el != null) {
            this.modal_el = new bootstrap.Modal(this.dom_el, {backdrop: 'static'} )
            this.el_body = this.dom_el.querySelector('.js-modal-body')
            this.el_title = this.dom_el.querySelector('.js-modal-title')
            this.el_footer = this.dom_el.querySelector('.js-modal-footer')
        }
    }

    show(title, body, footer) {
        this.title(title)
        this.body(body)
        this.footer(footer)
        this.modal_el.show()
    }

    hide() {
        this.header('')
        this.body('')
        this.footer('')
        this.modal_el.hide()
    }

    body(html) {
        this.el_body.innerHTML = html
    }

    title(html) {
        this.el_title.innerHTML = html
    }

    footer(html) { 
   
        this.el_footer.innerHTML = html
    }
}


/**
 * Funzione interna
 */
function mobile_menu() {
    const btn = document.getElementById('sidebar-toggler');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebar = document.getElementById('sidebar');
    
    // Create close button if it doesn't exist
    let closeBtn = document.getElementById('sidebarCloseBtn');
    if (!closeBtn) {
        closeBtn = document.createElement('button');
        closeBtn.id = 'sidebarCloseBtn';
        closeBtn.className = 'sidebar-close-btn';
        closeBtn.innerHTML = '✕';
        document.body.appendChild(closeBtn);
    }

    function showSidebar() {
        sidebar.classList.add('show-sidebar');
        overlay.classList.add('sidebar-overlay-show');
        closeBtn.classList.add('show');
        document.body.classList.add('no-scroll');
    }

    function hideSidebar() {
        sidebar.classList.remove('show-sidebar');
        overlay.classList.remove('sidebar-overlay-show');
        closeBtn.classList.remove('show');
        document.body.classList.remove('no-scroll');
    }

    if (btn && overlay) {
        btn.addEventListener('click', function() {
            if (sidebar.classList.contains('show-sidebar')) {
                hideSidebar();
            } else {
                showSidebar();
            }
        });

        overlay.addEventListener('click', hideSidebar);
        closeBtn.addEventListener('click', hideSidebar);
    }
}


// rendo le funzioni a livello globale
// tutte le classi sono state caricate
document.addEventListener('DOMContentLoaded', function() {
    window.registerHook = registerHook;
    window.callHook = callHook;
    window.offcanvasEnd = new Offcanvas_end();
    window.toasts = new Toasts();
    window.modal = new Modal();
    // avvio il menu laterale mobile
    mobile_menu();
    // avvio i toggleEls prendo tutti i campi che hanno data-toggle-field
    document.querySelectorAll('[data-togglefield]').forEach(el => {  new toggleEls(el); });
    // inizializzo i popup di bootstrap
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]')
    const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl,  { trigger: 'focus' }));
   

});



/**
 * Crea un campo ripetibile con un pulsante per aggiungere e rimuovere campi
 * Al momento non usato mi pare!
 */
class RepeatableField {
    constructor(fieldContainer, options = {}) {
        this.container = fieldContainer;
        this.options = {
            addButtonText: options.addButtonText || 'Aggiungi campo',
            removeButtonText: options.removeButtonText || '',
            maxFields: options.maxFields || 10,
            addButtonClass: options.addButtonClass || 'btn btn-primary my-2',
            removeButtonClass: options.removeButtonClass || 'btn btn-lg btn-danger js-btn-remove',
            onAdd: options.onAdd || null, // Callback quando viene aggiunto un campo
            onRemove: options.onRemove || null // Callback quando viene rimosso un campo
        };

        // Inizializza con il conteggio dei figli diretti (escluso il bottone add che aggiungeremo)
        this.fieldCount = this.container.children.length;
        this.init();
    }

    init() {
        // Inizializza i campi esistenti (tranne il primo)
        this.initializeExistingFields();
        // Crea il bottone add
        this.createAddButton();
    }

    createEl(html) {
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        return template.content.firstElementChild;
    }

    initializeExistingFields() {
        // Converti HTMLCollection in Array per iterare
        const children = Array.from(this.container.children);
        
        // Salta il primo (template) e processa gli altri
        children.forEach((child, index) => {
            if (index > 0) { // Salta il primo campo
                this.wrapFieldWithLayout(child);
            }
        });
    }

    wrapFieldWithLayout(fieldElement) {
        // Crea il container flex
        const container = this.createEl('<div class="d-flex js-repeat-row"></div>');
        const div1 = this.createEl('<div class="pt-2 flex-grow-1"></div>');
        
        // Sposta l'elemento esistente nel nuovo layout
        const parent = fieldElement.parentNode;
        parent.removeChild(fieldElement);
        div1.appendChild(fieldElement);
        container.appendChild(div1);
        
        // Aggiungi il bottone remove
        const div2 = this.createEl('<div class="pt-2 ps-2"></div>');
        div2.appendChild(this.createRemoveButton());
        container.appendChild(div2);
        
        // Inserisci il nuovo container
        parent.appendChild(container);
    }

    createAddButton() {
        const addButton = document.createElement('button');
        addButton.type = 'button';
        addButton.className = this.options.addButtonClass;
        addButton.textContent = this.options.addButtonText;
        
        addButton.addEventListener('click', () => this.addField());
        
        this.container.appendChild(addButton);
    }

    createRemoveButton() {
        const removeButton = this.createEl(
            `<button class="${this.options.removeButtonClass}">
                <i class="bi bi-trash3-fill"></i>
            </button>`
        );
        
        removeButton.addEventListener('click', (e) => this.removeField(e));
        
        return removeButton;
    }

    addField() {
        if (this.fieldCount >= this.options.maxFields) {
            alert(`Non puoi aggiungere più di ${this.options.maxFields} campi`);
            return;
        }

        // Clona il primo elemento figlio (template)
        const templateField = this.container.children[0];
        const newField = templateField.cloneNode(true);
        
        // Pulisci i valori dei campi clonati
        newField.querySelectorAll('input, select, textarea').forEach(input => {
            input.value = '';
            // Aggiorna gli ID per evitare duplicati
         
            const originalId = input.getAttribute('id');
            if (originalId) {
                const baseId = originalId.split('_')[0];
                input.setAttribute('id', `${baseId}_${this.fieldCount}`);
            }
        });

        // Avvolgi il nuovo campo nel layout con il bottone remove
        const container = this.createEl('<div class="d-flex js-repeat-row"></div>');
        const div1 = this.createEl('<div class="pt-2 flex-grow-1"></div>');
        div1.appendChild(newField);
        container.appendChild(div1);
        
        const div2 = this.createEl('<div class="pt-2 ps-2"></div>');
        div2.appendChild(this.createRemoveButton());
        container.appendChild(div2);
        
        // Inserisci il nuovo campo prima del bottone aggiungi
        this.container.insertBefore(container, this.container.lastElementChild);
        
        this.fieldCount++;

        // Callback onAdd se definito
        if (this.options.onAdd) {
            this.options.onAdd(newField);
        }
    }

    removeField(event) {
        const wrapper = event.target.closest('.js-repeat-row');
        
        // Callback onRemove se definito
        if (this.options.onRemove) {
            this.options.onRemove(wrapper);
        }

        wrapper.remove();
        this.fieldCount--;
    }
}

/**
 * Classe per ordinare e paginare una tabella
 */
class ItoTableSorterPaginator {
    /**
     * @param {el} elementTable - L'ID della tabella su cui abilitare ordinamento e paginazione.
     * @param {number} rowsPerPage - Numero di righe mostrate per pagina.
     * @param {el} elementPaginationUl - L'ID del container (ul) in cui verranno inseriti i link di paginazione.
     */
    constructor(elementTable, rowsPerPage = 5, elementPaginationUl = null) {
        this.table = elementTable;
        if (!this.table) {
            throw new Error(`Table "${tableId}" not found`);
        }

        this.tbody = this.table.querySelector('tbody');
        this.headers = Array.from(this.table.querySelectorAll('thead th'));
        this.rows = Array.from(this.tbody.querySelectorAll('tr'));
        this.paginationEl = elementPaginationUl;
        this.rowsPerPage = rowsPerPage;
        this.currentPage = 1;
        this.sortDirections = {};

        this.initSorters();
        this.renderTable();
    }
  
    /**
     * Aggiunge gli event listener su ogni th per il click di ordinamento.
     */
    initSorters() {
      this.headers.forEach((th, index) => {
        th.style.cursor = 'pointer';
        th.addEventListener('click', () => {
          this.sortColumn(index);
        });
      });
    }
  
    /**
     * Effettua l'ordinamento della colonna specificata.
     * @param {number} colIndex - Indice della colonna su cui ordinare.
     */
    sortColumn(colIndex) {
        let currentDirection = this.sortDirections[colIndex] || 'asc';
      
        this.rows.sort((rowA, rowB) => {
          const cellsA = rowA.querySelectorAll('td');
          const cellsB = rowB.querySelectorAll('td');
          // Se una delle righe non ha abbastanza celle, gestisci l'eccezione o forza un ordinamento “inferiore”
          if (!cellsA[colIndex] || !cellsB[colIndex]) {
            // Gestione semplificata:
            return 0; // le lascio in ordine invariato, oppure gestisci come preferisci
          }
      
          const cellA = cellsA[colIndex].innerText.trim();
          const cellB = cellsB[colIndex].innerText.trim();
      
          const valA = parseFloat(cellA.replace(',', '.'));
          const valB = parseFloat(cellB.replace(',', '.'));
          
          if (!isNaN(valA) && !isNaN(valB)) {
            return valA - valB;
          } else {
            return cellA.localeCompare(cellB);
          }
        });
      
        if (currentDirection === 'desc') {
          this.rows.reverse();
        }
      
        this.sortDirections[colIndex] = (currentDirection === 'asc') ? 'desc' : 'asc';
        this.updateHeaderIcon(colIndex);
        this.renderTable();
    }
      
    cleanHeaderIcons() {
        this.headers.forEach(th => {
            th.innerHTML = th.dataset.sort || th.innerText.trim();
        });
    }
  
    /**
     * Aggiorna l'HTML del th per mostrare l'icona corrispondente
     * all'ordinamento corrente (asc o desc).
     * @param {number} colIndex 
     */
    updateHeaderIcon(colIndex) {
      this.cleanHeaderIcons();
      const th = this.headers[colIndex];
      const sortDirection = this.sortDirections[colIndex];
      
      // Recupero il testo dal data-sort se esiste, altrimenti dal testo di th
      const columnName = th.dataset.sort || th.innerText.trim();
  
      // Icona da usare (asc/desc)
      const iconClass = (sortDirection === 'asc') ? 'bi bi-sort-up-alt' : 'bi bi-sort-down-alt';
  
      // Esempio di sostituzione dell'intero contenuto del th
      // con la struttura richiesta
      th.innerHTML = `
        <div class="d-flex table-order-selected">
          <span class="me-2">${columnName}</span>
          <i class="${iconClass}"></i>
        </div>
      `;
    }
  
    /**
     * Mostra le righe della pagina corrente e aggiorna la paginazione (se richiesta).
     */
    renderTable() {
      // Calcolo dell'indice iniziale e finale in base alla pagina corrente
      const startIndex = (this.currentPage - 1) * this.rowsPerPage;
      const endIndex = startIndex + this.rowsPerPage;
  
      // Estrazione delle righe da visualizzare
      const visibleRows = this.rows.slice(startIndex, endIndex);
  
      // Svuoto il tbody
      this.tbody.innerHTML = '';
  
      // Aggiungo solo le righe necessarie
      visibleRows.forEach(row => this.tbody.appendChild(row));
  
      // Se è stato fornito l'id per la paginazione, aggiorno i link
      if (this.paginationEl) {
        this.renderPagination();
      }
    }
  
    /**
     * Genera e gestisce i link di paginazione (se paginationEl è definito).
     */
    renderPagination() {
        const paginationContainer = this.paginationEl;
        if (!paginationContainer) return;

        // Nascondi la paginazione se non necessaria
        if (this.rowsPerPage <= 0 || this.rows.length <= this.rowsPerPage) {
            paginationContainer.classList.add('d-none');
            return;
        } else {
            paginationContainer.classList.remove('d-none');
        }

        // Numero totale di pagine
        const totalPages = Math.ceil(this.rows.length / this.rowsPerPage);

        let paginationHTML = '';

        // Pulsante "Precedente"
        paginationHTML += `
            <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${this.currentPage - 1}" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
        `;

        if (totalPages <= 10) {
            // Mostra tutte le pagine se sono meno di 10
            for (let page = 1; page <= totalPages; page++) {
                paginationHTML += `
                    <li class="page-item ${page === this.currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${page}">${page}</a>
                    </li>
                `;
            }
        } else {
            // Per molte pagine, mostra un select
            paginationHTML += `
                <li class="page-item">
                    <select class="form-select mx-2" style="width: auto;" aria-label="Seleziona pagina">
            `;
            
            for (let page = 1; page <= totalPages; page++) {
                paginationHTML += `
                    <option value="${page}" ${page === this.currentPage ? 'selected' : ''}>
                        ${page}
                    </option>
                `;
            }

            paginationHTML += `
                    </select>
                </li>
            `;
        }

        // Pulsante "Successivo"
        paginationHTML += `
            <li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${this.currentPage + 1}" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        `;

        paginationContainer.innerHTML = paginationHTML;

        // Gestione eventi per i link di paginazione
        const links = paginationContainer.querySelectorAll('.page-link');
        links.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(link.dataset.page);
                if (!isNaN(page) && page > 0 && page <= totalPages) {
                    this.currentPage = page;
                    this.renderTable();
                }
            });
        });

        // Gestione evento per il select
        const select = paginationContainer.querySelector('select');
        if (select) {
            select.addEventListener('change', (e) => {
                const page = parseInt(e.target.value);
                if (!isNaN(page)) {
                    this.currentPage = page;
                    this.renderTable();
                }
            });
        }
    }
}
  

class ItoSortableList {
    constructor(container, options = {}) {
      this.container = container;
      this.handleSelector = options.handleSelector || null;
      this.onUpdate = options.onUpdate;
      
      this.draggedItem = null;
      this.draggingClone = null;
      this.placeholder = null;
      this.offsetY = 0;
      this.isDragging = false;
      this.mouseDownPosition = null;
      this.onMouseMovebound = this.onGlobalMouseMove.bind(this);
      this.onMouseUpbound = this.onGlobalMouseUp.bind(this);
      
      this.init();
    }
  
    init() {
      [...this.container.children].forEach(item => {
        this.makeDraggable(item);
      });
    }
  
    makeDraggable(item) {
      const handle = this.handleSelector ? 
        item.querySelector(this.handleSelector) : 
        item;
  
      if (!handle) return;
  
      // Rimuovi draggable attribute e dragstart
      item.removeAttribute('draggable');
  
      // Sostituisci gli eventi drag con eventi mouse
      handle.addEventListener('mousedown', e => {
        e.preventDefault();
        e.stopPropagation();
        
        this.isDragging = true;
        this.draggedItem = item;
        
        // Calcola l'offset e crea il clone
        const rect = item.getBoundingClientRect();
        this.offsetY = e.clientY - rect.top;
        this.mouseDownPosition = {x: e.clientX, y: e.clientY};
        
        // Crea e inserisci il placeholder
        this.placeholder = document.createElement('div');
        this.placeholder.className = item.className + ' drag-placeholder';
        this.placeholder.style.height = rect.height + 'px';
        
        // Nascondiamo completamente l'item originale e inseriamo il placeholder
        item.remove();
        this.container.insertBefore(this.placeholder, null);
        // Crea il clone visivo
        this.draggingClone = item.cloneNode(true);
        this.draggingClone.classList.add('dragging-clone');
        this.draggingClone.style.width = rect.width + 'px';
        this.draggingClone.style.position = 'fixed';
        this.draggingClone.style.top = e.clientY - this.offsetY + 'px';
        // trovo la posizione assoluta dell'elemento che sto per trascinare
        //this.lastValidPosition = this.container.children.indexOf(item);
        this.draggingClone.style.left = (rect.left + 40) + 'px';
        document.body.appendChild(this.draggingClone);
        // Aggiungi i listener globali
        document.addEventListener('mousemove', this.onMouseMovebound);
        document.addEventListener('mouseup', this.onMouseUpbound);
        // 
        const afterElement = this.getDragAfterElement(e.clientY - 20);
        if (afterElement) {
          this.container.insertBefore(this.placeholder, afterElement);
        } else {
          this.container.appendChild(this.placeholder);
        }
        
      });
    }
  
    onGlobalMouseMove(e) {
      if (this.isDragging && this.draggingClone) {
        e.preventDefault();
        
        this.draggingClone.style.top = (e.clientY - this.offsetY) + 'px';
        // this.draggingClone.style.left = e.clientX - (this.draggingClone.offsetWidth / 2) + 'px';
  
        // Aggiorna la posizione del placeholder
        const afterElement = this.getDragAfterElement(e.clientY);
        if (afterElement) {
          this.container.insertBefore(this.placeholder, afterElement);
        } else {
          this.container.appendChild(this.placeholder);
        }
      }
    }
  
    onGlobalMouseUp(e) {
      if (this.isDragging) {
        e.preventDefault();
        
        // Rimuovi i listener globali
        document.removeEventListener('mousemove', this.onMouseMovebound);
        document.removeEventListener('mouseup', this.onMouseUpbound);
  
        if (this.draggedItem) {
          if (this.placeholder && this.placeholder.parentNode) {
            this.container.insertBefore(this.draggedItem, this.placeholder.nextSibling);
          } else {
            this.container.appendChild(this.draggedItem);
          }
  
          this.draggedItem.style.display = '';
        }
        
        this.cleanupDrag();
        if (this.onUpdate) {
            this.onUpdate(this.getCurrentOrder());
        }
      }
    }
  
    cleanupDrag() {
      if (this.placeholder && this.placeholder.parentNode) {
        this.placeholder.remove();
      }
      if (this.draggingClone && this.draggingClone.parentNode) {
        this.draggingClone.remove();
      }
      if (this.draggedItem) {
        this.draggedItem.style.opacity = '1';
      }
      this.draggedItem = null;
      this.draggingClone = null;
      this.placeholder = null;
      this.lastValidPosition = null;
      this.isDragging = false;
    }
  
    getDragAfterElement(y) {
      const draggableElements = [...this.container.children].filter(
        child => child !== this.draggedItem && child !== this.placeholder
      );
  
      let result = draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
          return { offset, element: child };
        } else {
          return closest;
        }
      }, { offset: Number.NEGATIVE_INFINITY });
  
      // Se non troviamo un elemento dopo cui inserire, restituiamo null
      // per indicare che deve essere inserito alla fine
      return result.element;
    }
  
    getCurrentOrder() {
      return [...this.container.children];
    }
    /**
   * Metodo per distruggere l'istanza e pulire tutti i riferimenti e listener
   */
  destroy() {
    // 1. Rimuovi i listener globali
    document.removeEventListener('mousemove', this.onMouseMovebound);
    document.removeEventListener('mouseup', this.onMouseUpbound);
    
    // 2. Pulisci eventuali elementi di drag in corso
    this.cleanupDrag();
    
    // 3. Rimuovi i listener di mousedown da tutti gli handle nel container
    if (this.container) {
      [...this.container.children].forEach(item => {
        const handle = this.handleSelector ? 
          item.querySelector(this.handleSelector) : 
          item;
          
        if (handle) {
          // La modalità più sicura per rimuovere tutti i listener è clonare e sostituire
          const newHandle = handle.cloneNode(true);
          if (handle.parentNode) {
            handle.parentNode.replaceChild(newHandle, handle);
          }
        }
      });
    }
    
    // 4. Pulisci i riferimenti interni
    this.container = null;
    this.draggedItem = null;
    this.draggingClone = null;
    this.placeholder = null;
    this.onUpdate = null;
    this.onMouseMovebound = null;
    this.onMouseUpbound = null;

    return true;
  }
}