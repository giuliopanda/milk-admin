/**
 * Classe per gestire la mappatura dei campi tra CSV e tabella target
 */
class FieldMapper {
    constructor() {
        this.csvFields = [];
        this.targetFields = [];
        this.mappingTable = null;
        this.mappingContainer = document.getElementById('fieldMappingContainer');
    }

    /**
     * Imposta i campi CSV e aggiorna la tabella di mappatura
     * @param {Array} fields - Array di nomi dei campi CSV
     */
    setCsvFields(fields) {
        console.log('Setting CSV fields:', fields);
        this.csvFields = fields;
        this.updateMappingTable();
    }

    /**
     * Ottiene i campi della tabella target tramite chiamata AJAX e aggiorna la tabella di mappatura
     * @param {string} tableName - Nome della tabella target
     * @returns {Promise} - Promise che si risolve quando i campi sono stati ottenuti
     */
    async setTargetTable(tableName) {
        console.log('Setting target table:', tableName);
        if (!tableName) {
            this.targetFields = [];
            this.updateMappingTable();
            return;
        }

        try {
            const response = await fetch(`?page=db2tables&action=get_table_fields&table=${encodeURIComponent(tableName)}`);
            const data = await response.json();
            if (data.success) {
                console.log('Received fields data:', data.fields);
                
                // Gestisce sia il caso in cui fields è un array che il caso in cui è un oggetto
                if (Array.isArray(data.fields)) {
                    this.targetFields = data.fields;
                } else if (typeof data.fields === 'object' && data.fields !== null) {
                    // Se fields è un oggetto, estrai le chiavi
                    this.targetFields = Object.keys(data.fields);
                } else {
                    this.targetFields = [];
                    console.error('Unexpected format for fields:', data.fields);
                }
                
                console.log('Processed target fields:', this.targetFields);
                this.updateMappingTable();
            } else {
                console.error('Failed to fetch table fields:', data.error);
                // Mostra errore all'utente
                window.toasts.show('Errore nel recupero dei campi della tabella: ' + data.error, 'danger');
            }
        } catch (error) {
            console.error('Error fetching table fields:', error);
            window.toasts.show('Errore di connessione nel recupero dei campi della tabella', 'danger');
        }
    }

    /**
     * Aggiorna la tabella di mappatura tra campi CSV e campi della tabella target
     */
    updateMappingTable() {
        if (!this.mappingContainer) {
            console.error('Mapping container not found!');
            return;
        }

        // Pulisce la tabella esistente
        this.mappingContainer.innerHTML = '';

        // Verifica che abbiamo campi target e campi CSV
        if (!this.targetFields || !this.csvFields) {
            console.error('Target fields or CSV fields are undefined', {
                targetFields: this.targetFields,
                csvFields: this.csvFields
            });
            return;
        }

        // Verifica che i campi non siano vuoti
        if (!this.targetFields.length || !this.csvFields.length) {
            console.log('Not showing mapping table - missing fields', {
                targetFields: this.targetFields.length,
                csvFields: this.csvFields.length
            });
            return;
        }

        console.log('Updating mapping table with', this.targetFields.length, 'target fields and', this.csvFields.length, 'CSV fields');

        // Crea la tabella
        const table = document.createElement('table');
        table.className = 'table table-striped mt-3';
        this.mappingTable = table;

        // Aggiungi l'intestazione
        const thead = document.createElement('thead');
        thead.innerHTML = `
            <tr>
                <th>Campo Tabella Target</th>
                <th>Campo CSV</th>
            </tr>
        `;
        table.appendChild(thead);

        // Aggiungi il corpo
        const tbody = document.createElement('tbody');
        this.targetFields.forEach(field => {
            const tr = document.createElement('tr');
            
            // Trova un campo CSV corrispondente (match esatto o case-insensitive)
            let matchedCsvField = '';
            for (const csvField of this.csvFields) {
                if (csvField === field || csvField.toLowerCase() === field.toLowerCase()) {
                    matchedCsvField = csvField;
                    break;
                }
            }
            
            tr.innerHTML = `
                <td>${field}</td>
                <td>
                    <select class="form-select" name="field_map[${field}]">
                        <option value="">-- Skip --</option>
                        ${this.csvFields.map(csvField => 
                            `<option value="${csvField}"${csvField === matchedCsvField ? ' selected' : ''}>${csvField}</option>`
                        ).join('')}
                    </select>
                </td>
            `;
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);

        // Aggiungi una nota informativa
        const infoDiv = document.createElement('div');
        infoDiv.className = 'alert alert-info mt-2';
        infoDiv.innerHTML = `<small>Seleziona a quale campo CSV corrisponde ogni campo della tabella target. I campi sono stati associati automaticamente dove possibile.</small>`;
        
        // Aggiungi la tabella e l'informazione al container
        this.mappingContainer.appendChild(table);
        this.mappingContainer.appendChild(infoDiv);
    }

    /**
     * Ottiene le mappature configurate
     * @returns {Object} - Oggetto con le mappature (chiave: campo target, valore: campo CSV)
     */
    getMappings() {
        if (!this.mappingTable) return {};

        const mappings = {};
        const selects = this.mappingTable.querySelectorAll('select');
        selects.forEach(select => {
            const targetField = select.name.match(/\[(.*?)\]/)[1];
            mappings[targetField] = select.value;
        });
        return mappings;
    }
}

// Variabili globali
let csvFileContents = null;
const fieldMapper = new FieldMapper();

/**
 * Funzione principale eseguita quando il DOM è pronto
 */
document.addEventListener('DOMContentLoaded', function() {
    // Elementi DOM
    const importType = document.getElementById('importType');
    const existingTableSection = document.getElementById('existingTableSection');
    const newTableSection = document.getElementById('newTableSection');
    const targetTable = document.getElementById('targetTable');
    const importFile = document.getElementById('importFile');
    const csvOptionsSection = document.getElementById('csvOptionsSection');
    const csvSeparator = document.getElementById('csvSeparator');
    const primaryKeyOptions = document.getElementById('primaryKeyOptions');
    const primaryKeyField = document.getElementById('primaryKeyField');
    const autoIncrementId = document.getElementById('autoIncrementId');
    const useExistingField = document.getElementById('useExistingField');
    const existingFieldSelector = document.getElementById('existingFieldSelector');
    const uniqueFieldType = document.getElementById('uniqueFieldType');
    const importBtn = document.getElementById('importBtn');
    const importForm = document.getElementById('importForm');
    const skipFirstRow = document.getElementById('skipFirstRow');

    // Inizializzazione
    initializePrimaryKeyOptions();
    initializeImportTypeToggle();
    initializeFileUpload();
    initializeCsvSeparatorHandler();
    initializeTargetTableHandler();

    // Nascondi inizialmente le sezioni che devono apparire solo dopo il caricamento del file
    const importTypeSection = document.getElementById('importTypeSection');
    const importOptionsSection = document.getElementById('importOptionsSection');
    if (importTypeSection) importTypeSection.classList.add('d-none');
    if (importOptionsSection) importOptionsSection.classList.add('d-none');
    initializeImportButton();

    /**
     * Inizializza le opzioni della chiave primaria
     */
    function initializePrimaryKeyOptions() {
        if (autoIncrementId && useExistingField) {
            autoIncrementId.addEventListener('change', function() {
                if (this.checked) {
                    existingFieldSelector.classList.add('d-none');
                }
            });
            
            useExistingField.addEventListener('change', function() {
                if (this.checked) {
                    existingFieldSelector.classList.remove('d-none');
                }
            });
            
            // Imposta il valore predefinito per uniqueFieldType
            if (uniqueFieldType) {
                uniqueFieldType.value = 'unique';
            }
        }
    }

    /**
     * Inizializza il toggle del tipo di importazione (nuova tabella/tabella esistente)
     */
    function initializeImportTypeToggle() {
        // Nascondi inizialmente sezioni non necessarie
        existingTableSection.classList.add('d-none');
        primaryKeyOptions.classList.add('d-none');
        importType.addEventListener('change', function() {
            if (this.value === 'existing') {
                existingTableSection.classList.remove('d-none');
                newTableSection.classList.add('d-none');
                primaryKeyOptions.classList.add('d-none');
                
                // Se abbiamo già i campi CSV e una tabella target selezionata, aggiorna la mappatura
                if (csvFileContents && targetTable.value) {
                    fieldMapper.setTargetTable(targetTable.value);
                }
            } else {
                existingTableSection.classList.add('d-none');
                newTableSection.classList.remove('d-none');
                
                // Mostra opzioni chiave primaria solo se abbiamo un file caricato
                if (csvFileContents) {
                    primaryKeyOptions.classList.remove('d-none');
                }
            }
        });
        skipFirstRow.addEventListener('change', function() {
            if (this.checked) {
                primaryKeyOptions.classList.remove('d-none');
            } else {
                primaryKeyOptions.classList.add('d-none');
            }
        });
    }

    /**
     * Inizializza il gestore del cambio tabella target
     */
    function initializeTargetTableHandler() {
        if (targetTable) {
            targetTable.addEventListener('change', function() {
                console.log('Target table changed to:', this.value);
                
                // Se abbiamo già caricato un file CSV, aggiorna la mappatura
                if (csvFileContents) {
                    const separator = csvSeparator.value || detectCsvSeparator(csvFileContents);
                    const headers = parseCSVHeaders(csvFileContents, separator);
                    
                    // Prima imposta i campi CSV, poi richiedi i campi della tabella target
                    console.log('Setting CSV fields before fetching table fields');
                    fieldMapper.setCsvFields(headers);
                    
                    // Ora richiedi i campi della tabella target
                    if (this.value) {
                        console.log('Fetching target table fields for:', this.value);
                        fieldMapper.setTargetTable(this.value);
                    }
                } else {
                    // Se non abbiamo ancora caricato un file CSV, avvisa l'utente
                    console.log('No CSV file loaded, showing warning');
                    window.toasts.show('Carica prima un file CSV per visualizzare la mappatura dei campi', 'warning');
                }
            });
        }
    }

    /**
     * Inizializza il gestore del cambio separatore CSV
     */
    function initializeCsvSeparatorHandler() {
        if (csvSeparator) {
            csvSeparator.addEventListener('input', function() {
                // Quando il separatore cambia, rianalizza e convalida CSV
                if (csvFileContents) {
                    const separator = this.value;
                    const rows = parseCSV(csvFileContents, separator);
                    const validation = validateCSV(rows);
                    
                    // Gestisci la visualizzazione degli avvisi
                    handleCsvValidation(validation);
                    
                    if (validation.isValid) {
                        // Aggiorna i campi CSV con il nuovo separatore
                        const headers = parseCSVHeaders(csvFileContents, separator);
                        console.log('CSV headers with new separator:', headers);
                        
                        // Aggiorna i campi della chiave primaria e della mappatura
                        updatePrimaryKeyFields(csvFileContents, separator);
                        fieldMapper.setCsvFields(headers);
                        
                        // Se siamo in modalità tabella esistente e abbiamo selezionato una tabella, aggiorna la mappatura
                        if (importType.value === 'existing' && targetTable.value) {
                            fieldMapper.setTargetTable(targetTable.value);
                        }
                    }
                }
            });
        }
    }

    /**
     * Inizializza il gestore dell'upload del file
     */
    function initializeFileUpload() {
        // svuota il campo file
        importFile.value = '';
        if (importFile) {
            importFile.addEventListener('change', handleFileChange);
        }
    }

    /**
     * Gestisce il cambio del file
     */
    function handleFileChange() {
        console.log('File change event triggered');
        // Reimposta lo stato precedente
        csvFileContents = null;
        resetFormState();
        
        if (this.files.length > 0) {
            const file = this.files[0];
            
            // Controlla se è un file CSV
            if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
                showImportError('Solo i file CSV sono supportati');
                return;
            }
            
            // Mostra opzioni CSV quando viene caricato un file
            if (csvOptionsSection) {
                csvOptionsSection.classList.remove('d-none');
            }
            
            // Leggi il file per rilevare il separatore e analizzare le intestazioni
            const reader = new FileReader();
            reader.onload = function(e) {
                console.log('File read complete');
                // Memorizza i contenuti del file globalmente
                csvFileContents = e.target.result;
                
                // Rileva il separatore
                const separator = detectCsvSeparator(csvFileContents);
                
                // Imposta il separatore rilevato nel campo di input
                if (csvSeparator) {
                    csvSeparator.value = separator;
                }
                
                // Analizza e convalida CSV
                const rows = parseCSV(csvFileContents, separator);
                const validation = validateCSV(rows);
                
                // Gestisci la visualizzazione degli avvisi
                if (!handleCsvValidation(validation)) return;
                
                // Mostra le sezioni nascoste dopo che il file è stato caricato e validato
                const importTypeSection = document.getElementById('importTypeSection');
                const importOptionsSection = document.getElementById('importOptionsSection');
                if (importTypeSection) importTypeSection.classList.remove('d-none');
                if (importOptionsSection) importOptionsSection.classList.remove('d-none');
                if (newTableSection) newTableSection.classList.remove('d-none');
                
                // Mostra opzioni chiave primaria quando il file è valido
                if (primaryKeyOptions && importType.value === 'new') {
                    primaryKeyOptions.classList.remove('d-none');
                }
                
                // Aggiorna i campi della chiave primaria in base al separatore rilevato
                updatePrimaryKeyFields(csvFileContents, separator);
                
                // Estrai le intestazioni e aggiorna la classe FieldMapper
                const headers = parseCSVHeaders(csvFileContents, separator);
                console.log('CSV headers:', headers);
                fieldMapper.setCsvFields(headers);
                
                // Se siamo in modalità tabella esistente e abbiamo selezionato una tabella, aggiorna la mappatura
                if (importType.value === 'existing' && targetTable.value) {
                    fieldMapper.setTargetTable(targetTable.value);
                }
            };
            
            // Leggi come testo
            reader.readAsText(file);
        }
    }

    /**
     * Reimposta lo stato del form
     */
    function resetFormState() {
        // Reimposta le opzioni della chiave primaria
        if (primaryKeyField) {
            primaryKeyField.innerHTML = '<option value="">Carica un file CSV per vedere i campi disponibili</option>';
        }
        if (uniqueFieldType) {
            uniqueFieldType.value = 'unique';
        }
        
        // Reimposta i radio button
        if (autoIncrementId && useExistingField) {
            autoIncrementId.checked = true;
            useExistingField.checked = false;
            if (existingFieldSelector) {
                existingFieldSelector.classList.add('d-none');
            }
        }
        
        // Rimuovi eventuali messaggi di errore precedenti
        const csvAlert = document.getElementById('csvAlert');
        if (csvAlert) {
            csvAlert.remove();
        }
        
        // Abilita pulsante di importazione per impostazione predefinita
        if (importBtn) {
            importBtn.disabled = false;
            importBtn.textContent = 'Importa';
        }
    }

    /**
     * Gestisce la validazione del CSV
     * @param {Object} validation - Risultato della validazione
     * @returns {boolean} - true se il CSV è valido, false altrimenti
     */
    function handleCsvValidation(validation) {
        let csvAlert = document.getElementById('csvAlert');
        if (!csvAlert) {
            csvAlert = document.createElement('div');
            csvAlert.id = 'csvAlert';
            // Inserisci dopo la sezione delle opzioni CSV
            if (csvOptionsSection) {
                csvOptionsSection.parentNode.insertBefore(csvAlert, csvOptionsSection.nextSibling);
            }
        }
        
        if (!validation.isValid) {
            // Mostra messaggio di errore
            csvAlert.className = 'alert alert-danger';
            csvAlert.textContent = validation.message;
            if (importBtn) importBtn.disabled = true;
            return false;
        } else {
            // Rimuovi messaggio di errore se esiste
            csvAlert.remove();
            if (importBtn) importBtn.disabled = false;
            return true;
        }
    }

    /**
     * Inizializza il pulsante di importazione
     */
    function initializeImportButton() {
        if (importBtn && importForm) {
            console.log('Setting up import button click handler');
            importBtn.addEventListener('click', function(e) {
                console.log('Import button clicked');
                e.preventDefault();
                
                // Valida form
                const importTypeValue = importType.value;
                const newTableName = document.getElementById('newTableName')?.value?.trim();
                const importFile = document.getElementById('importFile').files[0];
                
                console.log('Form validation:', {
                    importType: importTypeValue,
                    newTableName: newTableName,
                    hasFile: !!importFile
                });
                
                // Reimposta messaggio di errore
                const importError = document.getElementById('importError');
                if (importError) {
                    importError.classList.add('d-none');
                    importError.textContent = '';
                }
                
                // Valida file
                if (!importFile) {
                    showImportError('Select a file to import');
                    return;
                }
                
                // Valida tipo di file
                const fileExtension = importFile.name.split('.').pop().toLowerCase();
                if (fileExtension !== 'csv') {
                    showImportError('Only CSV files are supported');
                    return;
                }
                
                // Valida nome tabella per nuova tabella
                if (importTypeValue === 'new' && (!newTableName || !/^[a-z0-9_]+$/.test(newTableName))) {
                    showImportError('Insert a valid table name (only lowercase letters, numbers and underscores)');
                    return;
                }
                
                // Valida che sia stata selezionata una tabella target
                if (importTypeValue === 'existing' && !targetTable.value) {
                    showImportError('Select a target table');
                    return;
                }
                
                // Verifica che le mappature siano complete per tabelle esistenti
                if (importTypeValue === 'existing' && targetTable.value) {
                    const mappings = fieldMapper.getMappings();
                    console.log('Field mappings:', mappings);
                    
                    // Verifica che almeno un campo sia mappato
                    const hasMappings = Object.values(mappings).some(value => value !== '');
                    if (!hasMappings) {
                        showImportError('You must map at least one CSV field to a field of the target table');
                        return;
                    }
                }
                
                // Aggiungi le mappature dei campi al FormData
                const formData = new FormData(importForm);
                
                // Se stiamo importando in una tabella esistente, aggiungi le mappature dei campi
                if (importTypeValue === 'existing' && targetTable.value) {
                    const mappings = fieldMapper.getMappings();
                    formData.append('field_mappings', JSON.stringify(mappings));
                    console.log('Adding field mappings to form data:', mappings);
                }
                
                // Sovrascrivi l'azione del form per utilizzare l'endpoint corretto
                const importUrl = '?page=db2tables&action=import_csv_data';
                
                // Disabilita pulsante e mostra stato di caricamento
                importBtn.disabled = true;
                importBtn.textContent = 'Importing...';
                
                // Invia richiesta
                fetch(importUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Import response:', data);
                    if (data.success) {
                        // Mostra messaggio di successo
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success alert-dismissible fade show';
                        alertDiv.innerHTML = `
                            <strong>Success</strong> ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        `;
                        document.querySelector('#db2tContent').prepend(alertDiv);
                        
                        // Se abbiamo un nome di tabella, reindirizza dopo aver mostrato il messaggio
                        if (data.table_name) {
                            setTimeout(() => {
                                window.location.href = `?page=db2tables&action=view-table&table=${data.table_name}`;
                            }, 2000);
                        } else {
                            // Reimposta lo stato del pulsante dato che non stiamo reindirizzando
                            importBtn.disabled = false;
                            importBtn.textContent = 'Importing...';
                        }
                    } else {
                        // Mostra messaggio di errore
                        const errorMsg = data.error || 'Unable to import data';
                        showImportError(errorMsg);
                        
                        // Reimposta lo stato del pulsante
                        importBtn.disabled = false;
                        importBtn.textContent = 'Importing...';
                    }
                })
                .catch(error => {
                    console.error('Import error:', error);
                    showImportError('An error occurred during data import');
                    
                    // Reimposta lo stato del pulsante
                    importBtn.disabled = false;
                    importBtn.textContent = 'Importing...';
                });
            });
        }
    }
});

/**
 * Detects the CSV separator - simpl<ified version that counts occurrences
 * @param {string} content - CSV file content
 * @returns {string} - Detected separator
 */
function detectCsvSeparator(content) {
    const commonSeparators = [',', ';', '\t', '|'];
    const firstLine = content.split('\n')[0];
    
    let maxCount = 0;
    let detectedSeparator = ','; // Predefinito a virgola
    
    commonSeparators.forEach(separator => {
        const count = (firstLine.match(new RegExp(separator === '\t' ? '\t' : separator.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g')) || []).length;
        if (count > maxCount) {
            maxCount = count;
            detectedSeparator = separator;
        }
    });
    
    return detectedSeparator;
}

/**
 * Analyzes CSV headers with a given separator
 * @param {string} content - CSV file content
 * @param {string} separator - CSV separator
 * @param {string} quoteChar - Quote character
 * @returns {Array} - Array of headers
 */
function parseCSVHeaders(content, separator = ',', quoteChar = '"') {
    if (!content || content.trim() === '') return [];
    
    const firstLine = content.split('\n')[0];
    let headers = [];
    let inQuote = false;
    let currentHeader = '';
    
    for (let i = 0; i < firstLine.length; i++) {
        const char = firstLine[i];
        
        if (char === quoteChar) {
            inQuote = !inQuote;
        } else if (char === separator && !inQuote) {
            // Fine del campo
            headers.push(currentHeader.trim().replace(new RegExp(`^${quoteChar}|${quoteChar}$`, 'g'), ''));
            currentHeader = '';
        } else {
            currentHeader += char;
        }
    }
    
    // Aggiungi l'ultima intestazione
    if (currentHeader) {
        headers.push(currentHeader.trim().replace(new RegExp(`^${quoteChar}|${quoteChar}$`, 'g'), ''));
    }
    
    // Fallback a split semplice se l'analisi è fallita
    if (headers.length === 0) {
        headers = firstLine.split(separator).map(header => header.trim().replace(new RegExp(`^${quoteChar}|${quoteChar}$`, 'g'), ''));
    }
    
    return headers;
}

/**
 * Analizza il contenuto CSV rispettando le citazioni e i campi su più righe
 * @param {string} content - Contenuto del file CSV
 * @param {string} separator - Separatore CSV
 * @param {string} quote - Carattere di citazione
 * @returns {Array} - Array di righe e colonne
 */
function parseCSV(content, separator = ',', quote = '"') {
    console.log('Starting CSV parse with separator:', separator);
    const rows = [];
    let currentRow = [];
    let currentCell = '';
    let inQuotes = false;
    
    for (let i = 0; i < content.length; i++) {
        const char = content[i];
        const nextChar = content[i + 1];
        
        if (char === quote) {
            if (nextChar === quote) {
                // Gestisci citazioni di fuga ("")
                currentCell += quote;
                i++; // Salta la prossima citazione
            } else {
                inQuotes = !inQuotes;
            }
            continue;
        }
        
        if ((char === '\n' || char === '\r') && !inQuotes) {
            if (char === '\r' && nextChar === '\n') {
                i++; // Salta \n in \r\n
            }
            // Fine della riga - aggiungi solo se abbiamo contenuto
            if (currentCell || currentRow.length > 0) {
                currentRow.push(currentCell.trim());
                rows.push(currentRow);
                currentRow = [];
                currentCell = '';
            }
            continue;
        }
        
        if (char === separator && !inQuotes) {
            currentRow.push(currentCell.trim());
            currentCell = '';
            continue;
        }
        
        currentCell += char;
    }
    
    // Gestisci l'ultima cella e riga
    if (currentCell || currentRow.length > 0) {
        currentRow.push(currentCell.trim());
        rows.push(currentRow);
    }
    
    console.log('CSV parsing complete. Found rows:', rows.length);
    
    return rows;
}

/**
 * Convalida CSV
 * @param {Array} rows - Array di righe analizzate
 * @returns {Object} - Oggetto con isValid e message
 */
function validateCSV(rows) {
    if (rows.length < 2) return { isValid: false, message: 'Il file è vuoto o non ha righe di dati' };
    
    const firstRowLength = rows[0].length;
    
    // Controlla se il file ha una sola colonna
    if (firstRowLength === 1) {
        return {
            isValid: false,
            message: 'Il file sembra contenere una sola colonna. Verifica che il separatore CSV sia corretto.'
        };
    }
    
    // Controlla se tutte le righe hanno lo stesso numero di colonne
    for (let i = 1; i < rows.length; i++) {
        if (rows[i].length !== firstRowLength) {
            return {
                isValid: false,
                message: `Struttura CSV non valida: La riga ${i + 1} ha ${rows[i].length} colonne mentre l'intestazione ne ha ${firstRowLength}`
            };
        }
    }
    
    return { isValid: true };
}

/**
 * Controlla se i valori in una colonna sono numeri interi sequenziali partendo da 1
 * @param {string} contents - Contenuto del file CSV
 * @param {string} separator - Separatore CSV
 * @param {number} columnIndex - Indice della colonna da controllare
 * @returns {boolean} - true se i valori sono sequenziali, false altrimenti
 */
function checkSequentialIds(contents, separator, columnIndex) {
    const rows = parseCSV(contents, separator);
    
    if (rows.length < 2) {
        return false; // Servono almeno una riga di dati
    }

    // Salta l'intestazione se necessario
    const skipFirstRow = document.getElementById('skipFirstRow');
    const startIndex = skipFirstRow && skipFirstRow.checked ? 1 : 0;

    // Controlla solo le prime 5 righe per determinare se è sequenziale
    const maxRowsToCheck = Math.min(6, rows.length);

    let expectedId = 1;
    const seenIds = new Set();

    for (let i = startIndex; i < maxRowsToCheck; i++) {
        const row = rows[i];
        
        if (!row[columnIndex]) {
            return false;
        }

        const value = row[columnIndex].trim();
        const numValue = parseInt(value, 10);

        // Controlla se il valore è un numero intero valido e corrisponde alla sequenza prevista
        if (isNaN(numValue) || numValue !== expectedId || seenIds.has(numValue)) {
            return false;
        }

        seenIds.add(numValue);
        expectedId++;
    }

    return true;
}

/**
 * Aggiorna i campi della chiave primaria in base alle intestazioni CSV
 * @param {string} contents - Contenuto del file CSV
 * @param {string} separator - Separatore CSV
 */
function updatePrimaryKeyFields(contents, separator) {
    // Elabora solo se abbiamo contenuti e la casella di controllo delle intestazioni è selezionata
    const skipFirstRow = document.getElementById('skipFirstRow');
    const primaryKeyField = document.getElementById('primaryKeyField');
    
    if (!contents || !skipFirstRow || !skipFirstRow.checked || !primaryKeyField) return;
    
    // Analizza le intestazioni con il separatore dato
    const headers = parseCSVHeaders(contents, separator);
    
    // Cancella le opzioni esistenti
    primaryKeyField.innerHTML = '';
    primaryKeyField.disabled = false;
    
    // Aggiungi opzione predefinita
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = 'Skip';
    primaryKeyField.appendChild(defaultOption);
    
    // Aggiungi opzioni per ogni intestazione
    let hasIdField = false;
    let idFieldIndex = -1;
    
    headers.forEach((header, index) => {
        const option = document.createElement('option');
        option.value = header;
        option.textContent = header;
        primaryKeyField.appendChild(option);
        
        // Controlla se questa intestazione è 'id' o 'ID'
        if (header.toLowerCase() === 'id') {
            hasIdField = true;
            idFieldIndex = index;
        }
    });
    
    // Se abbiamo trovato un campo 'id', preselezionalo e seleziona l'opzione "Usa campo esistente"
    if (hasIdField) {
        // Seleziona il campo ID nel menu a discesa
        primaryKeyField.value = headers[idFieldIndex];
        
        // Ottieni i radio button
        const useExistingField = document.getElementById('useExistingField');
        const autoIncrementId = document.getElementById('autoIncrementId');
        const uniqueFieldType = document.getElementById('uniqueFieldType');
        const existingFieldSelector = document.getElementById('existingFieldSelector');
        
        if (useExistingField && autoIncrementId) {
            // Controlla se il campo id contiene numeri sequenziali
            const isSequential = checkSequentialIds(contents, separator, idFieldIndex);

            // Seleziona l'opzione "Usa campo esistente"
            useExistingField.checked = true;
            autoIncrementId.checked = false;
            
            // Mostra il selettore di campo
            if (existingFieldSelector) {
                existingFieldSelector.classList.remove('d-none');
                
                // Imposta il tipo di campo appropriato in base al controllo sequenziale
                if (uniqueFieldType) {
                    uniqueFieldType.value = isSequential ? 'primary' : 'unique';
                }
            }
        }
    }
}

/**
 * Mostra un errore di importazione
 * @param {string} msg - Messaggio di errore
 */
function showImportError(msg) {
    const importError = document.getElementById('importError');
    if (importError) {
        importError.textContent = msg;
        importError.classList.remove('d-none');
    } else {
        window.toasts.show(msg, 'danger');
    }
}