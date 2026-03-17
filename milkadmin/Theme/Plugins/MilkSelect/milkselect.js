class MilkSelect {
    constructor(element, options = [], valueMap = null, displayValue = null, placeholder = null) {
    this.hiddenInput = element;
    this.isMultiple = element.dataset.selectType === "multiple";
    this.options = [];
    this.optionItems = [];
    this.valueMap = {};
    this.saveByValue = false;
    this.selectedValues = []; // Display values (what user sees)
    this.selectedKeys = []; // IDs/keys (what gets saved)
    this.activeIndex = -1;
    this.currentInput = null;
    this.initialDisplayValue = displayValue;
    this.isFirstOpen = true; // Track if dropdown was opened before
    this.placeholder = placeholder || (this.isMultiple ? 'Aggiungi valore...' : 'Cerca o seleziona...');
    this.isFloating = element.dataset.floating === '1';
    this.apiUrl = element.dataset.apiUrl || null;
    this.allowCreate = element.dataset.create === '1';
    this.fetchTimeout = null;
    this.isLoading = false;
    this.isEditing = false; // Track if user has modified the input text
    this.isReadonly = element.dataset.readonly === '1';
    this.isDestroyed = false;
    this.setOptions(options, valueMap);
    this.init();
  }

  /**
   * Static method to initialize MilkSelect from JSON config
   * @param {string} elementId - The ID of the hidden input element
   * @returns {MilkSelect|null} - The created MilkSelect instance or null if already initialized
   */
  static initFromConfig(elementId) {
    const element = document.getElementById(elementId);
    if (!element) {
      console.warn(`MilkSelect: Element with id "${elementId}" not found`);
      return null;
    }

    // Check if already initialized
    if (element.dataset.milkselectInitialized === 'true') {
      return element.milkSelectInstance || null;
    }

    // Get config ID from data attribute
    const configId = element.dataset.milkselectConfig;
    if (!configId) {
      console.warn(`MilkSelect: No config ID found for element "${elementId}"`);
      return null;
    }

    // Load config from JSON script
    const configElement = document.getElementById(configId);
    if (!configElement) {
      console.warn(`MilkSelect: Config element "${configId}" not found`);
      return null;
    }

    let config;
    try {
      config = JSON.parse(configElement.textContent);
    } catch (e) {
      console.error(`MilkSelect: Failed to parse config for "${elementId}"`, e);
      return null;
    }

    // Build valueMap from keys if present
    let valueMap = null;
    if (config.keys && Array.isArray(config.keys)) {
      valueMap = {};
      config.options.forEach((opt, idx) => {
        valueMap[opt] = config.keys[idx];
      });
    }

    // Create instance
    const instance = new MilkSelect(
      element,
      config.options || [],
      valueMap,
      config.displayValue || null,
      config.placeholder || null
    );

    // Mark as initialized
    element.dataset.milkselectInitialized = 'true';
    element.milkSelectInstance = instance;

    return instance;
  }

  /**
   * Static method to initialize all MilkSelect elements in a container
   * Useful for dynamically loaded content
   * @param {HTMLElement|string} container - Container element or selector (default: document)
   */
  static initAll(container = document) {
    const containerElement = typeof container === 'string'
      ? document.querySelector(container)
      : container;

    if (!containerElement) {
      console.warn('MilkSelect: Container not found');
      return;
    }

    // Find all hidden inputs with milkselect config
    const elements = containerElement.querySelectorAll('input[type="hidden"][data-milkselect-config]');
    elements.forEach(element => {
      if (element.dataset.milkselectInitialized !== 'true') {
        MilkSelect.initFromConfig(element.id);
      }
    });
  }

  /**
   * Static helper to destroy an initialized MilkSelect instance by hidden input id
   * @param {string} elementId
   * @returns {boolean}
   */
  static destroy(elementId) {
    const element = document.getElementById(elementId);
    if (!element || !element.milkSelectInstance) {
      return false;
    }
    element.milkSelectInstance.destroy();
    return true;
  }

  /**
   * Alias of static destroy()
   * @param {string} elementId
   * @returns {boolean}
   */
  static remove(elementId) {
    return MilkSelect.destroy(elementId);
  }

  init() {
    this.createDOM();
    this.loadExistingValues();
    this.addEventListeners();
    this.isMultiple ? this.createNewInput() : this.createSingleInput();
    this.applyInitialValidationState();
  }

  normalizeOptionItem(option, legacyValueMap = null) {
    if (option === null || option === undefined) {
      return null;
    }

    if (typeof option === "object" && !Array.isArray(option)) {
      const rawText = option.text ?? option.label ?? option.value;
      if (rawText === undefined || rawText === null || rawText === "") {
        return null;
      }

      const text = String(rawText);
      const rawValue = option.value ?? text;
      const value = rawValue === null || rawValue === undefined ? text : String(rawValue);
      const group = option.group !== undefined && option.group !== null && option.group !== ""
        ? String(option.group)
        : null;

      return { text, value, group };
    }

    const text = String(option);
    const mappedValue = legacyValueMap && legacyValueMap[text] !== undefined
      ? legacyValueMap[text]
      : text;

    return {
      text,
      value: mappedValue === null || mappedValue === undefined ? text : String(mappedValue),
      group: null
    };
  }

  applyNormalizedOptions(normalized) {
    this.optionItems = normalized;
    this.options = normalized.map(item => item.text);
    this.valueMap = {};
    normalized.forEach(item => {
      if (this.valueMap[item.text] === undefined) {
        this.valueMap[item.text] = item.value;
      }
    });
    this.saveByValue = normalized.some(item => item.value !== item.text);
  }

  setOptions(options, legacyValueMap = null) {
    const sourceOptions = Array.isArray(options) ? options : [];
    const normalized = [];

    sourceOptions.forEach((option) => {
      const normalizedItem = this.normalizeOptionItem(option, legacyValueMap);
      if (normalizedItem) {
        normalized.push(normalizedItem);
      }
    });

    this.applyNormalizedOptions(normalized);
  }

  findOptionByText(text) {
    return this.optionItems.find(item => item.text === text) || null;
  }

  findOptionByValue(value) {
    const normalizedValue = String(value);
    return this.optionItems.find(item => item.value === normalizedValue) || null;
  }

  findSelectedChipByKey(key) {
    if (!this.wrapper) return null;
    const chips = this.wrapper.querySelectorAll(".cs-autocomplete-selected-item");
    const targetKey = String(key);
    return Array.from(chips).find(chip => chip.dataset.key === targetKey) || null;
  }

  dispatchContainerEvent(eventName, detail = null) {
    if (!this.container) return;
    this.container.dispatchEvent(new CustomEvent(eventName, { detail }));
  }

  refreshDropdownAfterOptionsMutation() {
    if (!this.dropdown || !this.dropdown.classList.contains("show")) {
      return;
    }

    const hasTypedValue = !!(this.currentInput && this.currentInput.value && this.currentInput.value.trim().length > 0);
    const searchTerm = hasTypedValue ? this.currentInput.value.toLowerCase() : "";
    this.filterOptions(searchTerm, !hasTypedValue);
    this.showDropdownIfNeeded();
  }

  syncSelectedValuesWithOptions() {
    if (this.isMultiple) {
      const validIndexes = [];
      this.selectedKeys.forEach((key, idx) => {
        const normalizedKey = String(key);
        const normalizedText = String(this.selectedValues[idx] ?? "");
        const option = this.findOptionByValue(normalizedKey)
          || this.findOptionByText(normalizedText);
        if (option) {
          validIndexes.push(idx);
        }
      });

      if (validIndexes.length === this.selectedKeys.length) {
        return;
      }

      const validIndexesSet = new Set(validIndexes);
      const keysToRemove = this.selectedKeys.filter((_, idx) => !validIndexesSet.has(idx));
      keysToRemove.forEach((keyToRemove) => {
        const chip = this.findSelectedChipByKey(keyToRemove);
        if (chip) {
          this.removeMultipleItem(String(keyToRemove), chip);
        } else {
          const index = this.selectedKeys.indexOf(String(keyToRemove));
          if (index > -1) {
            this.selectedKeys.splice(index, 1);
            this.selectedValues.splice(index, 1);
          }
        }
      });
      this.updateHiddenInput();
      return;
    }

    if (this.selectedKeys.length === 0 && this.selectedValues.length === 0) {
      return;
    }

    const currentKey = String(this.selectedKeys[0] ?? "");
    const currentText = String(this.selectedValues[0] ?? "");
    const matchedOption = this.findOptionByValue(currentKey) || this.findOptionByText(currentText);

    if (!matchedOption) {
      this.selectedKeys = [];
      this.selectedValues = [];
      if (this.currentInput) {
        this.currentInput.value = "";
        this.currentInput.classList.remove('has-value');
      }
      this.removeClearButton();
      this.updateHiddenInput();
      return;
    }

    if (this.selectedValues[0] !== matchedOption.text) {
      this.selectedValues = [matchedOption.text];
      this.selectedKeys = [matchedOption.value];
      if (this.currentInput) {
        this.currentInput.value = matchedOption.text;
        this.currentInput.classList.add('has-value');
      }
      this.updateHiddenInput();
    }
  }

  addOption(option, selectAfterAdd = false) {
    const normalizedItem = this.normalizeOptionItem(option);
    if (!normalizedItem) {
      return false;
    }

    const updatedItems = [...this.optionItems];
    const existingIndex = updatedItems.findIndex(item => item.value === normalizedItem.value);
    const isReplacement = existingIndex >= 0;

    if (isReplacement) {
      updatedItems[existingIndex] = normalizedItem;
    } else {
      updatedItems.push(normalizedItem);
    }

    this.applyNormalizedOptions(updatedItems);
    this.syncSelectedValuesWithOptions();

    if (selectAfterAdd) {
      this.selectOption(normalizedItem);
    } else {
      this.refreshDropdownAfterOptionsMutation();
    }

    this.dispatchContainerEvent("optionAdded", {
      option: normalizedItem,
      replaced: isReplacement
    });
    return true;
  }

  removeOption(valueOrText) {
    const lookupValue = String(valueOrText);
    const shouldRemove = (item) => item.value === lookupValue || item.text === lookupValue;

    const removedItems = this.optionItems.filter(shouldRemove);
    if (removedItems.length === 0) {
      return false;
    }

    const updatedItems = this.optionItems.filter(item => !shouldRemove(item));
    this.applyNormalizedOptions(updatedItems);
    this.syncSelectedValuesWithOptions();
    this.refreshDropdownAfterOptionsMutation();

    this.dispatchContainerEvent("optionRemoved", {
      lookup: lookupValue,
      removed: removedItems
    });
    return true;
  }

  createDOM() {
    this.container = document.createElement("div");
    this.container.className = "cs-autocomplete-container";

    this.wrapper = document.createElement("div");
    this.wrapper.className = this.isMultiple
      ? "cs-autocomplete-wrapper-multiple"
      : "cs-autocomplete-wrapper-single";

    // Copy validation classes from hidden input to wrapper
    if (this.hiddenInput.classList.contains('is-invalid')) {
      this.wrapper.classList.add('is-invalid');
    }
    if (this.isReadonly) {
      this.wrapper.classList.add('cs-autocomplete-readonly');
    }

    // Store if field is required
    this.isRequired = this.hiddenInput.hasAttribute('required');

    this.dropdown = document.createElement("div");
    this.dropdown.className = "cs-autocomplete-dropdown";

    this.container.append(this.wrapper, this.dropdown);
    this.hiddenInput.parentNode.insertBefore(this.container, this.hiddenInput.nextSibling);
  }

  getValidationContainer() {
    return this.hiddenInput.closest('.form-floating, .mb-3, .col, .form-check') || this.hiddenInput.parentElement;
  }

  setContainerInvalid(isInvalid) {
    const container = this.getValidationContainer();
    if (!container) return;
    container.classList.toggle('is-invalid', isInvalid);
  }

  loadExistingValues() {
    if (!this.initialDisplayValue) return;

    const displayValues = Array.isArray(this.initialDisplayValue)
      ? this.initialDisplayValue
      : [this.initialDisplayValue];

    this.selectedValues = displayValues;

    // Legge valore hidden (se già esiste, es. da PHP)
    const hiddenValue = this.hiddenInput.value;
    if (hiddenValue) {
      try {
        const parsed = JSON.parse(hiddenValue);
        this.selectedKeys = Array.isArray(parsed) ? parsed : [parsed];
      } catch {
        // Non è JSON, quindi è singolo valore
        this.selectedKeys = [hiddenValue];
      }
    }

    if (this.selectedValues.length > 0 && this.selectedKeys.length === 0) {
      this.selectedKeys = this.selectedValues.map(displayValue => this.valueMap[displayValue] ?? displayValue);
    }
    this.selectedKeys = this.selectedKeys.map(v => String(v));
    this.selectedValues = this.selectedValues.map(v => String(v));

    if (this.isMultiple) {
      this.selectedValues.forEach((value, idx) => this.addMultipleItem(value, this.selectedKeys[idx] ?? value));

      // If there are existing values in multiple mode, mark validation as passed
      if (this.isRequired && this.selectedValues.length > 0 && this.validationInput) {
        this.validationInput.value = "valid";
        this.validationInput.removeAttribute('required');
        // Only add is-valid if form was already validated
        if (this.isFormValidated()) {
          this.wrapper.classList.add('is-valid');
        }
      }
    } else if (this.selectedValues.length > 0) {
      // Single mode with existing value - mark as valid if required and form was validated
      if (this.isRequired && this.isFormValidated()) {
        this.wrapper.classList.add('is-valid');
      }
    }
  }


  /** --- DYNAMIC UPDATE --- */
  updateOptions(newOptions, syncSelection = true) {
    this.setOptions(newOptions);
    if (syncSelection) {
      this.syncSelectedValuesWithOptions();
    }
    this.filterOptions("");
    this.dropdown.classList.remove("show");
    this.dispatchContainerEvent("optionsUpdated", this.options);
  }

  createSingleInput() {
    this.wrapper.innerHTML = "";
    const input = document.createElement("input");
    input.type = "text";
    input.className = "cs-autocomplete-input-single form-control";
    // Don't show placeholder in floating mode (label is used instead)
    input.placeholder = this.isFloating ? " " : this.placeholder;
    input.value = this.selectedValues[0] || "";
    input.id = this.hiddenInput.id + '_visible';

    // Add has-value class if there's a value
    if (this.selectedValues.length) {
      input.classList.add('has-value');
    }

    // Transfer required attribute to visible input
    if (this.isRequired) {
      input.setAttribute('required', '');
      this.hiddenInput.removeAttribute('required');
    }
    if (!this.isReadonly) {
      input.addEventListener("input", e => this.handleSingleInput(e));
      input.addEventListener("focus", e => this.handleSingleFocus(e));
      input.addEventListener("blur", e => this.validateSingleInput(e));
    }
    input.addEventListener("keydown", e => this.handleKeydown(e));
    if (this.isReadonly) {
      input.setAttribute('readonly', '');
      input.setAttribute('tabindex', '-1');
    }
    this.wrapper.appendChild(input);
    if (this.selectedValues.length && !this.isReadonly) this.addClearButton();
    this.currentInput = input;
  }

  addClearButton() {
    const clearBtn = document.createElement("button");
    clearBtn.type = "button";
    clearBtn.className = "cs-clear-button";
    clearBtn.innerHTML = "×";
    clearBtn.addEventListener("click", e => {
      e.stopPropagation();
      this.clearSingleValue(true);
    });
    this.wrapper.appendChild(clearBtn);
  }

  clearSingleValue(showDropdownOnClear = false) {
    this.selectedValues = [];
    this.selectedKeys = [];
    this.isEditing = false;
    this.currentInput.value = "";
    this.currentInput.classList.remove('has-value');
    this.updateHiddenInput();
    this.removeClearButton();

    // Re-add required when clearing in single mode
    if (this.isRequired) {
      this.currentInput.setAttribute('required', '');
      this.wrapper.classList.remove('is-valid');
      // Only add is-invalid if form was already validated
      if (this.isFormValidated()) {
        this.wrapper.classList.add('is-invalid');
        this.setContainerInvalid(true);
      }
    }

    // Optional reopen (used only for explicit clear-button action).
    if (showDropdownOnClear && !this.apiUrl) {
      this.currentInput?.focus();
      this.showDropdown(true);
    }
  }

  removeClearButton() {
    const btn = this.wrapper.querySelector(".cs-clear-button");
    if (btn) btn.remove();
  }

  createNewInput() {
    const input = document.createElement("input");
    input.type = "text";
    input.className = "cs-autocomplete-input";
    // Don't show placeholder in floating mode (label is used instead)
    input.placeholder = this.isFloating ? " " : this.placeholder;

    // For multiple select, we use a hidden validation input
    if (this.isRequired) {
      this.createValidationInput();
    }

  
    if (!this.isReadonly) {
      input.addEventListener("input", e => this.handleInput(e));
      input.addEventListener("focus", () => this.handleMultipleFocus());
      input.setAttribute('placeholder', this.isFloating ? " " : this.placeholder);
    } else {
      input.setAttribute('readonly', '');
      input.setAttribute('tabindex', '-1');
      input.style.display = 'none'; // Nasconde l'input di ricerca in readonly
    }
    input.addEventListener("keydown", e => this.handleKeydown(e));
    this.wrapper.appendChild(input);
    this.currentInput = input;
  }

  createValidationInput() {
    // Create a visible but styled-as-hidden input for HTML5 validation in multiple mode
    const validationInput = document.createElement("input");
    validationInput.type = "text";
    validationInput.className = "milkselect-validation-input";
    validationInput.setAttribute('required', '');
    validationInput.setAttribute('tabindex', '-1');
    validationInput.style.position = "absolute";
    validationInput.style.left = "0";
    validationInput.style.top = "0";
    validationInput.style.width = "1px";
    validationInput.style.height = "1px";
    validationInput.style.opacity = "0";
    validationInput.style.border = "none";
    validationInput.style.padding = "0";
    validationInput.style.margin = "0";

    // Remove required from hidden input
    this.hiddenInput.removeAttribute('required');

    // Add event listener for invalid event to show validation error
    validationInput.addEventListener('invalid', (e) => {
      this.wrapper.classList.add('is-invalid');
      this.setContainerInvalid(true);
      // Focus on the actual search input instead
      this.currentInput?.focus();
    });

    // Insert at the beginning of wrapper so it can receive focus
    this.wrapper.insertBefore(validationInput, this.wrapper.firstChild);
    this.validationInput = validationInput;
  }

  // --- FOCUS HANDLERS ---

  handleSingleFocus(e) {
    // Do not auto-open on focus (important for programmatic focus, e.g. offcanvas/modal).
    // Dropdown is opened by explicit click on wrapper or by typing/keyboard.
    if (this.apiUrl) return;
  }

  handleMultipleFocus() {
    // Do not auto-open on focus (important for programmatic focus, e.g. offcanvas/modal).
    if (this.apiUrl) return;
  }

  // --- INPUT HANDLERS ---

  handleInput(e) {
    const searchTerm = e.target.value;
    if (this.apiUrl) {
      this.fetchOptionsDebounced(searchTerm);
    } else {
      this.filterOptions(searchTerm.toLowerCase());
      this.showDropdownIfNeeded();
    }
  }

  handleSingleInput(e) {
    const searchTerm = e.target.value;
    this.isEditing = true; // User has modified the input

    if (this.apiUrl) {
      this.fetchOptionsDebounced(searchTerm);
    } else {
      this.filterOptions(searchTerm.toLowerCase());
      this.showDropdownIfNeeded();
    }
  }

  fetchOptionsDebounced(searchTerm) {
    clearTimeout(this.fetchTimeout);

    if (!searchTerm || searchTerm.trim().length === 0) {
      this.dropdown.innerHTML = "";
      this.hideDropdown();
      return;
    }

    // Show loading immediately when user types
    this.showLoading();

    this.fetchTimeout = setTimeout(() => {
      this.fetchOptions(searchTerm);
    }, 300);
  }

  showLoading() {
    this.dropdown.innerHTML = "";
    const loadingItem = document.createElement("div");
    loadingItem.className = "cs-autocomplete-loading";
    loadingItem.innerHTML = `<span class="cs-loading-spinner"></span> Loading...`;
    this.dropdown.appendChild(loadingItem);
    this.dropdown.classList.add("show");
    this.adjustDropdownPosition();
  }

  async fetchOptions(searchTerm) {
    if (!searchTerm || searchTerm.trim().length === 0) {
      this.dropdown.innerHTML = "";
      this.hideDropdown();
      return;
    }

    this.isLoading = true;
    const separator = this.apiUrl.includes('?') ? '&' : '?';
    const url = `${this.apiUrl}${separator}q=${encodeURIComponent(searchTerm)}`;

    try {
      const response = await fetch(url);
      const data = await response.json();

      if (data.success === 'ok' && data.options) {
        this.processApiOptions(data.options);
        this.filterOptions(searchTerm.toLowerCase());
        this.showDropdownIfNeeded();
      } else {
        this.dropdown.innerHTML = "";
        this.hideDropdown();
        alert('Errore nel caricamento dei dati');
      }
    } catch (error) {
      this.dropdown.innerHTML = "";
      this.hideDropdown();
      alert('Errore di rete: ' + error.message);
    } finally {
      this.isLoading = false;
    }
  }

  processApiOptions(options) {
    if (Array.isArray(options)) {
      this.setOptions(options);
    } else if (typeof options === 'object' && options !== null) {
      const mappedOptions = Object.entries(options).map(([value, text]) => ({
        value,
        text
      }));
      this.setOptions(mappedOptions);
    } else {
      this.setOptions([]);
    }
  }

  validateSingleInput(e) {
    // Small delay to allow click events on dropdown items to fire first
    setTimeout(() => {
      const currentValue = e.target.value.trim();

      // If field is empty, clear the selection
      if (currentValue === "") {
        this.clearSingleValue(false);
        return;
      }

      // Check if current value matches any valid option
      const isValidOption = this.options.some(opt => opt === currentValue);

      // Also check if it matches the currently selected value (for API mode where options may not be loaded)
      const isCurrentSelection = this.selectedValues.length > 0 && this.selectedValues[0] === currentValue;

      // If not valid, reset to previous selected value or clear
      if (!isValidOption && !isCurrentSelection) {
        if (this.allowCreate && currentValue !== "") {
          const createOption = { value: currentValue, text: currentValue, group: null };
          this.addOption(createOption, false);
          this.selectOption(createOption);
          return;
        }

        if (this.selectedValues.length > 0) {
          // Restore the last valid selection
          e.target.value = this.selectedValues[0];
          e.target.classList.add('has-value');
        } else {
          // No previous selection, clear the field
          this.clearSingleValue(false);
        }
      }
    }, 200);
  }

  filterOptions(searchTerm, forceShowAll = false) {
    this.dropdown.innerHTML = "";
    const normalizedSearchTerm = (searchTerm || "").toLowerCase();

    // On first open (static mode), don't filter to show all options
    const shouldFilter = !forceShowAll && (!this.isFirstOpen || normalizedSearchTerm.length > 0);

    const filtered = this.optionItems.filter(optionItem => {
      // Always exclude already selected values in multiple mode
      if (this.isMultiple && this.selectedKeys.includes(optionItem.value)) {
        return false;
      }
      // Filter by search term
      return !shouldFilter || optionItem.text.toLowerCase().includes(normalizedSearchTerm);
    });

    // In single mode: exclude the currently selected value ONLY if user hasn't started editing
    // Once user modifies the input, show all matching options including the selected one
    const filteredFinal = filtered.filter(optionItem => {
      if (!forceShowAll && !this.isMultiple && !this.isEditing && this.selectedValues.length > 0 && this.selectedValues[0] === optionItem.text) {
        return false;
      }
      return true;
    });

    if (filteredFinal.length === 0) {
      // No results to show (or only the already-selected option)
      this.dropdown.innerHTML = "";
      return;
    }

    this.renderDropdownItems(filteredFinal);

    this.activeIndex = -1;
  }

  renderDropdownItems(optionItems) {
    const hasGroups = optionItems.some(optionItem => optionItem.group);

    if (!hasGroups) {
      optionItems.forEach(optionItem => this.appendDropdownItem(optionItem));
      return;
    }

    const groups = new Map();
    optionItems.forEach(optionItem => {
      const groupName = optionItem.group || "";
      if (!groups.has(groupName)) {
        groups.set(groupName, []);
      }
      groups.get(groupName).push(optionItem);
    });

    groups.forEach((items, groupName) => {
      if (groupName) {
        const groupLabel = document.createElement("div");
        groupLabel.className = "cs-autocomplete-group-label";
        groupLabel.textContent = groupName;
        this.dropdown.appendChild(groupLabel);
      }

      items.forEach(optionItem => this.appendDropdownItem(optionItem));
    });
  }

  appendDropdownItem(optionItem) {
    const item = document.createElement("div");
    item.className = "cs-autocomplete-item";
    item.textContent = optionItem.text;
    item.dataset.value = optionItem.value;
    if (optionItem.group) {
      item.dataset.group = optionItem.group;
    }
    item.addEventListener("click", () => this.selectOption(optionItem));
    this.dropdown.appendChild(item);
  }

  /**
   * Show dropdown only if there are meaningful options to display
   */
  showDropdownIfNeeded() {
    const items = this.dropdown.querySelectorAll(".cs-autocomplete-item");
    if (items.length > 0) {
      this.dropdown.classList.add("show");
      this.adjustDropdownPosition();
    } else {
      this.hideDropdown();
    }
  }

  selectOption(optionData) {
    const selectedOption = (optionData && typeof optionData === "object" && !Array.isArray(optionData))
      ? optionData
      : this.findOptionByText(optionData);

    const displayValue = selectedOption ? selectedOption.text : String(optionData);
    const key = selectedOption
      ? String(selectedOption.value)
      : String(this.valueMap[displayValue] ?? displayValue);
    this.isEditing = false; // Reset editing state

    if (this.isMultiple) {
      if (!this.selectedKeys.includes(key)) {
        this.selectedValues.push(displayValue);
        this.selectedKeys.push(key);
        this.addMultipleItem(displayValue, key);
      }
      this.currentInput.value = "";
      this.currentInput.focus();

      // Remove required when at least one item is selected in multiple mode
      if (this.isRequired && this.selectedValues.length > 0 && this.validationInput) {
        this.validationInput.value = "valid";
        this.validationInput.removeAttribute('required');
        this.wrapper.classList.remove('is-invalid');
        this.setContainerInvalid(false);
        if (this.isFormValidated() || this.wrapper.classList.contains('is-invalid')) {
          this.wrapper.classList.add('is-valid');
        }
      }
    } else {
      this.selectedValues = [displayValue];
      this.selectedKeys = [key];
      this.currentInput.value = displayValue;
      this.currentInput.classList.add('has-value');
      this.removeClearButton();
      this.addClearButton();

      // Remove required when a value is selected in single mode
      if (this.isRequired) {
        this.currentInput.removeAttribute('required');
        this.wrapper.classList.remove('is-invalid');
        this.setContainerInvalid(false);
        if (this.isFormValidated() || this.wrapper.classList.contains('is-invalid')) {
          this.wrapper.classList.add('is-valid');
        }
      }
    }
    this.updateHiddenInput();
    this.hideDropdown();
  }

  isFormValidated() {
    const form = this.hiddenInput.closest('form');
    return form && form.classList.contains('was-validated');
  }

  applyInitialValidationState() {
    const inputHasInvalid = this.currentInput?.classList.contains('is-invalid');
    const hiddenHasInvalid = this.hiddenInput.classList.contains('is-invalid');

    if (hiddenHasInvalid && this.currentInput && !inputHasInvalid) {
      this.currentInput.classList.add('is-invalid');
    }

    if (inputHasInvalid || hiddenHasInvalid) {
      this.wrapper.classList.add('is-invalid');
      this.setContainerInvalid(true);
      return;
    }

    if (this.isRequired && this.isFormValidated()) {
      const hasValue = this.isMultiple
        ? this.selectedValues.length > 0
        : this.selectedValues.length > 0 && this.currentInput?.value;

      if (!hasValue) {
        this.wrapper.classList.add('is-invalid');
        this.setContainerInvalid(true);
      }
    }
  }

  addMultipleItem(value, key = value) {
    const item = document.createElement("div");
    item.className = "cs-autocomplete-selected-item";
    item.dataset.value = value;
    item.dataset.key = String(key);
    if (this.isReadonly) {
      item.innerHTML = `${value}`;
    } else {
      item.innerHTML = `${value}<button type="button" class="cs-autocomplete-remove-btn">×</button>`;
      item.querySelector("button").addEventListener("click", () =>
        this.removeMultipleItem(String(key), item)
      );
    }
    this.wrapper.insertBefore(item, this.currentInput);
  }

  removeMultipleItem(key, el) {
    el.remove();
    const index = this.selectedKeys.indexOf(String(key));
    if (index > -1) {
      this.selectedValues.splice(index, 1);
      this.selectedKeys.splice(index, 1);
    }
    this.updateHiddenInput();

    // Re-add required if all items are removed in multiple mode
    if (this.isRequired && this.selectedValues.length === 0 && this.validationInput) {
      this.validationInput.value = "";
      this.validationInput.setAttribute('required', '');
      this.wrapper.classList.remove('is-valid');
      if (this.isFormValidated()) {
        this.wrapper.classList.add('is-invalid');
        this.setContainerInvalid(true);
      }
    }
  }

  handleKeydown(e) {
    if (this.isReadonly) return;
    const items = this.dropdown.querySelectorAll(".cs-autocomplete-item");
    const isDropdownOpen = this.dropdown.classList.contains("show");

    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        // If dropdown is not open in static mode, open it
        if (!isDropdownOpen && !this.apiUrl) {
          this.showDropdown(true);
          return;
        }
        this.activeIndex = Math.min(this.activeIndex + 1, items.length - 1);
        this.updateActiveItem(items);
        break;
      case "ArrowUp":
        e.preventDefault();
        this.activeIndex = Math.max(this.activeIndex - 1, 0);
        this.updateActiveItem(items);
        break;
      case "Tab":
        if (isDropdownOpen && items.length > 0) {
          e.preventDefault();
          this.activeIndex = (this.activeIndex + 1) % items.length;
          this.updateActiveItem(items);
        }
        break;
      case "Enter":
        e.preventDefault();

        const inputValue = this.currentInput?.value.trim() || "";

        if (this.activeIndex >= 0 && items[this.activeIndex]) {
          items[this.activeIndex].click();
        }
        else if (inputValue.length > 0 && items.length > 0) {
          items[0].click();
        }
        else if (inputValue.length > 0 && this.allowCreate && !this.apiUrl) {
          const createOption = { value: inputValue, text: inputValue, group: null };
          this.addOption(createOption, true);
        }
        else {
          this.hideDropdown();
        }
        break;
      case "Escape":
        this.hideDropdown();
        this.isEditing = false;
        // Restore selected value if in single mode
        if (!this.isMultiple && this.selectedValues.length > 0) {
          this.currentInput.value = this.selectedValues[0];
        }
        break;
    }
  }

  updateActiveItem(items) {
    items.forEach((el, i) => el.classList.toggle("active", i === this.activeIndex));
    if (items[this.activeIndex]) items[this.activeIndex].scrollIntoView({ block: "nearest" });
  }

  showDropdown(forceShowAll = false) {
    const searchTerm = forceShowAll ? "" : (this.currentInput?.value.toLowerCase() || "");
    this.filterOptions(searchTerm, forceShowAll);
    this.showDropdownIfNeeded();
    this.isFirstOpen = false;
  }

  hideDropdown() {
    this.dropdown.classList.remove("show");
    this.dropdown.classList.remove("show-above");
  }

  adjustDropdownPosition() {
    const rect = this.wrapper.getBoundingClientRect();
    const dropdownHeight = this.dropdown.offsetHeight || 200;
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;

    if (spaceBelow < dropdownHeight && spaceAbove > dropdownHeight) {
      this.dropdown.classList.add("show-above");
    } else {
      this.dropdown.classList.remove("show-above");
    }
  }

  updateHiddenInput() {
    const valuesToSave = this.saveByValue ? this.selectedKeys : this.selectedValues;

    let val;
    if (this.isMultiple) {
      val = JSON.stringify(valuesToSave);
    } else {
      val = valuesToSave.length > 0 ? valuesToSave[0] : "";
    }

    this.hiddenInput.value = val;
    this.hiddenInput.dispatchEvent(new Event("change", { bubbles: true }));
  }


  addEventListeners() {
    this.onDocumentClick = (e) => {
      if (this.container && !this.container.contains(e.target)) this.hideDropdown();
    };
    document.addEventListener("click", this.onDocumentClick);

    this.onWrapperClick = () => {
      if (!this.isReadonly) {
        this.currentInput?.focus();
        if (!this.apiUrl) {
          this.showDropdown(true);
        }
      }
    };
    this.wrapper.addEventListener("click", this.onWrapperClick);

    // Remove is-invalid class on focus
    this.onWrapperFocus = () => {
      if (this.wrapper.classList.contains('is-invalid')) {
        this.wrapper.classList.remove('is-invalid');
        this.hiddenInput.classList.remove('is-invalid');
        this.hiddenInput.classList.remove('js-focus-remove-is-invalid');
        this.setContainerInvalid(false);
      }
    };
    this.wrapper.addEventListener("focus", this.onWrapperFocus, true);

    // Add is-valid class when field loses focus and has a valid value
    this.onWrapperBlur = () => {
      if (this.isRequired && this.isFormValidated()) {
        const hasValue = this.isMultiple
          ? this.selectedValues.length > 0
          : this.selectedValues.length > 0 && this.currentInput?.value;

        if (hasValue) {
          this.wrapper.classList.remove('is-invalid');
          this.wrapper.classList.add('is-valid');
        }
      }
    };
    this.wrapper.addEventListener("blur", this.onWrapperBlur, true);
  }

  removeEventListeners() {
    if (this.onDocumentClick) {
      document.removeEventListener("click", this.onDocumentClick);
      this.onDocumentClick = null;
    }
    if (this.wrapper && this.onWrapperClick) {
      this.wrapper.removeEventListener("click", this.onWrapperClick);
      this.onWrapperClick = null;
    }
    if (this.wrapper && this.onWrapperFocus) {
      this.wrapper.removeEventListener("focus", this.onWrapperFocus, true);
      this.onWrapperFocus = null;
    }
    if (this.wrapper && this.onWrapperBlur) {
      this.wrapper.removeEventListener("blur", this.onWrapperBlur, true);
      this.onWrapperBlur = null;
    }
  }

  destroy() {
    if (this.isDestroyed) {
      return;
    }

    clearTimeout(this.fetchTimeout);
    this.hideDropdown();
    this.removeEventListeners();

    if (this.container && this.container.parentNode) {
      this.container.parentNode.removeChild(this.container);
    }

    if (this.hiddenInput) {
      this.hiddenInput.dataset.milkselectInitialized = 'false';
      if (this.hiddenInput.milkSelectInstance === this) {
        delete this.hiddenInput.milkSelectInstance;
      }
      if (this.isRequired) {
        this.hiddenInput.setAttribute('required', '');
      }
    }

    this.currentInput = null;
    this.dropdown = null;
    this.wrapper = null;
    this.container = null;
    this.isDestroyed = true;
  }

  remove() {
    this.destroy();
  }
}

// Auto-initialization for dynamically loaded content
if (typeof MutationObserver !== 'undefined') {
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType === 1) {
          if (node.matches && node.matches('input[type="hidden"][data-milkselect-config]')) {
            if (node.dataset.milkselectInitialized !== 'true') {
              MilkSelect.initFromConfig(node.id);
            }
          }
          if (node.querySelectorAll) {
            const selects = node.querySelectorAll('input[type="hidden"][data-milkselect-config]');
            selects.forEach(select => {
              if (select.dataset.milkselectInitialized !== 'true') {
                MilkSelect.initFromConfig(select.id);
              }
            });
          }
        }
      });
    });
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      observer.observe(document.body, {
        childList: true,
        subtree: true
      });
    });
  } else {
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }
}
