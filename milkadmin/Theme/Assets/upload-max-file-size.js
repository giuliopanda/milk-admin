window.max_file_size_mb = window.max_file_size_mb || 2;
      

(function() {
    // Converte MB in bytes
    const MAX_FILE_SIZE_BYTES = window.max_file_size_mb * 1024 * 1024;
     
    // Funzione per formattare le dimensioni
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Funzione per controllare un singolo file
    function checkSingleFile(file, input) {
        if (file.size > MAX_FILE_SIZE_BYTES) {
            const fileSize = formatFileSize(file.size);
            const maxSize = formatFileSize(MAX_FILE_SIZE_BYTES);
            window.toasts.show('The file "${file.name}" is too large!\n\nFile size: ${fileSize}\nMax allowed size: ${maxSize}\n\nPlease select a smaller file.', 'error');
            
            input.value = ''; // Svuota l'input
            return false;
        }
        return true;
    }
    
    // Funzione per controllare i file selezionati
    function checkFiles(input) {
        const files = input.files;
        let allValid = true;
        
        if (files && files.length > 0) {
            for (let i = 0; i < files.length; i++) {
                if (!checkSingleFile(files[i], input)) {
                    allValid = false;
                    break; // Interrompe al primo file troppo grande
                }
            }
        }
        
        return allValid;
    }
    
    // Attacca l'event listener a tutti gli input file esistenti
    function attachToExistingInputs() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', function() {
                checkFiles(this);
            });
        });
    }
    
    // Observer per input file aggiunti dinamicamente
    function observeNewInputs() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // ELEMENT_NODE
                        // Controlla se il nodo aggiunto è un input file
                        if (node.tagName === 'INPUT' && node.type === 'file') {
                            node.addEventListener('change', function() {
                                checkFiles(this);
                            });
                        }
                        // Controlla se ci sono input file nei figli del nodo aggiunto
                        const fileInputs = node.querySelectorAll && node.querySelectorAll('input[type="file"]');
                        if (fileInputs) {
                            fileInputs.forEach(input => {
                                input.addEventListener('change', function() {
                                    checkFiles(this);
                                });
                            });
                        }
                    }
                });
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    // Controllo aggiuntivo sui submit (doppia sicurezza)
    function attachSubmitControl() {
        document.addEventListener('submit', function(e) {
            const form = e.target;
            const fileInputs = form.querySelectorAll('input[type="file"]');
            let allValid = true;
            
            fileInputs.forEach(input => {
                if (!checkFiles(input)) {
                    allValid = false;
                }
            });
            
            if (!allValid) {
                e.preventDefault(); // Blocca il submit
                alert('Impossible to send the form: one or more files exceed the maximum allowed size.');
            }
        });
    }
    
    // Inizializzazione quando il DOM è pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            attachToExistingInputs();
            observeNewInputs();
            attachSubmitControl();
        });
    } else {
        // Il DOM è già carico
        attachToExistingInputs();
        observeNewInputs();
        attachSubmitControl();
    }
    
    // Esponi la funzione globalmente per uso esterno (opzionale)
    window.setMaxFileSize = function(sizeInMB) {
        window.MAX_FILE_SIZE_MB = sizeInMB;
        document.getElementById('maxSizeDisplay').textContent = sizeInMB + ' MB';
        console.log('Maximum file size updated to:', sizeInMB, 'MB');
    };
    
})();