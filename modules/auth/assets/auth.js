/**
 * Javascript per le pagine page-login e page-choose-new-password.php 
 */
(function () {
    'use strict'
    //pagina page-choose-new-password.php 
    const newPwdForm = document.getElementById('chooseNewPwdValidation');
    if (newPwdForm) {
        newPwdForm.addEventListener('submit', function (event) {
            checkPasswords();
            if (!newPwdForm.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }

            newPwdForm.classList.add('was-validated')
        }, false)


        document.getElementById('newPasswordConfirm').addEventListener('input', function() {
            checkPasswords()
        });
        document.getElementById('newPassword').addEventListener('input', function() {
            checkPasswords()
        });
        
        function checkPasswords() {
            const password = document.getElementById('newPassword')
            const passwordPattern = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*()_+{}\[\]:;<>,.?~\\/-])[A-Za-z\d!@#$%^&*()_+{}\[\]:;<>,.?~\\/-]{8,}$/
            if (!passwordPattern.test(password.value)) {
                password.setCustomValidity('Password must be at least 8 characters long, contain at least one uppercase letter, one lowercase letter, one number and one special character.')
            } else { 
                    password.setCustomValidity('')
            }
            const confirmPassword = document.getElementById('newPasswordConfirm')
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match')
            } else {
                confirmPassword.setCustomValidity('')
            }
        }
    }  
})()


/**
 * Create new user
 */
function create_new_user() {
    window.offcanvasEnd.show();
    window.offcanvasEnd.loading_show();
    // Esempio di un nuovo script
    //alert('Nuovo script eseguito dopo l\'apertura dell\'offcanvas');
    formData = new FormData();
    formData.append('action', 'edit-form');
    formData.append('page', 'auth');
    formData.append('page-output', 'json');
    //MANCA IL TOKEN!!! Però c'è credentials: 'same-origin'
    fetch(milk_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    }).then((response) => {
        window.offcanvasEnd.loading_hide();
        return response.json();
    }).then((data) => {
        window.offcanvasEnd.body(data.html);
        active_form_js();
        window.offcanvasEnd.title(data.title);
    })
}


/**
 * Attivo l'edit
 */
registerHook('table-action-edit', function (id, sendform) {
    console.log('table-action-edit');
    window.offcanvasEnd.show();
    window.offcanvasEnd.loading_show();
    // Esempio di un nuovo script
    //alert('Nuovo script eseguito dopo l\'apertura dell\'offcanvas');
    formData = new FormData();
    formData.append('id', id);
    formData.append('action', 'edit-form');
    formData.append('page', 'auth');
    formData.append('page-output', 'json');
    //MANCA IL TOKEN!!! Però c'è credentials: 'same-origin'
    fetch(milk_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    }).then((response) => {
        window.offcanvasEnd.loading_hide();
        return response.json();
    }).then((data) => {
        window.offcanvasEnd.body(data.html);
        active_form_js();
        window.offcanvasEnd.title(data.title);
    })
    // non aggiorna la tabella
    return false;
});



registerHook('table-action-trash', function (id, elclick, form, sendform) {
    return confirm('Are you sure you want to trash this user?');
});
registerHook('table-action-delete', function (id, elclick, form, sendform) {
    return confirm('Are you sure you want to delete this user? This action cannot be undone.');
});


function saveUser() {
    let form = document.getElementById('editUserForm');
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        window.toasts.show('Check the form fields', 'error');
         
        // Scorri al primo campo con errore
        const firstInvalid = form.querySelector(':invalid');
        if (firstInvalid) {
            firstInvalid.focus();
        }
        return;
    }
    window.offcanvasEnd.loading_show();

    //alert('Salvataggio utente');
    // Esempio di un nuovo script
    //alert('Nuovo script eseguito dopo l\'apertura dell\'offcanvas');
    formData = new FormData(form);
    fetch(form.getAttribute('action'), {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    }).then((response) => {
       
        return response.json();
    }).then((data) => {
        console.log(data);
        if (data.success) {
            window.toasts.body(data.msg, 'success');
            window.toasts.show();
            setTimeout(function() {window.offcanvasEnd.hide(); },500)
            getComponent('userList').reload();
            
        } else {
            window.toasts.body(data.msg, 'danger');
            window.toasts.show();
            window.offcanvasEnd.loading_hide();
        }
    })

}


function deleteUser(confirm_bool) {
    if (confirm_bool) {
        condition = confirm('Are you sure you want to delete this user? This action cannot be undone.');
        if (!condition) {
            return;
        }
    }
    let form = document.getElementById('editUserForm');
    document.getElementById('actionUser').value = 'delete-user';
    formData = new FormData(form);
    fetch(form.getAttribute('action'), {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    }).then((response) => {
        return response.json();
    }).then((data) => {
        console.log(data);
        if (data.success) {
            window.toasts.body(data.msg, 'success');
            window.toasts.show();
            setTimeout(function() {window.offcanvasEnd.hide(); },500)
            getComponent('userList').reload();
            
        } else {
            window.toasts.body(data.msg, 'danger');
            window.toasts.show();
            window.offcanvasEnd.loading_hide();
        }
    })
}


function filterStatus(type) {
    comp_table = getComponent('userList');
    comp_table.filter_remove_start('status:');
   
    comp_table.filter_add('status:' + type);
    
    comp_table.set_page(1);
    comp_table.reload();
}

function setActiveFilter(element) {
    // Remove active class from all filters
    document.querySelectorAll('.js-filter-action').forEach(function(el) {
        el.classList.remove('active-filter');
    });
    
    // Add active class to clicked filter
    element.classList.add('active-filter');
}


function search() {
    comp_table = getComponent('userList');
    let val =  document.getElementById('searchUser').value;
    comp_table.filter_remove_start('search:');
    if (val != '') {
        comp_table.filter_add('search:' + val);
    }
    comp_table.set_page(1);
    comp_table.reload();
}

/**
 * Viene chiamata ogni volta che viene caricata la form dell'edit 
 */
function active_form_js() {
    sendEmail = document.getElementById('sendEmail');
    // è un checkbox se clicco sul checkbox disabilito il campo cambia password id=changePassword
    if (sendEmail) {
        sendEmail.addEventListener('change', function () {
            if (sendEmail.checked) {
                document.getElementById('changePassword').value = '';
                document.getElementById('changePassword').disabled = true;
            } else {
                document.getElementById('changePassword').disabled = false;
            }
        });
    }

    isAdmin = document.getElementById('isAdmin');
    if (isAdmin) {
        isAdmin.addEventListener('change', () => permission_toogle());
        permission_toogle();
    }
    
    initExclusivePermissions();
}


/**
 * Handles exclusive permission groups
 * When a checkbox in an exclusive group is checked, all other checkboxes in the same group are unchecked
 * This function is exposed globally so it can be called after dynamic form updates
 */
function initExclusivePermissions() {
    const exclusivePermissions = document.querySelectorAll('.exclusive-permission');
    
    // Remove any existing event listeners (to prevent duplicates)
    exclusivePermissions.forEach(checkbox => {
        checkbox.removeEventListener('change', handleExclusivePermissionChange);
        checkbox.addEventListener('change', handleExclusivePermissionChange);
    });
}

function handleExclusivePermissionChange(event) {
    if (this.checked) {
        const group = this.getAttribute('data-group');
        const groupCheckboxes = document.querySelectorAll(`.exclusive-permission[data-group="${group}"]`);
        
        groupCheckboxes.forEach(otherCheckbox => {
            if (otherCheckbox !== this) {
                otherCheckbox.checked = false;
            }
        });
    }
}




function permission_toogle() {
    if (document.getElementById('isAdmin').checked) {
        document.getElementById('permissionsBlock').style.display = 'none';
    } else {
        document.getElementById('permissionsBlock').style.display = 'block';
    }
}

/**
 * Show page activity details in offcanvas
 */
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('js-show-page-activity')) {
        e.preventDefault();
        showPageActivityDetails(e.target);
    }
});

function showPageActivityDetails(button) {
    const pagesData = button.getAttribute('data-pages-data');
    
    // Show offcanvas with loading
    window.offcanvasEnd.show();
    window.offcanvasEnd.loading_show();
    window.offcanvasEnd.title('Page Activity Details');
    
    // Send request to backend to format the data
    const formData = new FormData();
    formData.append('pages_data', pagesData);
    formData.append('action', 'format-page-activity');
    formData.append('page', 'auth');
    
    fetch(milk_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        window.offcanvasEnd.loading_hide();
        
        if (data.success) {
            window.offcanvasEnd.body(data.html);
        } else {
            window.offcanvasEnd.body('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error loading page activity details: ' + (data.error || 'Unknown error') + '</div>');
        }
    })
    .catch(error => {
        window.offcanvasEnd.loading_hide();
        console.error('Error loading page activity details:', error);
        window.offcanvasEnd.body('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error loading page activity details</div>');
    });
}