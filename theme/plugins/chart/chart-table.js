/**
 * Render Table
 */
class DrawJsTable {
    static presets = {
        default: {
            tableClass: 'table table-striped table-bordered',
            headerClass: '',
            rowClass: '',
            cellClass: '',
            firstHeaderClass: '',
            showLabels: true,
            itemsPerPage: 5,
            number_format: ''
        },
        compact: {
            tableClass: 'table table-sm table-bordered',
            headerClass: 'thead-light',
            rowClass: '',
            cellClass: '',
            firstHeaderClass: 'thead-light',
            showLabels: true,
            itemsPerPage: 5,
            number_format: 'dot'
        },
        dark: {
            tableClass: 'table table-dark table-striped',
            headerClass: 'thead-dark',
            rowClass: '',
            cellClass: 'text-white',
            firstHeaderClass: 'thead-dark',
            showLabels: true,
            itemsPerPage: 5,
            number_format: 'dot'
        },
        hoverable: {
            tableClass: 'table table-hover',
            headerClass: '',
            rowClass: '',
            cellClass: '',
            firstHeaderClass: '',
            showLabels: true,
            itemsPerPage: 5,
            number_format: 'dot'
        },
    };

    constructor(data, containerId, options = {}) {
        this.data = data;
        this.containerId = containerId;
        
        // Salva itemsPerPage direttamente dalle opzioni se presente
        const userItemsPerPage = options.itemsPerPage !== undefined ? 
            parseInt(options.itemsPerPage, 10) : undefined;
        
        // Scegli il preset corretto in base all'opzione preset
        const preset = options.preset ? DrawJsTable.presets[options.preset] : {};
        
        // Merge delle opzioni preservando l'ordine corretto:
        // 1. Default preset
        // 2. Preset specifico (se specificato)
        // 3. Opzioni passate dall'utente
        this.options = Object.assign({}, DrawJsTable.presets.default, preset, options);
        
        // Ripristina il valore di itemsPerPage specificato dall'utente se presente
        if (userItemsPerPage !== undefined) {
            this.options.itemsPerPage = userItemsPerPage;
        }
        
        // Assicurati che itemsPerPage sia un numero
        this.options.itemsPerPage = parseInt(this.options.itemsPerPage, 10);
        
        if (!Array.isArray(this.options.cellClass)) {
            this.options.cellClass = this.options.cellClass;
        }
        
        // Log per debugging
        console.log("DrawJsTable options:", {
            preset: options.preset,
            userItemsPerPage: userItemsPerPage,
            finalItemsPerPage: this.options.itemsPerPage
        });
    }

    getCellClass(columnIndex) {
        return this.options.cellClass[columnIndex] ||  '';
    }

    render() {
        let container = eI('#'+this.containerId, {html: ''});
        if (container.tagName === 'CANVAS') {
            container = eI('div', {replaceChild: container});
            container.id = this.containerId;
        }
       
        container.style.overflow = 'auto';
        
        if (!this.data || !this.data.datasets || !this.data.labels) return;

        const table = eI('table', this.options.tableClass)
        const thead = eI('thead', this.options.headerClass)
        const headRow = eI('tr', this.options.rowClass)

        if (this.options.showLabels) {
            headRow.eI('th', {
                class: this.options.firstHeaderClass || this.getCellClass(0),
                scope: 'col',
                text: this.options.firstCellText || ''
            });
        }

        this.data.datasets.forEach((dataset, index) => {
            headRow.eI('th', {
                class:this.getCellClass(this.options.showLabels ? index + 1 : index),
                scope: 'col',
                text : dataset.label
            });
        });

        table.eI(thead).eI(headRow);

        const tbody = eI('tbody');
       
        this.data.labels.forEach((label, rowIndex) => {
            const row = eI('tr', this.options.rowClass);

            if (this.options.showLabels) {
                row.eI('td', {
                    class: this.options.firstHeaderClass || this.getCellClass(0),
                    scope: 'row',
                    text: label
                });
            }

            this.data.datasets.forEach((dataset, colIndex) => {
                // Aggiungi il suffisso al valore se presente nel dataset
                let cellValue = dataset.data[rowIndex];
                let displayValue = cellValue; // Value to display

                // Format the number if it's numeric
                if (typeof cellValue === 'number') {
                    displayValue = formatNumber(cellValue, this.options.number_format); // Use global formatNumber
                } else if (typeof cellValue === 'string' && !isNaN(parseFloat(cellValue.replace(/[,.]/g, '')))) {
                    // Attempt to format strings that look like numbers, considering potential separators
                     // We need to be careful here not to format non-numeric strings
                    try {
                         // Let formatNumber handle parsing and formatting based on the specified format
                         displayValue = formatNumber(cellValue, this.options.number_format);
                    } catch (e) {
                        // If formatting fails, use the original string value
                        displayValue = cellValue;
                    }
                } else {
                     displayValue = cellValue; // Keep original value if not numeric or safely formattable
                }

                // Add postfix if available and value is not null/undefined
                if (dataset.postfix && cellValue !== null && cellValue !== undefined) {
                    displayValue = displayValue + ' ' + dataset.postfix;
                }
                
                row.eI('td', {
                    class: this.getCellClass(this.options.showLabels ? colIndex + 1 : colIndex),
                    text: displayValue // Use the formatted value
                }); 
            });

            tbody.eI(row);
        });

        container.eI(table).eI(tbody);

        // Verifica nuovamente che itemsPerPage sia un numero valido
        const itemsPerPage = parseInt(this.options.itemsPerPage, 10);
        console.log(`Creating table with itemsPerPage=${itemsPerPage}`);
        
        if (itemsPerPage > 0) {
            const nav = container.eI('nav', {'arial-label': 'Table pagination'});
            const ul = nav.eI('ul', 'pagination');
            const tableManager = new ItoTableSorterPaginator(table, itemsPerPage, ul);
        }
    }

    update() {
        const container = eI('#'+this.containerId);
        const tables = container.getElementsByTagName('table');
        if (tables.length > 0) {
            tables[0].remove();
        }
        this.render();
    }
}