(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const forms = document.querySelectorAll('.js-needs-validation');
        Array.prototype.slice.call(forms).forEach(form => {
            console.log ("DOMContentLoaded");
            initMilkForm(form);
            setFormSubmit(form);
            initEnterNavigation(form);
        });
        initReadonlySelects(document);
        initReadonlyChoices(document);
        initRequiredCheckboxGroups(document);

        if (typeof removeIsInvalid === 'function') {
            removeIsInvalid();
        }
    });
})();

function initReadonlySelects(scope) {
    const root = scope && scope.querySelectorAll ? scope : document;
    root.querySelectorAll('select[readonly]:not([disabled])').forEach(bindReadonlySelect);
}

function initReadonlyChoices(scope) {
    const root = scope && scope.querySelectorAll ? scope : document;
    root.querySelectorAll('input[readonly][type="checkbox"]:not([disabled]), input[readonly][type="radio"]:not([disabled])').forEach(bindReadonlyChoice);
}

function initRequiredCheckboxGroups(scope) {
    const root = scope && scope.querySelectorAll ? scope : document;
    root.querySelectorAll('.js-form-checkboxes-group[data-required-group="checkboxes"]').forEach(bindRequiredCheckboxGroup);
}

function bindRequiredCheckboxGroup(group) {
    if (!group || group.dataset.requiredCheckboxGroupBound === '1') return;
    group.dataset.requiredCheckboxGroupBound = '1';

    const inputs = Array.from(group.querySelectorAll('input[type="checkbox"]'));
    if (!inputs.length) return;

    const getActiveInputs = () => inputs.filter((input) => !input.disabled);
    const getMessage = () => {
        const customMessage = String(group.dataset.requiredMessage ?? '').trim();
        if (customMessage !== '') {
            return customMessage;
        }
        return 'Please select at least one option.';
    };

    const syncGroupValidity = () => {
        const form = group.closest('form');
        const showValidationUi = !!(form && form.classList.contains('was-validated'));
        const activeInputs = getActiveInputs();
        if (!activeInputs.length) {
            inputs.forEach((input) => {
                input.setCustomValidity('');
                if (showValidationUi) {
                    input.classList.remove('is-invalid');
                }
            });
            if (showValidationUi) {
                group.classList.remove('is-invalid');
            }
            return;
        }

        const hasChecked = activeInputs.some((input) => input.checked);
        const message = getMessage();

        activeInputs.forEach((input, index) => {
            input.setCustomValidity(!hasChecked && index === 0 ? message : '');
            if (showValidationUi && hasChecked) {
                input.classList.remove('is-invalid');
            }
        });

        if (showValidationUi) {
            if (hasChecked) {
                group.classList.remove('is-invalid');
            } else {
                group.classList.add('is-invalid');
            }
        }
    };

    inputs.forEach((input) => {
        input.addEventListener('fieldValidation', syncGroupValidity);
        input.addEventListener('change', syncGroupValidity);
    });

    syncGroupValidity();
}

function bindReadonlyChoice(input) {
    if (!input || input.dataset.readonlyChoiceBound === '1') return;

    input.dataset.readonlyChoiceBound = '1';
    input.dataset.readonlyInitialChecked = input.checked ? '1' : '0';

    const blockedKeys = new Set([
        ' ',
        'Spacebar',
        'Enter',
        'ArrowUp',
        'ArrowDown',
        'ArrowLeft',
        'ArrowRight',
        'Home',
        'End'
    ]);

    const blockPointerInteraction = function (event) {
        event.preventDefault();
        event.stopPropagation();
    };

    const blockKeyboardInteraction = function (event) {
        if (!blockedKeys.has(event.key)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
    };

    const restoreReadonlyState = function () {
        const owner = input.form || document;
        if (input.type === 'radio' && input.name) {
            const radios = Array.from(owner.querySelectorAll('input[readonly][type="radio"]'))
                .filter((radio) => radio.name === input.name);
            radios.forEach((radio) => {
                radio.checked = (radio.dataset.readonlyInitialChecked === '1');
            });
            return;
        }

        input.checked = (input.dataset.readonlyInitialChecked === '1');
    };

    input.addEventListener('mousedown', blockPointerInteraction);
    input.addEventListener('touchstart', blockPointerInteraction, { passive: false });
    input.addEventListener('click', blockPointerInteraction);
    input.addEventListener('keydown', blockKeyboardInteraction);
    input.addEventListener('change', restoreReadonlyState);
}

function bindReadonlySelect(select) {
    if (!select || select.dataset.readonlySelectBound === '1') return;

    select.dataset.readonlySelectBound = '1';
    select.dataset.readonlyInitial = String(select.value ?? '');

    const blockedKeys = new Set([
        'ArrowUp',
        'ArrowDown',
        'ArrowLeft',
        'ArrowRight',
        'Home',
        'End',
        'PageUp',
        'PageDown',
        'Enter',
        ' '
    ]);

    const blockPointerInteraction = function (event) {
        event.preventDefault();
        event.stopPropagation();
    };

    const blockKeyboardInteraction = function (event) {
        if (!blockedKeys.has(event.key)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
    };

    const restoreReadonlyValue = function () {
        const initialValue = String(select.dataset.readonlyInitial ?? '');
        if (String(select.value ?? '') !== initialValue) {
            select.value = initialValue;
        }
    };

    select.addEventListener('mousedown', blockPointerInteraction);
    select.addEventListener('touchstart', blockPointerInteraction, { passive: false });
    select.addEventListener('keydown', blockKeyboardInteraction);
    select.addEventListener('change', restoreReadonlyValue);
}

function initMilkForm(form) {
    if (!form || form.dataset.milkFormBound === '1') return;
    if (typeof MilkForm !== 'function') return;

    // Init MilkForm (reuses instance if already present)
    const instance = new MilkForm(form);

    // Hook custom validation to MilkForm
    form.addEventListener('customValidation', (event) => {
        console.log("customValidation");
        // Ensure calculations are up to date before validation
        if (instance && typeof instance.recalculate === 'function') {
            instance.recalculate();
        }
        if (instance && typeof instance.validate === 'function') {
            const ok = instance.validate();
            if (!ok) {
                event.preventDefault();
                return false;
            }
        }
        return true;
    });

    form.dataset.milkFormBound = '1';
}

/**
 * Trova il container corretto per appendere l'errore
 */
function getFieldContainer(field) {
    return field.closest('.form-floating, .mb-3, .col, .form-check') || field.parentElement;
}

function findContainer(field) {
    // closest NON supporta selettori multipli, quindi li testiamo uno per uno
    const selectors = ['.form-floating', '.mb-3', '.col', '.form-check'];

    for (const sel of selectors) {
        const el = field.closest(sel);
        if (el) return el;
    }

    return field.parentElement; // fallback
}

function updateRequiredCheckboxGroupFeedback(field) {
    if (!field || field.type !== 'checkbox') return false;

    const group = field.closest('.js-form-checkboxes-group[data-required-group="checkboxes"]');
    if (!group) return false;

    const form = field.form || group.closest('form');
    const showValidationUi = !!(form && form.classList.contains('was-validated'));
    const activeInputs = Array.from(group.querySelectorAll('input[type="checkbox"]:not([disabled])'));

    if (!activeInputs.length) {
        group.classList.remove('is-invalid');
        return true;
    }

    const hasChecked = activeInputs.some((input) => input.checked);

    if (showValidationUi && !hasChecked) {
        activeInputs.forEach((input) => input.classList.add('is-invalid'));
        group.classList.add('is-invalid');
    } else {
        activeInputs.forEach((input) => input.classList.remove('is-invalid'));
        group.classList.remove('is-invalid');
    }

    return true;
}

function updateInvalidFeedback(field) {
    if (!field || !field.matches || !field.matches('input, select, textarea')) return;
    if (field.type === 'hidden') return;

    if (updateRequiredCheckboxGroupFeedback(field)) return;
 
    const container = findContainer(field);
    if (!container) return;

    const milkselectWrapper = field.closest('.cs-autocomplete-wrapper-single, .cs-autocomplete-wrapper-multiple');
 
    // Cerca solo feedback diretti nel container
    let feedback = container.querySelector('.invalid-feedback');
    const hasPresetFeedback = !!(feedback && feedback.innerHTML && feedback.innerHTML.trim().length > 0);

    if (!field.checkValidity()) {

        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            container.appendChild(feedback);
        }

        if (!hasPresetFeedback) {
            const message =
                field.dataset.errorMessage ||
                field.validationMessage ||
                '';
            feedback.textContent = message;
        }
        field.classList.add('is-invalid');
        if (milkselectWrapper) {
            milkselectWrapper.classList.add('is-invalid');
            container.classList.add('is-invalid');
        }

    } else {
        field.classList.remove('is-invalid');
        if (milkselectWrapper) {
            milkselectWrapper.classList.remove('is-invalid');
            container.classList.remove('is-invalid');
        }
        if (feedback) feedback.textContent = '';
    }
}

function replaceFormHtml(formHtml) {
    if (!formHtml) return;

    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = formHtml;
    const newForm = tempDiv.querySelector('form');
    if (!newForm) return;

    const formId = newForm.getAttribute('id');
    let currentForm = null;
    if (formId) {
        currentForm = document.getElementById(formId);
    }
    if (!currentForm) {
        currentForm = document.querySelector('form.js-needs-validation');
    }
    if (!currentForm) return;

    currentForm.replaceWith(newForm);
    if (typeof updateContainer === 'function') {
        updateContainer(newForm);
    }
    console.log ("REPLACE FORM HTML");
    initReadonlySelects(newForm);
    initReadonlyChoices(newForm);
    initRequiredCheckboxGroups(newForm);
    // Re-init MilkForm on replaced form
    initMilkForm(newForm);
    initEnterNavigation(newForm);
}

function milkFormReload(button) {
    if (!button) return;
    const form = button.closest('form');
    if (!form) return;

    let reloadSubmit = form.querySelector('[data-reload-submit="1"]');
    if (!reloadSubmit) {
        reloadSubmit = document.createElement('button');
        reloadSubmit.type = 'submit';
        reloadSubmit.name = 'reload';
        reloadSubmit.value = '1';
        reloadSubmit.setAttribute('data-reload-submit', '1');
        reloadSubmit.style.display = 'none';
        form.appendChild(reloadSubmit);
    }

    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit(reloadSubmit);
    } else {
        reloadSubmit.click();
    }
}

function setFormSubmit(form) {

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const isReload = event.submitter?.name === 'reload';
        const skipValidation = isReload || event.submitter?.hasAttribute('formnovalidate');
        let isValid = true;
        if (!skipValidation) {
            form.classList.add('was-validated');

            // Valida tutti i campi
            form.querySelectorAll('input, select, textarea').forEach(field => {
                field.dispatchEvent(new CustomEvent('fieldValidation', {
                    detail: { form, field },
                    cancelable: true
                }));
                updateInvalidFeedback(field);
            });
            console.log ("setFormSubmit");
            const customValidationEvent = new CustomEvent('customValidation', {
                detail: { form },
                cancelable: true
            });
            const customValidationPassed = form.dispatchEvent(customValidationEvent);

            isValid = form.checkValidity() && customValidationPassed;

            if (!isValid) {
                event.stopPropagation();
                form.classList.add('was-validated');
                return;
            }
        }

        // Before submit hook
        form.dispatchEvent(new CustomEvent('beforeFormSubmit', {
            detail: { form }
        }));

        // Aggiungi hidden input del submitter
        if (event.submitter && event.submitter.name) {
            let hidden = form.querySelector(`input[name="${event.submitter.name}"]`);
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = event.submitter.name;
                hidden.value = event.submitter.value;
                form.appendChild(hidden);
            }
        }

        const ajaxSubmit = form.dataset.ajaxSubmit === 'true' || isReload;

        if (ajaxSubmit) {
            if (window.plugin_loading) window.plugin_loading.show();

            try {
                const formData = new FormData(form);
                if (isReload && !formData.has('page-output')) {
                    formData.append('page-output', 'json');
                }
                const response = await fetch(form.action, {
                    method: form.method || 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error(`HTTP error: ${response.status}`);

                const data = await response.json();
                jsonAction(data, form.parentNode);
                if (isReload && data.form) {
                    replaceFormHtml(data.form);
                }

            } catch (error) {
                console.error(error);
                window.toasts?.show('Form submit error: ' + error, 'danger');
            } finally {
                if (window.plugin_loading) window.plugin_loading.hide();
            }

        } else {
            form.submit();
        }

        form.classList.add('was-validated');
    });

    // Revalidate live (input/change)
    const revalidateField = (e) => {
        const field = e.target;
        if (!field || !field.matches || !field.matches('input, select, textarea')) {
            return;
        }

        if (form.classList.contains('was-validated')) {
            field.dispatchEvent(new CustomEvent('fieldValidation', {
                detail: { form, field },
                cancelable: true,
                bubbles: true
            }));
        }

        updateInvalidFeedback(field);
    };

    form.addEventListener('input', revalidateField);
    form.addEventListener('change', revalidateField);


}

/**
 * Initialize Enter key navigation for form fields
 * Prevents form submission when pressing Enter in input fields,
 * instead moving focus to the next field
 * 
 * @param {HTMLFormElement} form - The form element to initialize
 */
function initEnterNavigation(form) {
    if (!form || form.dataset.enterNavigationBound === '1') return;
    
    form.addEventListener('keydown', function(event) {
        // Only handle Enter key
        if (event.key !== 'Enter') return;
        
        const target = event.target;
        
        // Cases where Enter should work normally (do not navigate)
        // 1. Submit buttons - Enter should submit the form
        if (target.matches('button[type="submit"], input[type="submit"]')) {
            return;
        }
        
        // 2. Textarea with Shift+Enter - should insert newline
        if (target.matches('textarea') && event.shiftKey) {
            return;
        }
        
        // 3. Trix Editor rich text editor - let it handle Enter
        if (target.closest('.trix-content, trix-editor')) {
            return;
        }
        
        // 4. File uploader component - let it handle Enter
        if (target.closest('.js-file-uploader, .js-image-uploader')) {
            return;
        }
        
        // 5. MilkSelect autocomplete - let it handle Enter for selection
        if (target.closest('.cs-autocomplete-container, .cs-autocomplete-wrapper-single, .cs-autocomplete-wrapper-multiple')) {
            return;
        }
        
        // 6. Ctrl+Enter can be used for quick submit
        if (event.ctrlKey) {
            return;
        }
        
        // Find next focusable field for navigation
        // Supported field types: text, email, password, number, date, tel, url, search, select
        if (!target.matches('input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="image"]):not([disabled]):not([readonly]), select:not([disabled]):not([readonly]), textarea:not([disabled]):not([readonly])')) {
            return;
        }

        // Always block native Enter submit while typing in managed fields.
        event.preventDefault();
        event.stopPropagation();

        // Get all focusable elements in the form
        const allFocusable = form.querySelectorAll(
            'input:not([type="hidden"]):not([disabled]):not([readonly]), ' +
            'select:not([disabled]):not([readonly]), ' +
            'textarea:not([disabled]):not([readonly]), ' +
            'button:not([disabled]):not([type="submit"])'
        );

        // Filter out elements inside custom components that handle their own Enter behavior
        const validElements = Array.from(allFocusable).filter(el => {
            // Skip elements inside custom components
            if (el.closest('.js-file-uploader, .js-image-uploader')) return false;
            if (el.closest('.cs-autocomplete-container')) return false;
            if (el.closest('.trix-content, trix-editor')) return false;

            // Skip hidden fields (elements with hidden type but inside containers)
            if (el.type === 'hidden') return false;

            return true;
        });

        // Find current element index
        const currentIndex = validElements.indexOf(target);

        // If found and there's a next element, move focus to it
        if (currentIndex !== -1 && currentIndex < validElements.length - 1) {
            const nextElement = validElements[currentIndex + 1];

            // Focus the next element
            nextElement.focus();

            // If it's an input, select the text for easier editing
            if (nextElement.matches('input[type="text"], input[type="email"], input[type="password"], input[type="search"]')) {
                nextElement.select();
            }
        }
    });
    
    // Mark form as initialized
    form.dataset.enterNavigationBound = '1';
}
