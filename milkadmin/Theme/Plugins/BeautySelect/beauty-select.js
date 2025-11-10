/**
 * TomSelectManager class
 */

class TomSelectManager {
    constructor() {
        this.instances = new Map();
        this.counter = 0;
        this.textInputModes = new Map();
    }

    createSelect({
        containerId,
        isMultiple = true,
        selectOptions = [],
        maxItems = 100,
        onChange = null,
        showToggleButton = false,
        floating = false,
        labelText = '',
        defaultValue = '',
        create = false
    }) {
        this.counter++;
        const selectContainerId = `select-container-${this.counter}`;
        const selectId = `select-${this.counter}`;

        // Create container
        const container = document.createElement('div');
        container.id = selectContainerId;
        container.className = 'select-container';

        // Create label
        const label = document.createElement('label');
        if (labelText != '') { 
            label.htmlFor = selectId;
            label.className = 'form-label mb-2';
            label.textContent = labelText
        }
        
        // Create input group wrapper for select and button
        const inputGroup = document.createElement('div');
        if (floating) {
            inputGroup.className = 'input-group form-floating';
        } else {
            inputGroup.className = 'input-group';
        }

        // Create select
        const select = document.createElement('select');
        select.id = selectId;
        select.className = 'form-select';
        select.multiple = isMultiple;
        

        // Create text input (initially hidden)
        const textInput = document.createElement('input');
        textInput.type = 'text';
        textInput.className = 'js-input-text form-control d-none';
        textInput.id = `${selectId}-text`;

        // Create toggle button only if showToggleButton is true
        const button = document.createElement('button');
        if (showToggleButton) {  
            button.type = 'button';
            button.className = 'btn btn-outline-secondary';
            button.innerHTML = '<i class="bi bi-pencil"></i>';
            button.addEventListener('click', () => this.toggleInputMode(containerId)); 
        }

        // Append elements in new structure
        if (!floating && labelText != '') { 
            container.appendChild(label);
        }
        inputGroup.appendChild(select);
       
        inputGroup.appendChild(textInput);
        if (showToggleButton) {
            inputGroup.appendChild(button);
        }
        if (floating && labelText != '') { 
            inputGroup.appendChild(label);
        }
        container.appendChild(inputGroup);

        // Add to DOM
        const targetContainer = document.getElementById(containerId);
        if (!targetContainer) {
            console.error(`Container with ID '${containerId}' not found. Cannot create select.`);
            return null;
        }
        targetContainer.appendChild(container);

        // Initialize TomSelect
        const ts = this.initializeTomSelect(select, {
            maxItems: isMultiple ? maxItems : 1,
            placeholder: isMultiple ? 'Select items...' : 'Select an item...',
            create: create,
        });

        // Add options
        this.setOptions(ts, selectOptions);

       
        // Add change event listener
        if (onChange) {
            ts.on('change', onChange);
            textInput.addEventListener('change', onChange);
        }

        // Store instance and toggle button state
        this.instances.set(containerId, ts);
        this.textInputModes.set(containerId, false);
        // Set value
        if (defaultValue != '') {   
            this.setValue(containerId, defaultValue);
        }
        return containerId;
    }

    toggleInputMode(containerId) {
        const container = document.getElementById(containerId);
        const instance = this.instances.get(containerId);
        const isTextMode = this.textInputModes.get(containerId);
        if (!container || !instance) return;

        const button = container.querySelector('.input-group button');
        if (!button) return; // Exit if no toggle button exists

        const inputGroup = container.querySelector('.input-group');
        const textInput = inputGroup.querySelector('.js-input-text');
        const tomSelectWrapper = inputGroup.querySelector('.ts-wrapper');

        if (isTextMode) {
            console.log ("ISTEXTMODE");
            // Switch back to TomSelect mode
            button.innerHTML = '<i class="bi bi-pencil"></i>';
            textInput.classList.add('d-none');
            tomSelectWrapper.classList.remove('d-none');
            
            // Transfer text input value to TomSelect if needed
            if (textInput.value) {
                instance.setValue(textInput.value);
            }
        } else {
            // Switch to text input mode
            button.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i>';
            textInput.classList.remove('d-none');
            tomSelectWrapper.classList.add('d-none');
            
            // Transfer TomSelect value to text input
            const currentValue = instance.getValue();
            textInput.value = Array.isArray(currentValue) ? currentValue.join(', ') : currentValue;
            // focus on the text input
            textInput.focus();
        }

        this.textInputModes.set(containerId, !isTextMode);
    }


    initializeTomSelect(selectElement, options = {}) {
        const defaultConfig = {
            create: false,
            sortField: {
                field: "text",
                direction: "asc"
            },
            optgroupField: 'optgroup',        // Nome del campo usato per collegare l'opzione al gruppo
            optgroupLabelField: 'value',      // Campo usato per l'etichetta visibile del gruppo
            optgroupValueField: 'value',      // Campo usato come identificatore interno del gruppo
            optgroupSearch: true,             // Abilita la ricerca nei gruppi
            searchField: ['value', 'optgroup'], // Campi in cui effettuare la ricerca
            optgroupOrder: 'asc',             // Ordina i gruppi in ordine alfabetico
            lockOptgroupOrder: true,           // Mantiene l'ordine dei gruppi durante la ricerca
            create:false,             // Abilita la creazione di nuove opzioni
            createOnBlur: false,     // Abilita la creazione di nuove opzioni al termine della digitazione
        };
        const config = { ...defaultConfig, ...options };
        config.maxOptions= 1000;
        return new TomSelect(selectElement, config);
    }

    setOptions(instance, options) {
        instance.clear();
        instance.clearOptions();
        
        options.forEach(option => {
            if (option.group) {
                // Add optgroup if it doesn't exist
                if (!instance.optgroups[option.group]) {
                    instance.addOptionGroup(option.group, {
                        label: option.group
                    });
                }
                // Add option to group
                instance.addOption({
                    value: option.value,
                    text: option.text,
                    optgroup: option.group
                });
            } else {
                // Add option without group
                instance.addOption({
                    value: option.value,
                    text: option.text
                });
            }
        });
    }

    updateOptions(containerId, options) {
        const instance = this.instances.get(containerId);
        if (instance) {
            this.setOptions(instance, options);
        }
    }

    removeSelect(containerId) {
        const instance = this.instances.get(containerId);
        if (instance) {
            instance.destroy();
            this.instances.delete(containerId);
            document.getElementById(containerId)?.remove();
        }
    }

    updateSelectConfig(containerId, isMultiple, maxItems = 3) {
        const instance = this.instances.get(containerId);
        if (instance) {
            const currentOptions = instance.options;
            const currentValue = instance.getValue();
            
            // Destroy current instance
            instance.destroy();
            
            // Get select element
            const selectElement = document.querySelector(`#${containerId} select`);
            selectElement.multiple = isMultiple;
            
            // Create new instance
            const newInstance = this.initializeTomSelect(selectElement, {
                maxItems: isMultiple ? maxItems : 1,
                placeholder: isMultiple ? 'Select items...' : 'Select an item...'
            });
            
            // Restore options and value
            this.setOptions(newInstance, Object.values(currentOptions));
            newInstance.setValue(currentValue);
            
            // Update instance in map
            this.instances.set(containerId, newInstance);
        }
    }

    getValue(containerId) {
        const instance = this.instances.get(containerId);
        const isTextMode = this.textInputModes.get(containerId);
        
        if (!instance) return null;
        
        if (isTextMode) {
            const container = document.getElementById(containerId);
            const textInput = container.querySelector('input[type="text"]');
            return textInput.value;
        }
        
        return instance.getValue();
    }

    setValue(containerId, value) {
        const instance = this.instances.get(containerId);
        const isTextMode = this.textInputModes.get(containerId);
        
        if (!instance) return;
        
        if (isTextMode) {
            const container = document.getElementById(containerId);
            const textInput = container.querySelector('input[type="text"]');
            textInput.value = value;
        } else {
           if (!instance.options.hasOwnProperty(value)) {
                // If the value is not in the options, add it
                instance.addOption({
                    value: value,
                    text: value
                });
            }
            instance.setValue(value);
        }
    }

    removeAll() {
        this.instances.forEach((instance, containerId) => {
            this.removeSelect(containerId);
        });
    }
}

window.beautySelect = new TomSelectManager();