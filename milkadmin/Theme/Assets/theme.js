

/**
 * Remove the is-invalid class when a field is selected
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
        // If the checkboxes inside change state, remove the is-invalid class
        boxCheckboxes.querySelectorAll('input[type="checkbox"]');
        boxCheckboxes.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
            checkbox.addEventListener('change', function(event) {
                // go up to find the div that contains it with the class js-checkbox-remove-is-invalid
                let div = event.currentTarget.closest('.js-checkbox-remove-is-invalid')
                div.classList.remove('is-invalid')
            })
        })
    })
    // radios
    var radios = document.querySelectorAll('.js-radio-remove-is-invalid')
    Array.prototype.slice.call(radios).forEach(function (boxRadios) {
        // If the radio inside changes state, remove the is-invalid class
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
 * Internal function to remove the is-invalid class
 * @param {} currentTarget 
 */
function remove_is_invalid(currentTarget) {
    currentTarget.classList.remove('is-invalid', 'is-valid')
    //currentTarget.setCustomValidity("");
    // search for the field with data-field="nome_campo" 
    let field_name = currentTarget.getAttribute('name')
    let div_msg_error = document.querySelector('[data-field="' + field_name + '"]')
    if (div_msg_error) {
        let container = div_msg_error.closest('.js-alert-container')
        elRemove(div_msg_error)      
        // check how many children the container has, if 0 remove it
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
 * JavaScript hook manager
 */ 
const hooks = {};

/**
 * Function to register a hook similar to the PHP function
 */
function registerHook(name, callback) {
  if (!hooks[name]) {
    hooks[name] = [];
  }
  hooks[name].push(callback);
}

/**
 * Function to call a hook
 * Returns the last value returned by a hook
 * Unlike PHP hooks, it returns the last value
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
 * When I create a class and attach it to an HTML element, I create a __itoComponent attribute on the element.
 * Are Bootstrap classes excluded?
 */
function getComponent(id) {
    // sanitize id
    id = id.replace('#', '');
    if (document.getElementById(id) == null) {
        console.warn ("GET COMPONENT: Component not found: " + id);
        return null;
    }
    return document.getElementById(id).__itoComponent;
}
/**
 * Find a component name by DOM element Id
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
 * Each time this function is called it checks whether the element should be displayed or not.
 * @param {*} el the element to show or hide
 * @param {*} el_form optional the form element to check
 * @param {*} value optional the value to compare
 * Show or hide an element. Triggers an event on the element shown or hidden
 */
function toggleEl(el, el_form, compare_value) {
    el = eI(el);
    
    if (el_form == undefined) {   
        if (el.style.display === 'none') {
            el.style.display = 'block';
            // Manage required when showing the element
            manageRequiredFields(el, true);
        } else {
            el.style.display = 'none';
            // Manage required when hiding the element
            manageRequiredFields(el, false);
        }
    } else {
        let value = null;
        // if el_form is a checkbox
        if (el_form.tagName == 'INPUT' && el_form.type == 'checkbox') {
              if (el_form.checked) {
                value = el_form.value;
              }
        } else {
            value = el_form.value;
        }
        
        if (value === compare_value) {
            el.style.display = 'block';
            // Manage required when showing the element
            manageRequiredFields(el, true);
            // trigger event el show
            let event = new Event('show');
            el.dispatchEvent(event);
        } else {
            el.style.display = 'none';
            // Manage required when hiding the element
            manageRequiredFields(el, false);
            // trigger event el hide
            let event = new Event('hide');
            el.dispatchEvent(event);
        }
    }
}
/**
 * Hide an element
 * @param DOM el 
 * @param Function fn When the element is hidden, this function is called
 */
function elHide(el, fn) {
    el = eI(el);
    // with fade animation
    // add fade class
    el.classList.add('fade');
    el.classList.remove('show');
    // after 1 second hide the element, remove the fade class and call the function
    setTimeout(() => {
        el.classList.add('d-none');
        el.classList.remove('fade');
        if (fn !== undefined) {
            fn.call(el);
        }
    }, 200);
}

/**
 * Remove an element with fade animation
 * @param {} el 
 */
function elRemove(el) {
    el = eI(el);
    // with fade animation
    // add fade class
    el.classList.add('fade');
    el.classList.remove('show');
    // after 1 second remove the element, remove the fade class and call the function
    setTimeout(() => {
        el.remove();
    }, 200);
}

/**
 * Show an element
 * @param DOM el 
 * @param Function fn  When the element is shown, this function is called
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
 * If an element has the data-togglefield attribute, a toggleEl is created for the field 
 * with the name indicated in data-togglefield, for example:
 * <input type="text" name="field1" >
 * <div data-togglefield="field1" data-togglevalue="1">Show in value = 1</div>
 * In this way, elements are shown or hidden without calling the javascript
 */
class toggleEls 
{
    el = null
    constructor(el) {
        el = eI(el);
        this.el = el
        // get the attribute data-toggleField
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
 * service function to transform a query string into a FormData object
 * @param string queryString 
 * @returns 
 */
function getFormData(queryString) {
    const params = new URLSearchParams(queryString.startsWith('?') ? queryString.substring(1) : queryString);
    const formData = new FormData();
    // Iterate over each key-value pair and add it to formData
    params.forEach((value, key) => {
        formData.append(key, value);
    });
    return formData;
}


/**
 * Class for managing offcanvas that appears from the right
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

        // Listener per sincronizzare is_show quando Bootstrap chiude l'offcanvas
        this.el_container.addEventListener('hidden.bs.offcanvas', () => {
            this.is_show = false;
            document.getElementById('offCanvasBody').innerHTML = ''
            document.getElementById('offCanvasTitle').innerHTML = ''
        });
    }

    /**
     * Set offcanvas size
     * @param {string} size - 'sm' | 'lg' | 'xl' | 'fullscreen' | empty for default
     */
    size(size) {
        // Remove all size classes
        this.el_container.classList.remove('offcanvas-size-ito')
        this.el_container.classList.remove('offcanvas-size-ito-sm')
        this.el_container.classList.remove('offcanvas-size-ito-lg')
        this.el_container.classList.remove('offcanvas-size-ito-xl')
        this.el_container.classList.remove('offcanvas-size-ito-fullscreen')

        // Add new size class if specified
        switch(size) {
            case 'sm':
                this.el_container.classList.add('offcanvas-size-ito-sm')
                break;
            case 'lg':
                this.el_container.classList.add('offcanvas-size-ito-lg')
                break;
            case 'xl':
                this.el_container.classList.add('offcanvas-size-ito-xl')
                break;
            case 'fullscreen':
                this.el_container.classList.add('offcanvas-size-ito-fullscreen')
                break;
            default:
                // default size (no additional class or empty string)
                this.el_container.classList.add('offcanvas-size-ito')
                break;
        }
    }

    show(title = '', body = '') {
        // Only clear content if we're going to set new content (title or body provided)
        // This prevents clearing content when show() is called without parameters
        if (!this.is_show && (title !== '' || body !== '')) {
            document.getElementById('offCanvasBody').innerHTML = ''
            document.getElementById('offCanvasTitle').innerHTML = ''
        }

        // Set content only if explicitly provided (not empty string)
        if (title !== '' && title !== undefined) {
            this.title(title);
        }
        if (body !== '' && body !== undefined) {
            this.body(body);
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
        updateContainer(document.getElementById('offCanvasTitle'));
    }

    body(html) {
        document.getElementById('offCanvasBody').innerHTML = html;
        updateContainer(document.getElementById('offCanvasBody'));
    }
    get_el() {
        return  this.el_container;
    }
}


class Toasts {
    toastEl = null;
    toastBody = null;
    toastType = null;
    
    // Gestione coda operazioni
    currentOperation = null;
    queuedOperation = null;
    operationInProgress = false;
    operationTimeout = null;
    OPERATION_DURATION = 500; // 0.5 secondi
    
    constructor() {
        if (document.getElementById('toastUp') != null) {
            this.toastEl = new bootstrap.Toast(document.getElementById('toastUp'));
        }
    }

    // Metodo per eseguire un'operazione con gestione coda
    async executeOperation(operation) {
        // Se c'è un'operazione in corso
        if (this.operationInProgress) {
            // Sostituisci l'operazione in coda con quella nuova
            this.queuedOperation = operation;
            return;
        }

        // Esegui l'operazione corrente
        this.operationInProgress = true;
        this.currentOperation = operation;
        try {
            // Esegui l'operazione
            if (operation.type === 'show') {
                this._doShow(operation.html, operation.toastType);
            } else if (operation.type === 'hide') {
                this._doHide();
            }

            // Aspetta il tempo minimo tra operazioni
            await this.waitOperation();

        } finally {
            this.operationInProgress = false;
            this.currentOperation = null;

            // Se c'è un'operazione in coda, eseguila
            if (this.queuedOperation) {
                const nextOperation = this.queuedOperation;
                this.queuedOperation = null;
                // Esegui ricorsivamente l'operazione in coda
                this.executeOperation(nextOperation);
            }
        }
    }

    // Metodo helper per attendere la durata dell'operazione
    waitOperation() {
        return new Promise(resolve => {
            this.operationTimeout = setTimeout(() => {
                this.operationTimeout = null;
                resolve();
            }, this.OPERATION_DURATION);
        });
    }

    // Metodo pubblico show con gestione coda
    show(html, type) {
        this.executeOperation({
            type: 'show',
            html: html,
            toastType: type
        });
    }

    // Metodo pubblico hide con gestione coda
    hide() {
        this.executeOperation({
            type: 'hide'
        });
    }

    // Metodo interno per mostrare il toast (esecuzione effettiva)
    _doShow(html, type) {
        if (typeof html !== 'undefined' && html.trim() !== '') {
            if (typeof type == 'undefined' || type.trim() == '') {
                type = this.toastType ?? 'primary';
            }
            this.body(html, type);
        }
        this.toastEl.show();
    }

    // Metodo interno per nascondere il toast (esecuzione effettiva)
    _doHide() {
        this.toastEl.hide();
        document.getElementById('toastBodyTxt').innerHTML = '';
    }

    /**
     * Imposta il contenuto e lo stile del toast
     * @param {string} html - Contenuto HTML del toast
     * @param {string} type - Tipo di toast: success | danger | warning | primary
     */
    body(html, type) {
        this.toastType = type;
        this.toastBody = html;
        
        const toastBodyEl = document.getElementById('toastBody');
        
        // Rimuovi tutte le classi di stile precedenti
        toastBodyEl.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-warning', 'text-bg-primary');
        
        // Aggiungi la classe appropriata
        switch (type) {
            case 'success':
                toastBodyEl.classList.add('text-bg-success');
                break;
            case 'danger':
            case 'error':
                toastBodyEl.classList.add('text-bg-danger');
                break;
            case 'warning':
                toastBodyEl.classList.add('text-bg-warning');
                break;
            case 'primary':
            default:
                toastBodyEl.classList.add('text-bg-primary');
                break;
        }
       
        document.getElementById('toastBodyTxt').innerHTML = html;
    }

    // Metodo per pulire eventuali timeout pendenti
    destroy() {
        if (this.operationTimeout) {
            clearTimeout(this.operationTimeout);
            this.operationTimeout = null;
        }
    }
    get_el() {
        return this.toastEl;
    }
}

class Modal {
    modal_el = null
    dom_el = null
    el_body = null
    el_title = null
    el_footer = null
    preload = null

    constructor()  {
        this.dom_el = document.getElementById('itoModal')
        if (this.dom_el != null) {
            this.modal_el = new bootstrap.Modal(this.dom_el, {backdrop: 'static'} )
            this.el_body = this.dom_el.querySelector('.js-modal-body')
            this.el_title = this.dom_el.querySelector('.js-modal-title')
            this.el_footer = this.dom_el.querySelector('.js-modal-footer')

            // Initialize loading spinner
            let preload = this.dom_el.querySelector('.js-ito-loading')
            if (preload) {
                this.preload = new Loading(preload)
            }
        }
    }

    /**
     * Set modal size
     * @param {string} size - 'sm' | 'lg' | 'xl' | 'fullscreen' | empty for default
     */
    size(size) {
        const dialogEl = this.dom_el.querySelector('.modal-dialog')
        if (!dialogEl) return;

        // Remove all size classes
        dialogEl.classList.remove('modal-sm', 'modal-lg', 'modal-xl', 'modal-fullscreen')

        // Add new size class if specified
        switch(size) {
            case 'sm':
                dialogEl.classList.add('modal-sm')
                break;
            case 'lg':
                dialogEl.classList.add('modal-lg')
                break;
            case 'xl':
                dialogEl.classList.add('modal-xl')
                break;
            case 'fullscreen':
                dialogEl.classList.add('modal-fullscreen')
                break;
            // default: normal size (no additional class)
        }
    }

    /**
     * Show loading spinner
     */
    loading_show() {
        if (this.preload) {
            this.preload.show()
        }
    }

    /**
     * Hide loading spinner
     */
    loading_hide() {
        if (this.preload) {
            this.preload.hide()
        }
    }

    show(title, body, footer) {
        // Set content only if explicitly provided (not undefined or empty string)
        if (title !== undefined && title !== '') this.title(title)
        if (body !== undefined && body !== '') this.body(body)
        if (footer !== undefined && footer !== '') this.footer(footer)
        this.modal_el.show()
    }

    hide() {
        this.title('')
        this.body('')
        this.footer('')
        this.modal_el.hide()
    }

    body(html) {
        if (html !== undefined) {
            this.el_body.innerHTML = html
            updateContainer(this.el_body);
        }
    }

    title(html) {
        if (html !== undefined) {
            this.el_title.innerHTML = html
            updateContainer(this.el_title);
        }
    }

    footer(html) {
        if (html !== undefined) {
            this.el_footer.innerHTML = html
        }
    }

    get_el() {
        return this.dom_el;
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

    // Initialize action lists
    initActionLists();

    // Auto-dismiss alerts after 8 seconds
    autoDismissAlerts();

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
  // Static registry of groups: { groupName: [instance1, instance2, ...] }
  static groups = {};

  constructor(container, options = {}) {
    this.container = container;
    this.handleSelector = options.handleSelector || null;
    this.onUpdate = options.onUpdate;
    this.groupName = null;

    this.draggedItem = null;
    this.draggingClone = null;
    this.placeholder = null;
    this.offsetY = 0;
    this.isDragging = false;
    this.mouseDownPosition = null;
    this.sourceContainer = null; // source container during drag
    this.targetInstance = null;  // current target instance during drag

    this.onMouseMovebound = this.onGlobalMouseMove.bind(this);
    this.onMouseUpbound = this.onGlobalMouseUp.bind(this);

    this.init();

    // If a group was passed in options, register immediately
    if (options.group) {
      this.joinGroup(options.group);
    }
  }

  // ─── Group Management ─────────────────────────────────────

  /**
   * Adds this instance to a shared drag group.
   * If it was already in another group, it is removed from that one first.
   * @param {string} groupName - Group name
   */
  joinGroup(groupName) {
    if (this.groupName === groupName) return;
    if (this.groupName) {
      this.leaveGroup();
    }
    this.groupName = groupName;
    if (!ItoSortableList.groups[groupName]) {
      ItoSortableList.groups[groupName] = [];
    }
    ItoSortableList.groups[groupName].push(this);
  }

  /**
   * Removes this instance from the current group.
   */
  leaveGroup() {
    if (!this.groupName) return;
    const group = ItoSortableList.groups[this.groupName];
    if (group) {
      const idx = group.indexOf(this);
      if (idx !== -1) group.splice(idx, 1);
      if (group.length === 0) {
        delete ItoSortableList.groups[this.groupName];
      }
    }
    this.groupName = null;
  }

  /**
   * Returns all instances in the same group (excluding this one).
   */
  getGroupSiblings() {
    if (!this.groupName) return [];
    return (ItoSortableList.groups[this.groupName] || []).filter(i => i !== this);
  }

  // ─── Initialization ───────────────────────────────────────

  init() {
    [...this.container.children].forEach(item => {
      this.makeDraggable(item);
    });
  }

  makeDraggable(item) {
    const handle = this.handleSelector
      ? item.querySelector(this.handleSelector)
      : item;

    if (!handle) return;

    item.removeAttribute('draggable');

    // Remove any previous handler (cross-list rebind)
    if (item._ito_mousedown_handler && item._ito_handle) {
      item._ito_handle.removeEventListener('mousedown', item._ito_mousedown_handler);
    }

    const self = this;
    const handler = function(e) {
      e.preventDefault();
      e.stopPropagation();

      self.isDragging = true;
      self.draggedItem = item;
      self.sourceContainer = self.container;
      self.targetInstance = self;

      // Prevent text selection during drag
      document.body.style.userSelect = 'none';
      document.body.style.webkitUserSelect = 'none';

      const rect = item.getBoundingClientRect();
      self.offsetY = e.clientY - rect.top;
      self.offsetX = e.clientX - rect.left;
      self.mouseDownPosition = { x: e.clientX, y: e.clientY };

      // Calculate the correct placeholder position BEFORE removing the item
      const nextSibling = item.nextElementSibling;

      // Placeholder
      self.placeholder = document.createElement('div');
      self.placeholder.className = item.className + ' drag-placeholder';
      self.placeholder.style.height = rect.height + 'px';

      // Insert the placeholder at the exact position of the item
      if (nextSibling) {
        self.container.insertBefore(self.placeholder, nextSibling);
      } else {
        self.container.appendChild(self.placeholder);
      }
      item.remove();

      // Visual clone
      self.draggingClone = item.cloneNode(true);
      self.draggingClone.classList.add('dragging-clone');
      self.draggingClone.style.width = rect.width + 'px';
      self.draggingClone.style.position = 'fixed';
      self.draggingClone.style.top = (e.clientY - self.offsetY) + 'px';
      self.draggingClone.style.left = (e.clientX - self.offsetX) + 'px';
      self.draggingClone.style.zIndex = '99999';
      self.draggingClone.style.pointerEvents = 'none';
      document.body.appendChild(self.draggingClone);

      document.addEventListener('mousemove', self.onMouseMovebound);
      document.addEventListener('mouseup', self.onMouseUpbound);
    };

    item._ito_mousedown_handler = handler;
    item._ito_handle = handle;
    handle.addEventListener('mousedown', handler);
  }

  // ─── Mouse Move ────────────────────────────────────────────

  onGlobalMouseMove(e) {
    if (!this.isDragging || !this.draggingClone) return;
    e.preventDefault();

    this.draggingClone.style.top = (e.clientY - this.offsetY) + 'px';
    this.draggingClone.style.left = (e.clientX - this.offsetX) + 'px';
    const targetInstance = this.findTargetInstance(e.clientX, e.clientY);

    if (targetInstance) {
      // If the target has changed, move the placeholder to the new container
      if (this.targetInstance !== targetInstance) {
        if (this.placeholder && this.placeholder.parentNode) {
          this.placeholder.remove();
        }
        this.targetInstance = targetInstance;
      }

      const afterElement = this.getDragAfterElement(targetInstance.container, e.clientY);
      if (afterElement) {
        targetInstance.container.insertBefore(this.placeholder, afterElement);
      } else {
        targetInstance.container.appendChild(this.placeholder);
      }
    }
  }

  // ─── Mouse Up ──────────────────────────────────────────────

  onGlobalMouseUp(e) {
    if (!this.isDragging) return;
    e.preventDefault();

    document.removeEventListener('mousemove', this.onMouseMovebound);
    document.removeEventListener('mouseup', this.onMouseUpbound);

    if (this.draggedItem) {
      const destinationContainer = this.targetInstance
        ? this.targetInstance.container
        : this.sourceContainer;

      if (this.placeholder && this.placeholder.parentNode) {
        this.placeholder.parentNode.insertBefore(this.draggedItem, this.placeholder);
        this.placeholder.remove();
        this.placeholder = null;
      } else {
        destinationContainer.appendChild(this.draggedItem);
      }

      this.draggedItem.style.display = '';

      // If the element was moved to another container,
      // make it draggable in the new instance as well
      if (this.targetInstance && this.targetInstance !== this) {
        this.targetInstance.makeDraggable(this.draggedItem);
      }
    }

    const movedToAnotherList = this.targetInstance && this.targetInstance !== this;
    const sourceInstance = this;
    const destInstance = this.targetInstance || this;

    this.cleanupDrag();

    // Callback with extended information
    if (sourceInstance.onUpdate) {
      sourceInstance.onUpdate(sourceInstance.getCurrentOrder(), {
        type: movedToAnotherList ? 'cross-list' : 'reorder',
        sourceContainer: sourceInstance.container,
        destinationContainer: destInstance.container,
      });
    }
    // Notify the destination list as well if different
    if (movedToAnotherList && destInstance.onUpdate) {
      destInstance.onUpdate(destInstance.getCurrentOrder(), {
        type: 'cross-list-receive',
        sourceContainer: sourceInstance.container,
        destinationContainer: destInstance.container,
      });
    }
  }

  // ─── Target Search ─────────────────────────────────────────

  /**
   * Finds which instance (own or from the group) is under the cursor.
   */
  findTargetInstance(x, y) {
    // Check own container first
    if (this.isPointInsideContainer(this.container, x, y)) {
      return this;
    }
    // Then check group siblings
    for (const sibling of this.getGroupSiblings()) {
      if (this.isPointInsideContainer(sibling.container, x, y)) {
        return sibling;
      }
    }
    // If the cursor is not over any container, keep the current target
    return this.targetInstance;
  }

  isPointInsideContainer(container, x, y) {
    const rect = container.getBoundingClientRect();
    return (
      x >= rect.left &&
      x <= rect.right &&
      y >= rect.top &&
      y <= rect.bottom
    );
  }

  // ─── Cleanup ───────────────────────────────────────────────

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
    // Restore text selection
    document.body.style.userSelect = '';
    this.draggedItem = null;
    this.draggingClone = null;
    this.placeholder = null;
    this.sourceContainer = null;
    this.targetInstance = null;
    this.isDragging = false;
  }

  getDragAfterElement(container, y) {
    const draggableElements = [...container.children].filter(
      child => child !== this.draggedItem && child !== this.placeholder
    );

    let result = draggableElements.reduce(
      (closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
          return { offset, element: child };
        }
        return closest;
      },
      { offset: Number.NEGATIVE_INFINITY }
    );

    return result.element;
  }

  getCurrentOrder() {
    return [...this.container.children].filter(
      child => !child.classList.contains('drag-placeholder')
    );
  }

  // ─── Destroy ───────────────────────────────────────────────

  destroy() {
    document.removeEventListener('mousemove', this.onMouseMovebound);
    document.removeEventListener('mouseup', this.onMouseUpbound);

    this.cleanupDrag();

    // Leave the group
    this.leaveGroup();

    if (this.container) {
      [...this.container.children].forEach(item => {
        if (item._ito_mousedown_handler && item._ito_handle) {
          item._ito_handle.removeEventListener('mousedown', item._ito_mousedown_handler);
          delete item._ito_mousedown_handler;
          delete item._ito_handle;
        }
      });
    }

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

// Global translation function
function __(key, params = []) {
    let text = key;
    
    // Search in the specific area
    if (typeof window.TRANSLATIONS !== 'undefined') {
        if (window.TRANSLATIONS[key]) {
            text = window.TRANSLATIONS[key];
        }
    }
   
   // Replace parameters with sprintf syntax
    // %s for strings, %d for numbers, %1$s for sequential parameters
    let paramIndex = 0;
    
    text = text.replace(/%(?:(\d+)\$)?([sd%])/g, (match, position, type) => {
        if (type === '%') {
            return '%'; // %% becomes %
        }
        
        let value;
        if (position) {
            // Sequential parameter (es: %1$s, %2$d)
            value = params[parseInt(position) - 1];
        } else {
            // Sequential parameter (es: %s, %d)
            value = params[paramIndex++];
        }
        
        if (value === undefined) {
            return match; // Maintain placeholder if no value
        }
        
        switch (type) {
            case 's': // string
                return String(value);
            case 'd': // number
                return parseInt(value) || 0;
            default:
                return value;
        }
    });

    return text;
}

// Wait for translations to be loaded
function waitForTranslations(timeout = 10000) {
    return new Promise((resolve, reject) => {
        const start = Date.now();
        
        function check() {
            if (window.TRANSLATIONS !== undefined) {
                resolve(window.TRANSLATIONS);
            } else if (Date.now() - start > timeout) {
                reject(new Error(`Timeout: TRANSLATIONS non trovato`));
            } else {
                setTimeout(check, 100);
            }
        }
        
        check();
    });
}

/**
 * Initialize all action lists on the page
 */
function initActionLists() {
    document.querySelectorAll('.js-action-list').forEach(container => {
        setupActionList(container);
    });
}

/**
 * Setup action list functionality for a specific container
 * @param {Element} container - The action list container element
 */
function setupActionList(container) {
    const targetInputId = container.dataset.targetInput;
    if (!targetInputId) {
        console.warn('Action list container missing data-target-input attribute:', container);
        return;
    }
    
    const hiddenInput = document.getElementById(targetInputId);
    if (!hiddenInput) {
        console.warn('Target input not found for action list:', targetInputId);
        return;
    }
    
    // Check if this action list is used as a filter
    const isFilter = hiddenInput.dataset.filterId && hiddenInput.dataset.filterType;
    
    // Get active class from first active item or use default
    let activeClass = 'active-action-list';
    const activeItem = container.querySelector('.js-action-item.active-action-list');
    if (activeItem) {
        // Find all classes on the active item that are not base classes
        const baseClasses = ['js-action-item', 'link-action'];
        const itemClasses = activeItem.className.split(' ');
        const possibleActiveClass = itemClasses.find(cls => 
            !baseClasses.includes(cls) && cls !== ''
        );
        if (possibleActiveClass) {
            activeClass = possibleActiveClass;
        }
    }
    
    // Apply initial state based on hidden input value
    updateActionListState(container, hiddenInput.value, activeClass);
    
    // Add click handlers to all action items
    container.querySelectorAll('.js-action-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const value = this.dataset.value || '';
            
            // Update hidden input value
            hiddenInput.value = value;
            
            // Update visual state
            updateActionListState(container, value, activeClass);
            
            // Handle filter integration if this is a filter
            if (isFilter) {
                handleActionListFilter(hiddenInput);
            }
            
            // Trigger change event on hidden input
            const changeEvent = new Event('change', { bubbles: true });
            hiddenInput.dispatchEvent(changeEvent);
            
            // If there's an onchange handler, execute it
            if (hiddenInput.onchange) {
                hiddenInput.onchange();
                hiddenInput.oninput();
            }
        });
    });
}

/**
 * Handle action list filter integration with the table filtering system
 * @param {Element} hiddenInput - The hidden input element
 */
function handleActionListFilter(hiddenInput) {
    const tableId = hiddenInput.dataset.filterId;
    const filterType = hiddenInput.dataset.filterType;
    const value = hiddenInput.value;
    
    if (!tableId || !filterType) {
        return;
    }
    
    // Get the table component using the existing getComponent function
    if (typeof getComponent === 'function') {
        const component = getComponent(tableId);
        if (component) {
            // Remove existing filters of this type
            component.filter_remove_start(filterType + ':');
            
            // Add new filter if value is not empty
            if (value !== '') {
                component.filter_add(filterType + ':' + value);
            }
            
            // Reset to page 1 and reload
            component.set_page(1);
            component.reload();
        }
    }
}

/**
 * Update the visual state of action list items
 * @param {Element} container - The action list container
 * @param {string} selectedValue - The currently selected value
 * @param {string} activeClass - The CSS class for active items
 */
function updateActionListState(container, selectedValue, activeClass = 'active-action-list') {
    container.querySelectorAll('.js-action-item').forEach(item => {
        const itemValue = item.dataset.value || '';
        
        if (itemValue === selectedValue) {
            // Add active class if not present
            if (!item.classList.contains(activeClass)) {
                item.classList.add(activeClass);
            }
        } else {
            // Remove active class if present
            item.classList.remove(activeClass);
        }
    });
}


/**
 * Convert bytes to human readable format
 * @param {number} bytes - The number of bytes
 * @returns {string} The human readable format
 */
function human_file_size(bytes) {
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    if (bytes == 0) return 'n/a';
    const i = parseInt(String(Math.floor(Math.log(bytes) / Math.log(1024))));
    return Math.round(bytes / Math.pow(1024, i)) + ' ' + sizes[i];
}

/**
 * Auto-dismiss alerts after a specified timeout
 * Applies to all alerts with the js-auto-dismiss class
 * @param {number} timeout - Milliseconds to wait before dismissing (default: 8000ms = 8 seconds)
 */
function autoDismissAlerts(timeout = 8000, container = document) {
    container.querySelectorAll('.js-auto-dismiss').forEach(alert => {
        setTimeout(() => {
            // Use Bootstrap's Alert instance to close it smoothly
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, timeout);
    });
}

/**
 * Clear search input and trigger filter update
 * Used by SearchBuilder clear button
 * @param {string|HTMLElement} inputElement - Input element ID or DOM element
 */
function clearSearchInput(inputElement) {
    // Get the input element
    let input;
    if (typeof inputElement === 'string') {
        // Remove # if present
        const id = inputElement.replace('#', '');
        input = document.getElementById(id);
    } else {
        input = inputElement;
    }

    if (!input) {
        console.warn('clearSearchInput: Input element not found', inputElement);
        return;
    }

    // Clear the value
    input.value = '';

    // Trigger both change and input events to ensure the filter is updated
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));

    // Focus the input after clearing
    input.focus();
}

function clearSearchInputButton(button) {
    button.addEventListener('click', () => {
        clearSearchInput(button.dataset.id);
    });
}
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.js-milk-clear-search').forEach(button => {
        clearSearchInputButton(button);
    });
});

    

/**
* Transforms links with data-fetch="get|post" into fetch calls.
* Avoids retransforming the same links.
* Handles the response like the sendForm() function.
 */

(function () {
    const FETCH_MARK = '__fetch_link_initialized__'; // hidden property to avoid double initialization

    /**
     * Initialize all links with data-fetch
     */
    function initFetchLinks(container = document) {
        const links = container.querySelectorAll('a[data-fetch]');
        links.forEach(link => {
            if (link[FETCH_MARK]) return; // già inizializzato
            link[FETCH_MARK] = true;

            link.addEventListener('click', async function (e) {
                e.preventDefault();

                if (link.classList.contains('disabled')) return; // already in progress

                const method = link.getAttribute('data-fetch').toUpperCase();
                const url = new URL(link.href, milk_url);

                // Disable link
                link.classList.add('disabled');

                // Show event loading (if present)
                if (window.plugin_loading) {
                    window.plugin_loading.show();
                }

                try {
                    let response;
                    if (method === 'GET') {
                        response = await fetch(url, {
                            method: 'GET',
                            credentials: 'same-origin'
                        });
                    } else if (method === 'POST') {
                        // Trasformiamo i parametri del link in form data
                        const formData = new FormData();
                        for (const [key, value] of url.searchParams.entries()) {
                            formData.append(key, value);
                        }
                        response = await fetch(url.origin + url.pathname, {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData
                        });
                    } else {
                        throw new Error(`Metodo non supportato: ${method}`);
                    }

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    jsonAction(data, link.parentNode);

                } catch (error) {
                    console.error('Fetch link failed:', error);
                    if (typeof window.toasts !== 'undefined') {
                        window.toasts.show('An error occurred while processing the request', 'danger');
                    }
                } finally {
                    // Rimuove stato disabled
                    link.classList.remove('disabled');
                    if (window.plugin_loading) {
                        window.plugin_loading.hide();
                    }
                }
            });
        });
    }

    /**
     * Initialize all divs with data-fetch and data-url
     * Loads content via fetch after page load with sequential delays
     */
   function initFetchDiv(container = document) {
        const divs = container.querySelectorAll('div[data-fetch][data-url]');
        if (divs.length === 0) return;

        divs.forEach((div) => {
            // Rimuovi il listener precedente se esiste per evitare duplicati
            if (div[FETCH_MARK] && div[FETCH_MARK].handler) {
                div.removeEventListener('click', div[FETCH_MARK].handler);
            }

            // Add click handler
            div.style.cursor = 'pointer'; // Optional: show it's clickable

            async function handleFetchClick() {
                // Prevent multiple fetches while one is in progress
                if (div.classList.contains('disabled')) return;

                const method = div.getAttribute('data-fetch').toUpperCase();
                const urlString = div.getAttribute('data-url');

                if (!urlString) {
                    console.warn('data-url not found on div:', div);
                    return;
                }

                const url = new URL(urlString, milk_url);

                // Disable div to prevent multiple clicks
                div.classList.add('disabled');

                // Show loading
                if (window.plugin_loading) {
                    window.plugin_loading.show();
                }

                try {
                    let response;
                    if (method === 'GET') {
                        response = await fetch(url, {
                            method: 'GET',
                            credentials: 'same-origin'
                        });
                    } else if (method === 'POST') {
                        const formData = new FormData();
                        for (const [key, value] of url.searchParams.entries()) {
                            formData.append(key, value);
                        }
                        response = await fetch(url.origin + url.pathname, {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData
                        });
                    } else {
                        throw new Error(`Unsupported method: ${method}`);
                    }

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    jsonAction(data, div);

                } catch (error) {
                    console.error('Fetch div failed:', error);
                    if (typeof window.toasts !== 'undefined') {
                        window.toasts.show('An error occurred while loading content', 'danger');
                    }
                    div.innerHTML = '<div class="alert alert-danger">Failed to load content</div>';
                } finally {
                    // Remove disabled state
                    div.classList.remove('disabled');
                    if (window.plugin_loading) {
                        window.plugin_loading.hide();
                    }
                }
            }

            div.addEventListener('click', handleFetchClick);

            // Mark as initialized and store the handler for potential removal
            div[FETCH_MARK] = {
                handler: handleFetchClick
            };
        });
    }

    // Inizializza all'avvio
    document.addEventListener('DOMContentLoaded', () => {
        initFetchLinks();
        // Load fetch divs after page is fully loaded and JS initialized
        setTimeout(() => initFetchDiv(), 100);
    });

    // Esporta se serve reinizializzare dopo update DOM
    window.initFetchLinks = initFetchLinks;
    window.initFetchDiv = initFetchDiv;
})();
