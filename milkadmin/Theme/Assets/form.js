(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const forms = document.querySelectorAll('.js-needs-validation');
        Array.prototype.slice.call(forms).forEach(setFormSubmit);

        if (typeof removeIsInvalid === 'function') {
            removeIsInvalid();
        }
    });
})();

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
 
    const container = findContainer(field);
    if (!container) return;
 
    // Cerca solo feedback diretti nel container
    let feedback = container.querySelector('.invalid-feedback');

    if (!field.checkValidity()) {

        const message =
            field.validationMessage ||
            field.dataset.errorMessage ||
            '';

        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            container.appendChild(feedback);
        }

        feedback.textContent = message;
        field.classList.add('is-invalid');

    } else {
        field.classList.remove('is-invalid');
        if (feedback) feedback.textContent = '';
    }
}

function setFormSubmit(form) {

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const skipValidation = event.submitter?.hasAttribute('formnovalidate');
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

        const ajaxSubmit = form.dataset.ajaxSubmit === 'true';

        if (ajaxSubmit) {
            if (window.plugin_loading) window.plugin_loading.show();

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: form.method || 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                if (!response.ok) throw new Error(`HTTP error: ${response.status}`);

                const data = await response.json();
                jsonAction(data, form.parentNode);

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
