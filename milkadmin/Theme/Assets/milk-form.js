/**
 * MilkForm - Gestione avanzata form per Milk Admin
 * 
 * @version 4.0.0
 * @description Validazione, campi calcolati e visibilità condizionale tramite espressioni
 * @requires ExpressionParser
 * 
 * FUNZIONALITÀ:
 * - Validazione con espressioni o validatori registrati
 * - Campi calcolati automaticamente
 * - Mostra/nascondi campi in base a condizioni
 * - Ricalcolo OTTIMIZZATO basato su grafo delle dipendenze
 * - Required condizionale tramite espressioni
 * - Protezione loop infiniti tramite visited set
 * 
 * CHANGELOG 4.0.0:
 * - NEW: Sistema di dipendenze basato su grafo
 * - NEW: Ricalcolo selettivo solo dei campi dipendenti
 * - NEW: Protezione loop infiniti con visited set
 * - NEW: Metodo _extractDependencies() per analisi AST
 * - NEW: Reverse dependency map per propagazione efficiente
 * - BREAKING: _recalculateAll() ora usato solo per init e submit
 * 
 * ATTRIBUTI DATA SUPPORTATI:
 * --------------------------
 * data-milk-expr="espressione"           - Espressione per calcolare il valore del campo
 * data-milk-show="espressione"           - Mostra il campo/container se l'espressione è true
 * data-milk-validate-expr="espressione"  - Espressione di validazione (deve restituire true/false)
 * data-milk-validate="validatorName"     - Nome del validatore registrato
 * data-milk-required-if="espressione"    - Campo obbligatorio se l'espressione è true
 * data-milk-default-expr="espressione"   - Valore di default da espressione (soft)
 * data-milk-message="Messaggio errore"   - Messaggio di errore personalizzato
 * data-milk-params='{"key":"value"}'     - Parametri JSON per il validatore
 * data-milk-validate-on="change|input|blur" - Evento su cui validare (default: change)
 * data-milk-group="groupName"            - Gruppo di validazione
 */

class MilkForm {
    
    // Registry statico per validatori custom
    static validators = {};
    
    // Registry istanze per form (protezione doppia inizializzazione)
    static instances = new WeakMap();
    
    // Configurazione di default
    static defaults = {
        validateOnChange: true,
        validateOnBlur: false,
        validateOnInput: false,
        showValidFeedback: true,
        scrollToFirstError: true,
        focusFirstError: true,
        bootstrapVersion: 5,
        invalidClass: 'is-invalid',
        validClass: 'is-valid',
        feedbackClass: 'invalid-feedback',
        validFeedbackClass: 'valid-feedback',
        hideClass: 'd-none',
        hideStyle: true,
        disableHiddenFields: true,
        recalculateOnAnyChange: true,
        debugDependencies: false          // Log delle dipendenze per debug
    };

    /**
     * Costruttore
     * @param {HTMLFormElement|string} form - Elemento form o selettore CSS
     * @param {Object} options - Opzioni di configurazione
     * @returns {MilkForm} - Istanza esistente o nuova
     */
    constructor(form, options = {}) {
        const formElement = typeof form === 'string' ? document.querySelector(form) : form;
        
        if (!formElement || formElement.tagName !== 'FORM') {
            throw new Error('MilkForm: Elemento form non valido');
        }
        
        // Controlla se esiste già un'istanza per questo form
        if (MilkForm.instances.has(formElement)) {
            const existingInstance = MilkForm.instances.get(formElement);
            
            if (options.forceNew) {
                existingInstance.destroy();
            } else {
                console.info('MilkForm: Istanza già esistente per questo form, riutilizzo esistente');
                return existingInstance;
            }
        }
        
        this.form = formElement;
        this.options = { ...MilkForm.defaults, ...options };
        
        // Istanza ExpressionParser
        if (typeof ExpressionParser === 'undefined') {
            throw new Error('MilkForm: ExpressionParser non trovato. Includere expression-parser.js prima di milk-form.js');
        }
        this.parser = new ExpressionParser();
        
        // Cache dei campi
        this.fields = new Map();              // Campi validabili
        this.calculatedFields = new Map();    // Campi calcolati
        this.defaultFields = new Map();       // Campi con default expr (soft)
        this.conditionalElements = new Map(); // Elementi con show/hide
        
        // ============================================================
        // NUOVO: Sistema dipendenze
        // ============================================================
        this.dependencyGraph = new Map();      // fieldId -> Set di dipendenze (parametri usati)
        this.reverseDependencies = new Map();  // parametro -> Set di fieldId che lo usano
        this.allReactiveFields = new Map();    // Tutti i campi reattivi (calculated + default + conditional + requiredIf + validateExpr)
        
        // Stato
        this.errors = new Map();
        this.hiddenFields = new Set();
        
        // Event listeners
        this._boundListeners = new Map();
        this._formChangeHandler = null;
        this._isRecalculating = false;
        
        // Callback
        this.callbacks = {
            onValidate: options.onValidate || null,
            onError: options.onError || null,
            onSuccess: options.onSuccess || null,
            onFieldValidate: options.onFieldValidate || null,
            onCalculate: options.onCalculate || null,
            onVisibilityChange: options.onVisibilityChange || null,
            onChange: options.onChange || null
        };
        
        // Registra istanza
        MilkForm.instances.set(this.form, this);
        
        // Inizializza
        this._init();
    }
    
    /**
     * Metodo statico per ottenere l'istanza di un form
     */
    static getInstance(form) {
        const formElement = typeof form === 'string' ? document.querySelector(form) : form;
        return formElement ? MilkForm.instances.get(formElement) || null : null;
    }

    /**
     * Registra un validatore custom
     */
    static registerValidator(name, fn) {
        if (typeof fn !== 'function') {
            throw new Error(`MilkForm: Il validatore "${name}" deve essere una funzione`);
        }
        MilkForm.validators[name] = fn;
    }

    /**
     * Inizializzazione
     * @private
     */
    _init() {
        this.form.setAttribute('novalidate', 'true');
        
        this._scanFields();
        this._buildDependencyGraph();      // NUOVO: costruisce il grafo
        this._bindGlobalFormListener();
        this._bindValidationEvents();
        
        // Esegui calcoli e visibilità iniziali (ricalcolo completo)
        this._recalculateAll();
        
        if (this.options.debugDependencies) {
            this._logDependencyGraph();
        }
    }

    // ============================================================
    // NUOVO: Sistema di estrazione e gestione dipendenze
    // ============================================================

    /**
     * Estrae le dipendenze (parametri) da un AST
     * @param {Object} ast - Albero AST
     * @returns {Set<string>} - Set dei nomi dei parametri usati
     * @private
     */
    _extractDependencies(ast) {
        const deps = new Set();
        const NT = ExpressionParser.NodeType;
        
        const visit = (node) => {
            if (!node) return;
            
            switch (node.type) {
                case NT.PARAMETER:
                    deps.add(node.value);
                    break;
                    
                case NT.BINARY_OP:
                case NT.ASSIGNMENT:
                    visit(node.left);
                    visit(node.right);
                    break;
                    
                case NT.UNARY_OP:
                    visit(node.right);
                    break;
                    
                case NT.IF_STATEMENT:
                    visit(node.condition);
                    if (node.thenBranch) {
                        node.thenBranch.forEach(stmt => visit(stmt));
                    }
                    if (node.elseBranch) {
                        node.elseBranch.forEach(stmt => visit(stmt));
                    }
                    break;
                    
                case NT.FUNCTION_CALL:
                    if (node.arguments) {
                        node.arguments.forEach(arg => visit(arg));
                    }
                    break;
                    
                case NT.PROGRAM:
                    if (node.statements) {
                        node.statements.forEach(stmt => visit(stmt));
                    }
                    break;
                    
                // NUMBER, STRING, DATE, IDENTIFIER non hanno dipendenze da parametri form
                default:
                    break;
            }
        };
        
        visit(ast);
        return deps;
    }

    /**
     * Costruisce il grafo delle dipendenze per tutti i campi reattivi
     * @private
     */
    _buildDependencyGraph() {
        this.dependencyGraph.clear();
        this.reverseDependencies.clear();
        this.allReactiveFields.clear();
        
        // 1. Raccogli tutti i campi calcolati
        this.calculatedFields.forEach((data, fieldId) => {
            this._registerReactiveField(fieldId, data.config.expr, 'calculated', data.element);
        });

        // 1b. Raccogli tutti i campi con default expr
        this.defaultFields.forEach((data, fieldId) => {
            this._registerReactiveField(fieldId, data.config.defaultExpr, 'default', data.element);
        });
        
        // 2. Raccogli tutti gli elementi con visibilità condizionale
        this.conditionalElements.forEach((data, id) => {
            this._registerReactiveField(id, data.expr, 'visibility', data.element);
        });
        
        // 3. Raccogli tutti i campi con requiredIf o validateExpr
        this.fields.forEach((data, fieldId) => {
            if (data.config.requiredIf) {
                this._registerReactiveField(fieldId, data.config.requiredIf, 'requiredIf', data.element);
            }
            if (data.config.validateExpr) {
                this._registerReactiveField(fieldId, data.config.validateExpr, 'validateExpr', data.element);
            }
        });
    }

    /**
     * Registra un campo reattivo nel grafo delle dipendenze
     * @private
     */
    _registerReactiveField(fieldId, expression, type, element) {
        if (!expression) return;
        
        try {
            const ast = this.parser.parse(expression);
            const deps = this._extractDependencies(ast);
            
            // Salva nel grafo diretto
            if (!this.dependencyGraph.has(fieldId)) {
                this.dependencyGraph.set(fieldId, new Set());
            }
            deps.forEach(dep => this.dependencyGraph.get(fieldId).add(dep));
            
            // Costruisci reverse map
            deps.forEach(dep => {
                if (!this.reverseDependencies.has(dep)) {
                    this.reverseDependencies.set(dep, new Set());
                }
                this.reverseDependencies.get(dep).add(fieldId);
            });
            
            // Salva info campo reattivo
            if (!this.allReactiveFields.has(fieldId)) {
                this.allReactiveFields.set(fieldId, {
                    types: new Set(),
                    element: element,
                    expressions: {}
                });
            }
            const fieldInfo = this.allReactiveFields.get(fieldId);
            fieldInfo.types.add(type);
            fieldInfo.expressions[type] = expression;
            
        } catch (e) {
            console.warn(`MilkForm: Errore parsing espressione per "${fieldId}":`, e.message);
        }
    }

    /**
     * Ottiene tutti i campi che dipendono da un parametro (ricorsivamente)
     * Usa BFS con visited set per prevenire loop infiniti
     * @param {string} paramName - Nome del parametro modificato
     * @returns {Array<string>} - Lista ordinata dei fieldId da ricalcolare
     * @private
     */
    _getAffectedFields(paramName) {
        const affected = [];
        const visited = new Set();
        const queue = [];
        
        // Normalizza il nome del parametro (gestisce varianti id/name)
        const paramVariants = this._getParameterVariants(paramName);
        
        // Trova tutti i campi direttamente dipendenti
        paramVariants.forEach(variant => {
            const directDeps = this.reverseDependencies.get(variant);
            if (directDeps) {
                directDeps.forEach(fieldId => {
                    if (!visited.has(fieldId)) {
                        queue.push(fieldId);
                    }
                });
            }
        });
        
        // BFS per trovare dipendenze a cascata
        while (queue.length > 0) {
            const fieldId = queue.shift();
            
            if (visited.has(fieldId)) {
                continue; // Loop detection
            }
            
            visited.add(fieldId);
            affected.push(fieldId);
            
            // Cerca se questo campo è usato da altri campi
            const fieldVariants = this._getParameterVariants(fieldId);
            fieldVariants.forEach(variant => {
                const cascadeDeps = this.reverseDependencies.get(variant);
                if (cascadeDeps) {
                    cascadeDeps.forEach(depFieldId => {
                        if (!visited.has(depFieldId)) {
                            queue.push(depFieldId);
                        }
                    });
                }
            });
        }
        
        return affected;
    }

    /**
     * Genera varianti del nome parametro per matching
     * (gestisce formdata*, data[*], etc.)
     * @private
     */
    _getParameterVariants(name) {
        const variants = new Set([name]);
        
        // Se inizia con 'formdata', aggiungi versione senza prefisso
        if (name.startsWith('formdata')) {
            variants.add(name.replace(/^formdata/, ''));
        }
        
        // Se è nel formato data[name], estrai il nome interno
        const match = name.match(/^data\[([^\]]+)\]/);
        if (match) {
            variants.add(match[1]);
        }
        
        // Aggiungi anche versione con prefisso formdata
        if (!name.startsWith('formdata')) {
            variants.add('formdata' + name);
        }
        
        return variants;
    }

    /**
     * Ricalcola solo i campi dipendenti da un parametro modificato
     * @param {string} changedFieldId - ID del campo modificato
     * @private
     */
    _recalculateDependents(changedFieldId) {
        // Aggiorna il singolo parametro nel parser
        const field = this.getField(changedFieldId);
        if (field) {
            const keys = this._getFieldKeys(field);
            const value = this._getFieldValue(field);
            keys.forEach(key => {
                this.parser.setParameter(key, value);
            });
        }
        
        // Trova tutti i campi affetti
        const affectedFields = this._getAffectedFields(changedFieldId);
        
        if (this.options.debugDependencies && affectedFields.length > 0) {
            console.log(`[MilkForm] Campo "${changedFieldId}" modificato. Ricalcolo:`, affectedFields);
        }
        
        // Se non ci sono campi affetti, esci
        if (affectedFields.length === 0) {
            return;
        }
        
        // Ricalcola in ordine (BFS garantisce ordine corretto)
        const recalculatedValues = new Map();
        
        affectedFields.forEach(fieldId => {
            const reactiveInfo = this.allReactiveFields.get(fieldId);
            if (!reactiveInfo) return;
            
            // Aggiorna parametri se il campo è stato già ricalcolato
            // (per gestire dipendenze a cascata)
            if (recalculatedValues.has(fieldId)) {
                const keys = this._getFieldKeys(reactiveInfo.element);
                keys.forEach(key => {
                    this.parser.setParameter(key, recalculatedValues.get(fieldId));
                });
            }
            
            // Ricalcola in base al tipo
            if (reactiveInfo.types.has('calculated')) {
                const newValue = this._calculateFieldById(fieldId);
                if (newValue !== undefined) {
                    recalculatedValues.set(fieldId, newValue);
                    // Aggiorna subito il parser per i campi successivi
                    const keys = this._getFieldKeys(reactiveInfo.element);
                    keys.forEach(key => {
                        this.parser.setParameter(key, newValue);
                    });
                }
            }

            if (reactiveInfo.types.has('default')) {
                const newValue = this._applyDefaultExpr(fieldId);
                if (newValue !== undefined) {
                    recalculatedValues.set(fieldId, newValue);
                    const keys = this._getFieldKeys(reactiveInfo.element);
                    keys.forEach(key => {
                        this.parser.setParameter(key, newValue);
                    });
                }
            }
            
            if (reactiveInfo.types.has('visibility')) {
                this._updateElementVisibility(fieldId);
            }
            
            if (reactiveInfo.types.has('requiredIf') || reactiveInfo.types.has('validateExpr')) {
                this._refreshFieldValidationState(fieldId);
            }
        });
    }

    /**
     * Calcola un campo e ritorna il nuovo valore
     * @private
     */
    _calculateFieldById(fieldId) {
        const fieldData = this.calculatedFields.get(fieldId);
        if (!fieldData) return undefined;
        
        const { element, config } = fieldData;
        
        try {
            const result = this.parser.analyze(config.expr, true);
            
            if (result.error) {
                console.warn(`MilkForm: Errore calcolo "${fieldId}":`, result.error);
                // Svuota il campo in caso di errore per evitare valori stale
                if (element.type === 'checkbox') {
                    element.checked = false;
                } else {
                    element.value = '';
                }
                return undefined;
            }
            
            let newValue = result.result;

            if (element.type === 'checkbox') {
                const checked = this.parser.normalizeCheckboxValue(newValue);
                if (element.checked !== checked) {
                    element.checked = checked;
                }
                if (this.callbacks.onCalculate) {
                    this.callbacks.onCalculate(element, checked, config.expr);
                }
                return checked ? 1 : 0;
            }
            
            // Formatta numeri
            if (typeof newValue === 'number') {
                const precisionAttr = element.dataset.milkPrecision ?? element.dataset.milkDecimals ?? null;
                const precision = precisionAttr !== null && precisionAttr !== '' ? parseInt(precisionAttr, 10) : 2;
                if (!isNaN(precision) && precision >= 0) {
                    const factor = Math.pow(10, precision);
                    newValue = Math.round(newValue * factor) / factor;
                } else {
                    newValue = Math.round(newValue * 100) / 100;
                }
            }
            
            if (newValue instanceof Date) {
                newValue = this.parser.formatResult(newValue);
            }
            
            // Imposta solo se diverso
            if (element.value !== String(newValue)) {
                element.value = newValue;
            }
            
            if (this.callbacks.onCalculate) {
                this.callbacks.onCalculate(element, newValue, config.expr);
            }
            
            return newValue;
        } catch (e) {
            console.warn(`MilkForm: Errore calcolo "${fieldId}":`, e.message);
            // Svuota il campo in caso di eccezione per evitare valori stale
            if (element.type === 'checkbox') {
                element.checked = false;
            } else {
                element.value = '';
            }
            return undefined;
        }
    }

    /**
     * Applica un default "soft" basato su espressione
     * @private
     */
    _applyDefaultExpr(fieldId) {
        const fieldData = this.defaultFields.get(fieldId);
        if (!fieldData) return undefined;

        const { element, config } = fieldData;
        if (!config.defaultExpr) return undefined;

        if (element.dataset.milkDefaultDirty === '1') {
            return undefined;
        }

        const currentValue = this._getFieldValue(element);
        const isEmpty = currentValue === '' || currentValue === null || currentValue === undefined ||
            (typeof currentValue === 'number' && isNaN(currentValue));
        const wasAutoApplied = element.dataset.milkDefaultApplied === '1';

        if (!isEmpty && !wasAutoApplied) {
            return undefined;
        }

        try {
            const result = this.parser.analyze(config.defaultExpr, true);

            if (result.error) {
                console.warn(`MilkForm: Errore defaultExpr "${fieldId}":`, result.error);
                return undefined;
            }

            let newValue = result.result;

            if (element.type === 'checkbox') {
                const checked = this.parser.normalizeCheckboxValue(newValue);
                if (element.checked !== checked) {
                    element.checked = checked;
                }
                element.dataset.milkDefaultApplied = '1';
                return checked ? 1 : 0;
            }

            if (typeof newValue === 'number') {
                const precisionAttr = element.dataset.milkPrecision ?? element.dataset.milkDecimals ?? null;
                const precision = precisionAttr !== null && precisionAttr !== '' ? parseInt(precisionAttr, 10) : 2;
                if (!isNaN(precision) && precision >= 0) {
                    const factor = Math.pow(10, precision);
                    newValue = Math.round(newValue * factor) / factor;
                } else {
                    newValue = Math.round(newValue * 100) / 100;
                }
            }

            if (newValue instanceof Date) {
                newValue = this.parser.formatResult(newValue);
            }

            if (element.value !== String(newValue)) {
                element.value = newValue;
            }

            element.dataset.milkDefaultApplied = '1';
            return newValue;
        } catch (e) {
            console.warn(`MilkForm: Errore defaultExpr "${fieldId}":`, e.message);
            return undefined;
        }
    }

    /**
     * Aggiorna lo stato di validazione di un campo (per requiredIf)
     * @private
     */
    _refreshFieldValidationState(fieldId) {
        const fieldData = this.fields.get(fieldId);
        if (!fieldData) return;
        
        const { element, config } = fieldData;
        
        // Se il campo non è attualmente in errore, non fare nulla
        // (la validazione completa avverrà al submit o al blur)
        if (!element.classList.contains(this.options.invalidClass)) {
            return;
        }
        
        // Se è nascosto, rimuovi errore
        if (this.isFieldHidden(fieldId)) {
            this._setFieldValid(element);
            this.errors.delete(fieldId);
            return;
        }
        
        // Se ha requiredIf, verifica se la condizione è ancora vera
        if (config.requiredIf) {
            try {
                const result = this.parser.analyze(config.requiredIf, true);
                if (result.error) return;
                
                const requiredNow = result.result === true;
                const value = this._getFieldValue(element);
                const isEmpty = value === '' || value === null || value === undefined ||
                    (typeof value === 'number' && isNaN(value));
                
                // Se non è più required o non è più vuoto, rimuovi errore
                if (!requiredNow || !isEmpty) {
                    this._setFieldValid(element);
                    this.errors.delete(fieldId);
                }
            } catch (e) {
                console.warn(`MilkForm: Errore refresh requiredIf "${fieldId}":`, e.message);
            }
        }

        // Se ha validateExpr, verifica se ora è tornato valido (es. dipendenze cambiate)
        if (config.validateExpr && element.classList.contains(this.options.invalidClass)) {
            try {
                const result = this.parser.analyze(config.validateExpr, true);
                if (result.error) return;

                if (result.result === true) {
                    this._setFieldValid(element);
                    this.errors.delete(fieldId);
                }
            } catch (e) {
                console.warn(`MilkForm: Errore refresh validateExpr "${fieldId}":`, e.message);
            }
        }
    }

    /**
     * Log del grafo delle dipendenze (per debug)
     * @private
     */
    _logDependencyGraph() {
        console.group('[MilkForm] Dependency Graph');
        
        console.log('Direct dependencies (field -> params):');
        this.dependencyGraph.forEacmh((deps, fieldId) => {
            console.log(`  ${fieldId} -> [${Array.from(deps).join(', ')}]`);
        });
        
        console.log('\nReverse dependencies (param -> fields):');
        this.reverseDependencies.forEach((fields, param) => {
            console.log(`  ${param} -> [${Array.from(fields).join(', ')}]`);
        });
        
        console.log('\nReactive fields:', this.allReactiveFields.size);
        this.allReactiveFields.forEach((info, fieldId) => {
            console.log(`  ${fieldId}: types=[${Array.from(info.types).join(', ')}]`);
        });
        
        console.groupEnd();
    }

    // ============================================================
    // FINE NUOVO SISTEMA DIPENDENZE
    // ============================================================

    /**
     * Scansiona tutti i campi con attributi data-milk-*
     * @private
     */
    _scanFields() {
        // Campi validabili (include anche required-if)
        this.form.querySelectorAll('[data-milk-validate], [data-milk-validate-expr], [data-milk-required-if]').forEach(field => {
            const fieldId = field.id || field.name;
            if (fieldId) {
                this.fields.set(fieldId, {
                    element: field,
                    config: this._parseFieldConfig(field)
                });
            }
        });
        
        // Campi calcolati
        this.form.querySelectorAll('[data-milk-expr]').forEach(field => {
            const fieldId = field.id || field.name;
            if (fieldId) {
                this.calculatedFields.set(fieldId, {
                    element: field,
                    config: this._parseFieldConfig(field)
                });
            }
        });

        // Campi con default "soft"
        this.form.querySelectorAll('[data-milk-default-expr]').forEach(field => {
            const fieldId = field.id || field.name;
            if (fieldId) {
                this.defaultFields.set(fieldId, {
                    element: field,
                    config: this._parseFieldConfig(field)
                });
            }
        });
        
        // Elementi con visibilità condizionale
        this.form.querySelectorAll('[data-milk-show]').forEach(element => {
            const id = element.id || element.dataset.milkId || `milk-cond-${this.conditionalElements.size}`;
            this.conditionalElements.set(id, {
                element: element,
                expr: element.dataset.milkShow
            });
        });
    }

    /**
     * Parsing configurazione campo
     * @private
     */
    _parseFieldConfig(field) {
        return {
            expr: field.dataset.milkExpr || null,
            defaultExpr: field.dataset.milkDefaultExpr || null,
            show: field.dataset.milkShow || null,
            validateExpr: field.dataset.milkValidateExpr || null,
            validate: field.dataset.milkValidate || null,
            requiredIf: field.dataset.milkRequiredIf || null,
            message: field.dataset.milkMessage || null,
            params: this._parseParams(field.dataset.milkParams),
            validateOn: field.dataset.milkValidateOn || 'change',
            group: field.dataset.milkGroup || null
        };
    }

    /**
     * Parse parametri JSON
     * @private
     */
    _parseParams(paramsStr) {
        if (!paramsStr) return {};
        try {
            return JSON.parse(paramsStr);
        } catch (e) {
            console.warn('MilkForm: Errore parsing params JSON', e);
            return {};
        }
    }

    /**
     * Aggiorna i parametri del parser con tutti i valori del form
     * @private
     */
    _updateParserParameters() {
        const formElements = this.form.querySelectorAll('input, select, textarea');

        formElements.forEach(el => {
            const keys = this._getFieldKeys(el);
            if (!keys.length) {
                return;
            }
            const value = this._getFieldValue(el);
            keys.forEach(key => {
                this.parser.setParameter(key, value);
            });
        });
    }

    /**
     * Bind listener globale sul form per ricalcolo
     * MODIFICATO: Usa ricalcolo selettivo invece di _recalculateAll
     * @private
     */
    _bindGlobalFormListener() {
        if (!this.options.recalculateOnAnyChange) return;
        
        this._formChangeHandler = (e) => {
            const target = e.target;
            if (target.matches('input, select, textarea')) {
                const fieldId = target.id || target.name;

                if (target.dataset && target.dataset.milkDefaultExpr !== undefined) {
                    target.dataset.milkDefaultDirty = '1';
                    if (target.dataset.milkDefaultApplied !== undefined) {
                        delete target.dataset.milkDefaultApplied;
                    }
                }
                
                // NUOVO: Ricalcolo selettivo basato su dipendenze
                this._recalculateDependents(fieldId);
                
                if (this.callbacks.onChange) {
                    this.callbacks.onChange(target, this._getFieldValue(target));
                }
            }
        };
        
        this.form.addEventListener('input', this._formChangeHandler);
        this.form.addEventListener('change', this._formChangeHandler);
    }

    /**
     * Bind eventi di validazione sui singoli campi
     * @private
     */
    _bindValidationEvents() {
        this.fields.forEach((fieldData, fieldId) => {
            const { element, config } = fieldData;
            const eventType = config.validateOn;

            const handler = () => this.validateField(element);
            element.addEventListener(eventType, handler);

            this._addBoundListener(element, eventType, handler);

            const inputHandler = () => {
                const formWasValidated = this.form.classList.contains('was-validated');
                if (formWasValidated) {
                    this.validateField(element);
                }
            };

            element.addEventListener('input', inputHandler);
            this._addBoundListener(element, 'input', inputHandler);
        });
    }

    /**
     * Aggiunge un listener alla mappa per cleanup
     * @private
     */
    _addBoundListener(element, event, handler) {
        if (!this._boundListeners.has(element)) {
            this._boundListeners.set(element, []);
        }
        this._boundListeners.get(element).push({ event, handler });
    }

    /**
     * Ricalcola TUTTI i campi calcolati e la visibilità
     * Usato solo per: init, submit, chiamata manuale recalculate()
     * @private
     */
    _recalculateAll() {
        if (this._isRecalculating) return;
        this._isRecalculating = true;
        
        try {
            // Aggiorna tutti i parametri
            this._updateParserParameters();
            
            // 1. Calcola visibilità
            this._updateAllVisibility();
            
            // 2. Calcola campi con iterazioni per dipendenze a cascata
            const maxIterations = 5;
            let iteration = 0;
            let hasChanges = true;
            
            while (hasChanges && iteration < maxIterations) {
                hasChanges = false;
                iteration++;
                
                this.calculatedFields.forEach((data, fieldId) => {
                    const oldValue = data.element.value;
                    this._calculateField(fieldId);
                    const newValue = data.element.value;
                    
                    if (oldValue !== newValue) {
                        hasChanges = true;
                    }
                });
                
                if (hasChanges) {
                    this._updateParserParameters();
                }
            }
            
            if (iteration >= maxIterations) {
                console.warn('MilkForm: Raggiunto limite iterazioni ricalcolo. Possibile dipendenza circolare.');
            }

            this._updateParserParameters();
            this.defaultFields.forEach((_, fieldId) => {
                this._applyDefaultExpr(fieldId);
            });
            this._updateParserParameters();
            this._refreshRequiredIfInvalids();
        } finally {
            this._isRecalculating = false;
        }
    }

    /**
     * Aggiorna visibilità di tutti gli elementi condizionali
     * @private
     */
    _updateAllVisibility() {
        this.conditionalElements.forEach((data, id) => {
            this._updateElementVisibility(id);
        });
    }

    /**
     * Aggiorna visibilità di un singolo elemento
     * @private
     */
    _updateElementVisibility(id) {
        const data = this.conditionalElements.get(id);
        if (!data) return;
        
        const { element, expr } = data;
        
        try {
            const result = this.parser.analyze(expr, true);
            const shouldShow = result.result === true;
            
            this._setElementVisibility(element, shouldShow);
            
            if (this.callbacks.onVisibilityChange) {
                this.callbacks.onVisibilityChange(element, shouldShow, expr);
            }
        } catch (e) {
            console.warn(`MilkForm: Errore valutazione visibilità "${id}":`, e.message);
        }
    }

    /**
     * Imposta visibilità di un elemento
     * @private
     */
    _setElementVisibility(element, visible) {
        if (visible) {
            element.classList.remove(this.options.hideClass);
            if (this.options.hideStyle) {
                element.style.display = '';
            }
            this._setFieldsDisabledState(element, false);
        } else {
            element.classList.add(this.options.hideClass);
            if (this.options.hideStyle) {
                element.style.display = 'none';
            }
            if (this.options.disableHiddenFields) {
                this._setFieldsDisabledState(element, true);
            }
        }
        
        this._updateHiddenFieldsSet(element, !visible);
    }

    /**
     * Abilita/disabilita i campi dentro un elemento
     * @private
     */
    _setFieldsDisabledState(element, disabled) {
        if (element.matches('input, select, textarea')) {
            element.disabled = disabled;
            return;
        }
        
        element.querySelectorAll('input, select, textarea').forEach(field => {
            field.disabled = disabled;
        });
    }

    /**
     * Aggiorna il set dei campi nascosti
     * @private
     */
    _updateHiddenFieldsSet(element, isHidden) {
        const getFieldIds = (el) => {
            const ids = [];
            if (el.matches && el.matches('input, select, textarea')) {
                const id = el.id || el.name;
                if (id) ids.push(id);
            }
            el.querySelectorAll && el.querySelectorAll('input, select, textarea').forEach(field => {
                const id = field.id || field.name;
                if (id) ids.push(id);
            });
            return ids;
        };
        
        const fieldIds = getFieldIds(element);
        
        fieldIds.forEach(id => {
            if (isHidden) {
                this.hiddenFields.add(id);
            } else {
                this.hiddenFields.delete(id);
            }
        });
    }

    /**
     * Verifica se un campo è nascosto
     * @param {string} fieldId - ID del campo
     * @returns {boolean}
     */
    isFieldHidden(fieldId) {
        return this.hiddenFields.has(fieldId);
    }

    /**
     * Calcola il valore di un campo (versione legacy per _recalculateAll)
     * @private
     */
    _calculateField(fieldId) {
        this._calculateFieldById(fieldId);
    }

    /**
     * Remove invalid state for required-if fields when condition is no longer true
     * @private
     */
    _refreshRequiredIfInvalids() {
        this.fields.forEach((fieldData, fieldId) => {
            const { element, config } = fieldData;
            if (!config.requiredIf) {
                return;
            }
            if (!element.classList.contains(this.options.invalidClass)) {
                return;
            }

            if (this.isFieldHidden(fieldId)) {
                this._setFieldValid(element);
                this.errors.delete(fieldId);
                return;
            }

            try {
                const result = this.parser.analyze(config.requiredIf, true);
                if (result.error) {
                    return;
                }
                const requiredNow = result.result === true;
                const value = this._getFieldValue(element);
                const isEmpty = value === '' || value === null || value === undefined ||
                    (typeof value === 'number' && isNaN(value));

                if (!requiredNow || !isEmpty) {
                    this._setFieldValid(element);
                    this.errors.delete(fieldId);
                }
            } catch (e) {
                console.warn(`MilkForm: Errore refresh requiredIf "${fieldId}":`, e.message);
            }
        });
    }

    /**
     * Ottiene il valore di un campo
     * @private
     */
    _getFieldValue(field) {
        if (field.type === 'checkbox') {
            return field.checked ? (field.value || 1) : 0;
        }
        if (field.type === 'radio') {
            const checked = this.form.querySelector(`input[name="${field.name}"]:checked`);
            return checked ? checked.value : null;
        }
        
        const value = field.value;
        
        if (value !== '' && !isNaN(value) && !isNaN(parseFloat(value))) {
            return parseFloat(value);
        }
        
        return value;
    }

    /**
     * Valida un singolo campo
     * @param {HTMLElement|string} field - Campo o ID
     * @returns {boolean}
     */
    validateField(field) {
        if (typeof field === 'string') {
            field = document.getElementById(field) || this.form.querySelector(`[name="${field}"]`);
        }

        if (!field) return true;

        const fieldId = field.id || field.name;
        const fieldData = this.fields.get(fieldId);

        if (!fieldData) {
            return true;
        }

        if (this.isFieldHidden(fieldId)) {
            this._setFieldValid(field);
            this.errors.delete(fieldId);
            return true;
        }

        const { config } = fieldData;
        const value = this._getFieldValue(field);

        let isValid = true;
        let message = config.message || 'Campo non valido';
        
        // 1. Prima controlla requiredIf
        if (config.requiredIf) {
            try {
                this._updateParserParameters();
                const result = this.parser.analyze(config.requiredIf, true);
                
                if (result.error) {
                    console.warn(`MilkForm: Errore requiredIf "${fieldId}":`, result.error);
                } else if (result.result === true) {
                    const isEmpty = value === '' || value === null || value === undefined || 
                                   (typeof value === 'number' && isNaN(value));
                    if (isEmpty) {
                        isValid = false;
                        message = config.message || 'Campo obbligatorio';
                    }
                }
            } catch (e) {
                console.warn(`MilkForm: Errore requiredIf "${fieldId}":`, e.message);
            }
        }
        
        // 2. Se già invalido per requiredIf, salta altre validazioni
        if (!isValid) {
            this._setFieldInvalid(field, message);
            this.errors.set(fieldId, message);
            
            if (this.callbacks.onFieldValidate) {
                this.callbacks.onFieldValidate(field, isValid, message);
            }
            
            return isValid;
        }
        
        // 3. Validazione tramite espressione
        if (config.validateExpr) {
            try {
                this._updateParserParameters();
                const result = this.parser.analyze(config.validateExpr, true);

                if (result.error) {
                    console.warn(`MilkForm: Errore validazione "${fieldId}":`, result.error);
                    isValid = false;
                    message = 'Errore nella validazione';
                } else {
                    isValid = result.result === true;
                }
            } catch (e) {
                console.warn(`MilkForm: Errore validazione "${fieldId}":`, e.message);
                isValid = false;
                message = 'Errore nella validazione';
            }
        }
        // Validazione tramite validatore registrato
        else if (config.validate) {
            const validator = MilkForm.validators[config.validate];
            
            if (!validator) {
                console.warn(`MilkForm: Validatore "${config.validate}" non trovato`);
                return true;
            }
            
            const result = validator(field, value, config.params, this);
            isValid = result === true || (result && result.valid);
            message = config.message || (result && result.message) || 'Campo non valido';
        }
        
        // Aggiorna UI
        if (isValid) {
            this._setFieldValid(field);
            this.errors.delete(fieldId);
        } else {
            this._setFieldInvalid(field, message);
            this.errors.set(fieldId, message);
        }
        
        if (this.callbacks.onFieldValidate) {
            this.callbacks.onFieldValidate(field, isValid, message);
        }

        return isValid;
    }

    /**
     * Valida tutto il form
     * @param {string} [group] - Gruppo opzionale
     * @returns {boolean}
     */
    validate(group = null) {
        // Prima di validare, fai un ricalcolo completo
        this._recalculateAll();
        
        this.errors.clear();
        let isFormValid = true;
        let firstInvalidField = null;

        this.form.classList.add('was-validated');

        this.fields.forEach((fieldData, fieldId) => {
            const { element, config } = fieldData;

            if (this.isFieldHidden(fieldId)) {
                this._setFieldValid(element);
                return;
            }

            if (group && config.group !== group) return;

            const isFieldValid = this.validateField(element);

            if (!isFieldValid) {
                isFormValid = false;
                if (!firstInvalidField) {
                    firstInvalidField = element;
                }
            }
        });
        
        if (!isFormValid && firstInvalidField) {
            if (this.options.scrollToFirstError) {
                firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            if (this.options.focusFirstError) {
                setTimeout(() => firstInvalidField.focus(), 300);
            }
        }
        
        if (this.callbacks.onValidate) {
            this.callbacks.onValidate(isFormValid, this.getErrors());
        }
        
        if (isFormValid && this.callbacks.onSuccess) {
            this.callbacks.onSuccess();
        }
        
        if (!isFormValid && this.callbacks.onError) {
            this.callbacks.onError(this.getErrors());
        }
        
        return isFormValid;
    }

    /**
     * Imposta campo valido
     * @private
     */
    _setFieldValid(field) {
        field.classList.remove(this.options.invalidClass);

        if (this.options.showValidFeedback) {
            field.classList.add(this.options.validClass);
        }

        field.setCustomValidity('');

        const feedback = this._getFeedbackElement(field);
        if (feedback) {
            feedback.textContent = '';
            feedback.style.display = 'none';
        }
    }

    /**
     * Imposta campo non valido
     * @private
     */
    _setFieldInvalid(field, message) {
        field.classList.remove(this.options.validClass);
        field.classList.add(this.options.invalidClass);

        field.setCustomValidity(message);

        let feedback = this._getFeedbackElement(field);
        if (!feedback) {
            feedback = this._createFeedbackElement(field);
        }

        feedback.textContent = message;
        feedback.style.display = 'block';
    }

    /**
     * Trova elemento feedback
     * @private
     */
    _getFeedbackElement(field) {
        let feedback = field.parentElement.querySelector(`.${this.options.feedbackClass}`);
        if (!feedback) {
            feedback = field.nextElementSibling;
            if (feedback && !feedback.classList.contains(this.options.feedbackClass)) {
                feedback = null;
            }
        }
        return feedback;
    }

    /**
     * Crea elemento feedback
     * @private
     */
    _createFeedbackElement(field) {
        const feedback = document.createElement('div');
        feedback.className = this.options.feedbackClass;
        field.parentElement.appendChild(feedback);
        return feedback;
    }

    /**
     * Ottieni tutti gli errori
     * @returns {Object}
     */
    getErrors() {
        return Object.fromEntries(this.errors);
    }

    /**
     * Resetta validazione
     */
    reset() {
        this.errors.clear();

        this.form.classList.remove('was-validated');

        this.fields.forEach(({ element }) => {
            element.classList.remove(this.options.invalidClass, this.options.validClass);

            const feedback = this._getFeedbackElement(element);
            if (feedback) {
                feedback.textContent = '';
                feedback.style.display = 'none';
            }
        });
    }

    /**
     * Forza ricalcolo completo di tutto
     */
    recalculate() {
        this._recalculateAll();
    }

    /**
     * Ottiene un campo dal form
     */
    getField(fieldId) {
        return document.getElementById(fieldId) ||
            document.getElementById(`formdata${fieldId}`) ||
            this.form.querySelector(`[name="${fieldId}"]`) ||
            this.form.querySelector(`[name="data[${fieldId}]"]`);
    }

    /**
     * Ottiene il valore di un campo
     */
    getValue(fieldId) {
        const field = this.getField(fieldId);
        return field ? this._getFieldValue(field) : null;
    }

    /**
     * Imposta il valore di un campo
     */
    setValue(fieldId, value) {
        const field = this.getField(fieldId);
        if (field) {
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    /**
     * Derive parameter keys for ExpressionParser from field id/name.
     * @private
     */
    _getFieldKeys(field) {
        const keys = new Set();
        const id = field.id || '';
        const name = field.name || '';

        if (id) {
            keys.add(id);
            if (id.startsWith('formdata')) {
                keys.add(id.replace(/^formdata/, ''));
            }
        }

        if (name) {
            keys.add(name);
            const match = name.match(/^data\[([^\]]+)\]/);
            if (match && match[1]) {
                keys.add(match[1]);
            }
        }

        return Array.from(keys);
    }

    /**
     * Esegue un'espressione nel contesto del form
     */
    evaluate(expression) {
        this._updateParserParameters();
        const result = this.parser.analyze(expression, true);
        
        if (result.error) {
            throw new Error(result.error);
        }
        
        return result.result;
    }

    /**
     * Aggiunge un campo dinamicamente
     */
    addField(field) {
        const config = this._parseFieldConfig(field);
        const fieldId = field.id || field.name;
        
        if (config.validate || config.validateExpr || config.requiredIf) {
            this.fields.set(fieldId, { element: field, config });
            
            field.addEventListener(config.validateOn, () => {
                this.validateField(field);
            });
        }
        
        if (config.expr) {
            this.calculatedFields.set(fieldId, { element: field, config });
        }
        
        if (config.show) {
            this.conditionalElements.set(fieldId, { element: field, expr: config.show });
        }
        
        // Ricostruisci il grafo delle dipendenze
        this._buildDependencyGraph();
        
        this._recalculateAll();
    }

    /**
     * Rimuove un campo
     */
    removeField(fieldId) {
        this.fields.delete(fieldId);
        this.calculatedFields.delete(fieldId);
        this.conditionalElements.delete(fieldId);
        this.errors.delete(fieldId);
        this.hiddenFields.delete(fieldId);
        
        // Ricostruisci il grafo delle dipendenze
        this._buildDependencyGraph();
    }

    /**
     * Ottiene le dipendenze di un campo
     * @param {string} fieldId - ID del campo
     * @returns {Array<string>} - Lista dei parametri da cui dipende
     */
    getDependencies(fieldId) {
        const deps = this.dependencyGraph.get(fieldId);
        return deps ? Array.from(deps) : [];
    }

    /**
     * Ottiene i campi che dipendono da un parametro
     * @param {string} paramName - Nome del parametro
     * @returns {Array<string>} - Lista dei fieldId dipendenti
     */
    getDependents(paramName) {
        const deps = this.reverseDependencies.get(paramName);
        return deps ? Array.from(deps) : [];
    }

    /**
     * Verifica se esiste una dipendenza circolare
     * @returns {Array<string>|null} - Ciclo trovato o null
     */
    detectCircularDependency() {
        const visited = new Set();
        const recursionStack = new Set();
        
        const dfs = (fieldId, path) => {
            if (recursionStack.has(fieldId)) {
                return [...path, fieldId]; // Ciclo trovato
            }
            
            if (visited.has(fieldId)) {
                return null;
            }
            
            visited.add(fieldId);
            recursionStack.add(fieldId);
            
            // Trova campi che dipendono da questo
            const variants = this._getParameterVariants(fieldId);
            for (const variant of variants) {
                const deps = this.reverseDependencies.get(variant);
                if (deps) {
                    for (const depFieldId of deps) {
                        const cycle = dfs(depFieldId, [...path, fieldId]);
                        if (cycle) return cycle;
                    }
                }
            }
            
            recursionStack.delete(fieldId);
            return null;
        };
        
        for (const fieldId of this.allReactiveFields.keys()) {
            const cycle = dfs(fieldId, []);
            if (cycle) return cycle;
        }
        
        return null;
    }

    /**
     * Distrugge l'istanza
     */
    destroy() {
        this._boundListeners.forEach((listeners, element) => {
            listeners.forEach(({ event, handler }) => {
                element.removeEventListener(event, handler);
            });
        });
        this._boundListeners.clear();
        
        if (this._formChangeHandler) {
            this.form.removeEventListener('input', this._formChangeHandler);
            this.form.removeEventListener('change', this._formChangeHandler);
        }
        
        this.fields.clear();
        this.calculatedFields.clear();
        this.conditionalElements.clear();
        this.errors.clear();
        this.hiddenFields.clear();
        this.dependencyGraph.clear();
        this.reverseDependencies.clear();
        this.allReactiveFields.clear();
        
        this.form.removeAttribute('novalidate');
        MilkForm.instances.delete(this.form);
        
        this.form = null;
        this.parser = null;
        this.callbacks = null;
    }
}


// ============================================================================
// VALIDATORI BUILT-IN
// ============================================================================

MilkForm.registerValidator('range', (field, value, params) => {
    const { min, max } = params;
    const numValue = parseFloat(value);
    
    if (isNaN(numValue)) {
        return { valid: false, message: 'Inserire un numero valido' };
    }
    
    if (min !== undefined && numValue < min) {
        return { valid: false, message: `Il valore deve essere almeno ${min}` };
    }
    
    if (max !== undefined && numValue > max) {
        return { valid: false, message: `Il valore deve essere al massimo ${max}` };
    }
    
    return true;
});

MilkForm.registerValidator('pattern', (field, value, params) => {
    if (!value) return true;
    
    const { regex, flags = '' } = params;
    if (!regex) return true;
    
    const pattern = new RegExp(regex, flags);
    return {
        valid: pattern.test(value),
        message: params.message || 'Formato non valido'
    };
});

MilkForm.registerValidator('length', (field, value, params) => {
    if (!value) return true;
    
    const { min, max, exact } = params;
    const len = value.length;
    
    if (exact !== undefined && len !== exact) {
        return { valid: false, message: `Deve contenere esattamente ${exact} caratteri` };
    }
    
    if (min !== undefined && len < min) {
        return { valid: false, message: `Deve contenere almeno ${min} caratteri` };
    }
    
    if (max !== undefined && len > max) {
        return { valid: false, message: `Deve contenere al massimo ${max} caratteri` };
    }
    
    return true;
});
/*
MilkForm.registerValidator('codiceFiscale', (field, value) => {
    if (!value) return true;
    
    const cfRegex = /^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/i;
    return {
        valid: cfRegex.test(value),
        message: 'Codice fiscale non valido'
    };
});

MilkForm.registerValidator('partitaIva', (field, value) => {
    if (!value) return true;
    
    if (!/^[0-9]{11}$/.test(value)) {
        return { valid: false, message: 'La partita IVA deve contenere 11 cifre' };
    }
    
    let sum = 0;
    for (let i = 0; i < 11; i++) {
        let n = parseInt(value[i]);
        if (i % 2 === 1) {
            n *= 2;
            if (n > 9) n -= 9;
        }
        sum += n;
    }
    
    return {
        valid: sum % 10 === 0,
        message: 'Partita IVA non valida'
    };
});

MilkForm.registerValidator('iban', (field, value) => {
    if (!value) return true;
    
    const iban = value.replace(/\s/g, '').toUpperCase();
    
    if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/.test(iban)) {
        return { valid: false, message: 'Formato IBAN non valido' };
    }
    
    const rearranged = iban.slice(4) + iban.slice(0, 4);
    const numericIban = rearranged.replace(/[A-Z]/g, char => char.charCodeAt(0) - 55);
    const remainder = BigInt(numericIban) % 97n;
    
    return {
        valid: remainder === 1n,
        message: 'IBAN non valido'
    };
});
*/
MilkForm.registerValidator('required', (field, value) => {
    const isEmpty = value === '' || value === null || value === undefined;
    return {
        valid: !isEmpty,
        message: 'Campo obbligatorio'
    };
});

MilkForm.registerValidator('requiredIf', (field, value, params, formInstance) => {
    if (!params.condition) {
        console.warn('MilkForm: requiredIf richiede params.condition');
        return true;
    }
    
    try {
        formInstance._updateParserParameters();
        const result = formInstance.parser.analyze(params.condition, true);
        
        if (result.error) {
            console.warn('MilkForm: Errore valutazione requiredIf:', result.error);
            return true;
        }
        
        if (result.result === true) {
            const isEmpty = value === '' || value === null || value === undefined;
            return {
                valid: !isEmpty,
                message: params.message || 'Campo obbligatorio'
            };
        }
        
        return true;
    } catch (e) {
        console.warn('MilkForm: Errore requiredIf:', e.message);
        return true;
    }
});



// ============================================================================
// EXPORT
// ============================================================================

if (typeof module !== 'undefined' && module.exports) {
    module.exports =  MilkForm;
}

if (typeof window !== 'undefined') {
    window.MilkForm = MilkForm;
}
