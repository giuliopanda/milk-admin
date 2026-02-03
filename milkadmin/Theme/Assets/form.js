(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const forms = document.querySelectorAll('.js-needs-validation');
        Array.prototype.slice.call(forms).forEach(form => {
            console.log ("DOMContentLoaded");
            initMilkForm(form);
            setFormSubmit(form);
        });

        if (typeof removeIsInvalid === 'function') {
            removeIsInvalid();
        }
    });
})();

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

function updateInvalidFeedback(field) {
    if (field.type === 'hidden') return;
 
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
    // Re-init MilkForm on replaced form
    initMilkForm(newForm);
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
