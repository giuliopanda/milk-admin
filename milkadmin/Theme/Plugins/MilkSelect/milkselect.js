class MilkSelect {
    constructor(element, options = [], valueMap = null, displayValue = null, placeholder = null) {
    this.hiddenInput = element;
    this.isMultiple = element.dataset.selectType === "multiple";
    this.options = options;
    this.valueMap = valueMap || null; // solo in memoria
    this.selectedValues = []; // Display values (what user sees)
    this.selectedKeys = []; // IDs/keys (what gets saved)
    this.activeIndex = -1;
    this.currentInput = null;
    this.initialDisplayValue = displayValue;
    this.isFirstOpen = true; // Track if dropdown was opened before
    this.placeholder = placeholder || (this.isMultiple ? 'Aggiungi valore...' : 'Cerca o seleziona...');
    this.isFloating = element.dataset.floating === '1';
    this.apiUrl = element.dataset.apiUrl || null;
    this.fetchTimeout = null;
    this.isLoading = false;

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

  init() {
    this.createDOM();
    this.loadExistingValues();
    this.addEventListeners();
    this.isMultiple ? this.createNewInput() : this.createSingleInput();
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

    // Store if field is required
    this.isRequired = this.hiddenInput.hasAttribute('required');

    this.dropdown = document.createElement("div");
    this.dropdown.className = "cs-autocomplete-dropdown";

    this.container.append(this.wrapper, this.dropdown);
    this.hiddenInput.parentNode.insertBefore(this.container, this.hiddenInput.nextSibling);
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

  if (this.isMultiple) {
    this.selectedValues.forEach(v => this.addMultipleItem(v));

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
  updateOptions(newOptions) {
    this.options = Array.isArray(newOptions) ? newOptions : [];
    this.filterOptions("");
    this.dropdown.classList.remove("show");
    this.container.dispatchEvent(new CustomEvent("optionsUpdated", { detail: this.options }));
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

    input.addEventListener("input", e => this.handleSingleInput(e));
    input.addEventListener("focus", e => this.showDropdown());
    input.addEventListener("keydown", e => this.handleKeydown(e));
    input.addEventListener("blur", e => this.validateSingleInput(e));

    this.wrapper.appendChild(input);
    if (this.selectedValues.length) this.addClearButton();
    this.currentInput = input;
  }

  addClearButton() {
    const clearBtn = document.createElement("button");
    clearBtn.type = "button";
    clearBtn.className = "cs-clear-button";
    clearBtn.innerHTML = "×";
    clearBtn.addEventListener("click", e => {
      e.stopPropagation();
      this.clearSingleValue();
    });
    this.wrapper.appendChild(clearBtn);
  }

  clearSingleValue() {
    this.selectedValues = [];
    this.selectedKeys = [];
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
      }
    }

    this.showDropdown();
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

    input.addEventListener("input", e => this.handleInput(e));
    input.addEventListener("focus", () => this.showDropdown());
    input.addEventListener("keydown", e => this.handleKeydown(e));
    this.wrapper.appendChild(input);
    this.currentInput = input;
  }

  createValidationInput() {
    // Create a visible but styled-as-hidden input for HTML5 validation in multiple mode
    // This input will receive focus and show the browser's validation message
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
      // Focus on the actual search input instead
      this.currentInput?.focus();
    });

    // Insert at the beginning of wrapper so it can receive focus
    this.wrapper.insertBefore(validationInput, this.wrapper.firstChild);
    this.validationInput = validationInput;
  }

  handleInput(e) {
    const searchTerm = e.target.value;
    if (this.apiUrl) {
      this.fetchOptionsDebounced(searchTerm);
    } else {
      this.filterOptions(searchTerm.toLowerCase());
    }
    this.showDropdown();
  }

  handleSingleInput(e) {
    const searchTerm = e.target.value;
    if (this.apiUrl) {
      this.fetchOptionsDebounced(searchTerm);
    } else {
      this.filterOptions(searchTerm.toLowerCase());
    }
    this.showDropdown();
  }

  fetchOptionsDebounced(searchTerm) {
    clearTimeout(this.fetchTimeout);
    this.fetchTimeout = setTimeout(() => {
      this.fetchOptions(searchTerm);
    }, 300);
  }

  async fetchOptions(searchTerm) {
    if (!searchTerm || searchTerm.trim().length === 0) {
      this.dropdown.innerHTML = "";
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
      } else {
        alert('Errore nel caricamento dei dati');
      }
    } catch (error) {
      alert('Errore di rete: ' + error.message);
    } finally {
      this.isLoading = false;
    }
  }

  processApiOptions(options) {
    if (Array.isArray(options)) {
      this.options = options;
      this.valueMap = null;
    } else if (typeof options === 'object') {
      this.options = Object.values(options);
      this.valueMap = {};
      Object.entries(options).forEach(([key, value]) => {
        this.valueMap[value] = key;
      });
    }
  }

  validateSingleInput(e) {
    // Small delay to allow click events on dropdown items to fire first
    setTimeout(() => {
      const currentValue = e.target.value.trim();

      // If field is empty, clear the selection
      if (currentValue === "") {
        this.clearSingleValue();
        return;
      }

      // Check if current value matches any valid option
      const isValidOption = this.options.some(opt => opt === currentValue);

      // If not valid, reset to previous selected value or clear
      if (!isValidOption) {
        if (this.selectedValues.length > 0) {
          // Restore the last valid selection
          e.target.value = this.selectedValues[0];
          e.target.classList.add('has-value');
        } else {
          // No previous selection, clear the field
          this.clearSingleValue();
        }
      }
    }, 200);
  }

  filterOptions(searchTerm) {
    this.dropdown.innerHTML = "";

    // On first open, don't filter by search term to show all options
    const shouldFilter = !this.isFirstOpen || (searchTerm && searchTerm.length > 0);

    const filtered = this.options.filter(opt => {
      // Always exclude already selected values in multiple mode
      if (this.isMultiple && this.selectedValues.includes(opt)) {
        return false;
      }
      // For single select, include the current value on first open
      if (!this.isMultiple && this.isFirstOpen && this.selectedValues.includes(opt)) {
        return true;
      }
      // Filter by search term
      return !shouldFilter || opt.toLowerCase().includes(searchTerm);
    });

    if (filtered.length === 0) {
       this.dropdown.innerHTML = "";
      return;
    }

    filtered.forEach((opt, idx) => {
      const item = document.createElement("div");
      item.className = "cs-autocomplete-item";
      item.textContent = opt;
      item.addEventListener("click", () => this.selectOption(opt));
      this.dropdown.appendChild(item);
    });

    this.activeIndex = -1;
  }

  selectOption(value) {
    // Get the corresponding ID/key if using value mapping
    const key = this.valueMap ? this.valueMap[value] : value;

    if (this.isMultiple) {
      if (!this.selectedValues.includes(value)) {
        this.selectedValues.push(value);
        this.selectedKeys.push(key);
        this.addMultipleItem(value);
      }
      this.currentInput.value = "";
      this.currentInput.focus();

      // Remove required when at least one item is selected in multiple mode
      if (this.isRequired && this.selectedValues.length > 0 && this.validationInput) {
        this.validationInput.value = "valid"; // Set a value to pass validation
        this.validationInput.removeAttribute('required');
        this.wrapper.classList.remove('is-invalid');
        // Only add is-valid if form was already validated or field already had validation classes
        if (this.isFormValidated() || this.wrapper.classList.contains('is-invalid')) {
          this.wrapper.classList.add('is-valid');
        }
      }
    } else {
      this.selectedValues = [value];
      this.selectedKeys = [key];
      this.currentInput.value = value;
      this.currentInput.classList.add('has-value');
      this.removeClearButton();
      this.addClearButton();

      // Remove required when a value is selected in single mode
      if (this.isRequired) {
        this.currentInput.removeAttribute('required');
        this.wrapper.classList.remove('is-invalid');
        // Only add is-valid if form was already validated or field already had validation classes
        if (this.isFormValidated() || this.wrapper.classList.contains('is-invalid')) {
          this.wrapper.classList.add('is-valid');
        }
      }
    }
    this.updateHiddenInput();
    this.hideDropdown();
  }

  isFormValidated() {
    // Check if the form has the 'was-validated' class
    const form = this.hiddenInput.closest('form');
    return form && form.classList.contains('was-validated');
  }

  addMultipleItem(value) {
    const item = document.createElement("div");
    item.className = "cs-autocomplete-selected-item";
    item.dataset.value = value;
    item.innerHTML = `${value}<button type="button" class="cs-autocomplete-remove-btn">×</button>`;
    item.querySelector("button").addEventListener("click", () =>
      this.removeMultipleItem(value, item)
    );
    this.wrapper.insertBefore(item, this.currentInput);
  }

  removeMultipleItem(value, el) {
    el.remove();
    const index = this.selectedValues.indexOf(value);
    if (index > -1) {
      this.selectedValues.splice(index, 1);
      this.selectedKeys.splice(index, 1);
    }
    this.updateHiddenInput();

    // Re-add required if all items are removed in multiple mode
    if (this.isRequired && this.selectedValues.length === 0 && this.validationInput) {
      this.validationInput.value = ""; // Clear value to trigger validation
      this.validationInput.setAttribute('required', '');
      this.wrapper.classList.remove('is-valid');
      // Only add is-invalid if form was already validated
      if (this.isFormValidated()) {
        this.wrapper.classList.add('is-invalid');
      }
    }
  }

  handleKeydown(e) {
    const items = this.dropdown.querySelectorAll(".cs-autocomplete-item");
    const isDropdownOpen = this.dropdown.classList.contains("show");

    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        this.activeIndex = Math.min(this.activeIndex + 1, items.length - 1);
        this.updateActiveItem(items);
        break;
      case "ArrowUp":
        e.preventDefault();
        this.activeIndex = Math.max(this.activeIndex - 1, 0);
        this.updateActiveItem(items);
        break;
      case "Tab":
        // Solo se il dropdown è aperto
        if (isDropdownOpen && items.length > 0) {
          e.preventDefault();
          // Cicla tra le opzioni: incrementa e ricomincia da 0 quando arriva alla fine
          this.activeIndex = (this.activeIndex + 1) % items.length;
          this.updateActiveItem(items);
        }
        break;
      case "Enter":
        e.preventDefault();

        // Get current input value
        const inputValue = this.currentInput?.value.trim() || "";

        // Se c'è un'opzione attiva (navigata con le frecce), selezionala
        if (this.activeIndex >= 0 && items[this.activeIndex]) {
          items[this.activeIndex].click();
        }
        // Se l'utente ha digitato qualcosa, seleziona la prima opzione filtrata
        else if (inputValue.length > 0 && items.length > 0) {
          items[0].click();
        }
        // Altrimenti (campo vuoto, nessuna ricerca), non fare nulla (lascia vuoto)
        else {
          this.hideDropdown();
        }
        break;
      case "Escape":
        this.hideDropdown();
        break;
    }
  }

  updateActiveItem(items) {
    items.forEach((el, i) => el.classList.toggle("active", i === this.activeIndex));
    if (items[this.activeIndex]) items[this.activeIndex].scrollIntoView({ block: "nearest" });
  }

  showDropdown() {
    this.dropdown.classList.add("show");
    this.filterOptions(this.currentInput?.value.toLowerCase() || "");
    this.adjustDropdownPosition();
    this.isFirstOpen = false; // Mark that dropdown was opened
  }

  hideDropdown() {
    this.dropdown.classList.remove("show");
    this.dropdown.classList.remove("show-above");
  }

  adjustDropdownPosition() {
    // Verifica se il dropdown dovrebbe essere mostrato sopra invece che sotto
    const rect = this.wrapper.getBoundingClientRect();
    const dropdownHeight = this.dropdown.offsetHeight || 200; // Altezza stimata
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;

    // Se non c'è abbastanza spazio sotto ma c'è spazio sopra, mostra sopra
    if (spaceBelow < dropdownHeight && spaceAbove > dropdownHeight) {
      this.dropdown.classList.add("show-above");
    } else {
      this.dropdown.classList.remove("show-above");
    }
  }

    updateHiddenInput() {
    // Use selectedKeys (IDs) if we have a value map, otherwise use selectedValues
    const valuesToSave = this.valueMap ? this.selectedKeys : this.selectedValues;

    let val;
    if (this.isMultiple) {
      // Multiple: save as JSON array
      val = JSON.stringify(valuesToSave);
    } else {
      // Single: save first value without JSON encoding
      val = valuesToSave.length > 0 ? valuesToSave[0] : "";
    }

    this.hiddenInput.value = val;
    this.hiddenInput.dispatchEvent(new Event("change", { bubbles: true }));
  }


  addEventListeners() {
    document.addEventListener("click", e => {
      if (!this.container.contains(e.target)) this.hideDropdown();
    });

    this.wrapper.addEventListener("click", () => {
      this.currentInput?.focus();
    });

    // Remove is-invalid class on focus (like other form inputs)
    this.wrapper.addEventListener("focus", () => {
      if (this.wrapper.classList.contains('is-invalid')) {
        this.wrapper.classList.remove('is-invalid');
        this.hiddenInput.classList.remove('is-invalid');
        this.hiddenInput.classList.remove('js-focus-remove-is-invalid');
      }
      // Don't add is-valid on focus, only when a value is selected
    }, true);

    // Add is-valid class when field loses focus and has a valid value (only if form was validated)
    this.wrapper.addEventListener("blur", () => {
      if (this.isRequired && this.isFormValidated()) {
        const hasValue = this.isMultiple
          ? this.selectedValues.length > 0
          : this.selectedValues.length > 0 && this.currentInput?.value;

        if (hasValue) {
          this.wrapper.classList.remove('is-invalid');
          this.wrapper.classList.add('is-valid');
        }
      }
    }, true);
  }
}

// Auto-initialization for dynamically loaded content
// This MutationObserver watches for new MilkSelect elements added to the DOM
if (typeof MutationObserver !== 'undefined') {
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        // Check if the added node is an element
        if (node.nodeType === 1) {
          // Check if the node itself is a milkselect input
          if (node.matches && node.matches('input[type="hidden"][data-milkselect-config]')) {
            if (node.dataset.milkselectInitialized !== 'true') {
              MilkSelect.initFromConfig(node.id);
            }
          }
          // Check for milkselect inputs within the added node
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

  // Start observing when DOM is ready
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
