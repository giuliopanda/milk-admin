(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const forms = document.querySelectorAll('.js-needs-validation');

        Array.prototype.slice.call(forms).forEach(function (form) {
            setFormSubmit(form);
        });

        // Richiamo di eventuale funzione di pulizia
        if (typeof removeIsInvalid === 'function') {
            removeIsInvalid();
        }
    });
})();


function setFormSubmit(form) {
    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        // Verifica se il submitter ha formnovalidate
        const skipValidation = event.submitter && event.submitter.hasAttribute('formnovalidate');

        let isValid = true;

        if (!skipValidation) {
            // Valida tutti i campi al submit
            form.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.dispatchEvent(new CustomEvent('fieldValidation', {
                    detail: { form: form, field: field },
                    cancelable: true
                }));
            });

            const customValidationEvent = new CustomEvent('customValidation', {
                detail: { form: form },
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

        // Validazione passata
        form.dispatchEvent(new CustomEvent('beforeFormSubmit', {
            detail: { form: form }
        }));

        // Crea un input hidden con il nome del submitter se necessario
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
           
            // --- INVIO AJAX CON FETCH ---
            if (window.plugin_loading) window.plugin_loading.show();

            try {
                
                const formData = new FormData(form);
                const response = await fetch(form.getAttribute('action'), {
                    method: form.method || 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const data = await response.json();

                jsonAction(data, form.parentNode);

            } catch (error) {
                console.error('Form AJAX submit failed:', error);
                if (window.toasts)
                    window.toasts.show('An error occurred while submitting the form', 'danger');
            } finally {
                if (window.plugin_loading) window.plugin_loading.hide();
            }

        } else {
            // --- INVIO CLASSICO ---
            form.submit();
        }

        form.classList.add('was-validated');
    }, false);

    // Validazione on input/change dopo was-validated
    form.addEventListener('input', function (e) {
        if (form.classList.contains('was-validated')) {
            e.target.dispatchEvent(new CustomEvent('fieldValidation', {
                detail: { form: form, field: e.target },
                cancelable: true,
                bubbles: true
            }));
        }
    });

    form.addEventListener('change', function (e) {
        if (form.classList.contains('was-validated')) {
            e.target.dispatchEvent(new CustomEvent('fieldValidation', {
                detail: { form: form, field: e.target },
                cancelable: true,
                bubbles: true
            }));
        }
    });
}
