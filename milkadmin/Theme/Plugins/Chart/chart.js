/**
 * chart
 * V 1.0
 */

// Format a number according to the specified number format
// @param {number|string} value - The value to format
// @param {string} format - The number format 'en-In' 'it-IT' ecc...
// @param {number} decimals - Number of decimal places (default: 2)
// @returns {string} - The formatted number
function formatNumber(value, format = 'en-IN', decimals = 2, postfix = '', formatting_text = 1) {
    if (value === null || value === undefined || value === '') {
        return ''; // Return empty string for null, undefined, or empty values
    }
    if (postfix !== '') {
        postfix = ' ' + postfix;
    }
    if (formatting_text == 0) {
        return value.toString() + postfix;
    }
    let num = parseFloat(value);

    if (isNaN(num)) {
        // If value is not a number, return it as is
        return value.toString() + postfix;
    }
    
    // Ensure decimals is a valid number (not empty string or NaN)
    if (decimals === '' || decimals === null || decimals === undefined || isNaN(parseInt(decimals))) {
        decimals = 2; // Default to 2 decimal places if invalid
    } else {
        decimals = parseInt(decimals);
    }
    
    // Usa direttamente toLocaleString con le opzioni appropriate
    let locate;
    if (format === '') {
        return num.toFixed(decimals) + postfix;
    }
    try {
       locate = num.toLocaleString(format, {
           minimumFractionDigits: decimals,
           maximumFractionDigits: decimals
       });
    } catch (e) {
        //console.error('Error formatting ' + value + ' with format ' + format + ' and decimals ' + decimals + ' and postfix ' + postfix + ':', e);
        return value.toString() + postfix;
    }
    
    return locate + postfix;
}

class ChartManager {
    constructor() {
        this.chart = null;
        this.loader = null;
        this.NON_AXIAL_CHARTS = ['pie', 'doughnut', 'polararea', 'radar'];
        this.defaultColors = [
            '#4bc0c0', '#ff6384', '#36a2eb', '#ffcd56', '#9966ff',
            '#ff9f40', '#bf564a', '#4d5360', '#91e8e1', '#f45b5b'
        ];
        this.numberFormat = '';
    }

    configureAxisOptions(options, axis) {
        options.scales = options.scales || {};
        
        if (options[`scale_${axis}`] === 'hide') {
            options.scales[axis] = { display: false };
        } else if (axis === 'y') {
                options.scales[axis].type = options[`scale_${axis}`];
                options.scales[axis].display = true;
        }

        if (options[`title_${axis}`]) {
            options.scales[axis].title = {
                display: true,
                text: options[`title_${axis}`]
            };
            delete options[`title_${axis}`];
        }

        delete options[`scale_${axis}`];
    }

    configureScaleOptions(options, type) {
    if (!options || Object.keys(options).length === 0) return null;
    if (this.NON_AXIAL_CHARTS.includes(type.toLowerCase())) {
        delete options.scales;
        delete options.scale_x;
        delete options.scale_y;
        delete options.title_x;
        delete options.title_y;
        delete options.beginAtZero;
        delete options.start_by_zero;
        return options;
    }
    // se non esistono scales.x e scales.y le inizializzo
    if (!options.scales) {
        options.scales = {};
    }
    if (!options.scales.x) {
        options.scales.x = {};
    }
    if (!options.scales.y) {
        options.scales.y = {};
    }

    ['x', 'y'].forEach(axis => this.configureAxisOptions(options, axis));

    // Handle beginAtZero option (legacy support)
    if (options.beginAtZero) {
        options.scales = options.scales || {};
        options.scales.y = options.scales.y || {};
        options.scales.y.beginAtZero = true;
        delete options.beginAtZero;
    }
    
    // Handle start_by_zero option (new implementation)
    if (options.start_by_zero) {
        options.scales = options.scales || {};
        options.scales.y = options.scales.y || {};
        options.scales.y.beginAtZero = true;
        delete options.start_by_zero;
    }

    return options;
}

    configurePlugins(options, type) {
       // if (!options || Object.keys(options).length === 0) return {};
        if (!options.plugins) {
            options.plugins = {};
        }

        // Only configure legend position if specified
        if (options.legend_position) {
            options.plugins.legend = {
                position: options.legend_position
            };
            delete options.legend_position;
        }

        options.plugins.decimation = {
              enabled: true,
              algorithm: 'min-max'
        }
        
        // Handle show_datalabels option
        const showDataLabels = options.show_datalabels || 'no';   
        // Configure datalabels for bar charts
        if (type === 'bar') {
            options.plugins.datalabels = {
                align: 'end',
                anchor: 'end',
                color: function(context) {
                    return context.dataset.backgroundColor;
                },
                font: function(context) {
                    var w = context.chart.width;
                    return {
                        size: w < 512 ? 12 : 14,
                        weight: 'bold',
                    };
                },
                formatter: (value, context) => {
                    // If show_datalabels is 'no', don't show any labels
                    if (showDataLabels === 'no') {
                        return null;
                    }
                    
                    // If value is 0, don't show label
                    if (value == 0) {
                        console.log('Value is zero, not showing label');
                        return null;
                    }
                    
                    // The 'limited' option has been removed as it's no longer needed
                    
                    // For percentage calculation
                    if (showDataLabels === 'percent') {
                        try {
                            // Safely calculate the total
                            let total = 0;
                            
                            if (context.chart && context.chart.data && 
                                context.chart.data.datasets && 
                                context.chart.data.datasets[context.datasetIndex] && 
                                context.chart.data.datasets[context.datasetIndex].data) {
                                
                                // Calculate total using absolute values to handle mixed positive/negative values
                                total = context.chart.data.datasets[context.datasetIndex].data.reduce(
                                    (sum, val) => sum + Math.abs(parseFloat(val) || 0), 0
                                );
                            } else if (context.dataset && context.dataset.data) {
                                // Alternative approach if chart structure isn't available
                                // Calculate total using absolute values to handle mixed positive/negative values
                                total = context.dataset.data.reduce(
                                    (sum, val) => sum + Math.abs(parseFloat(val) || 0), 0
                                );
                            }
                            
                            // Handle zero values correctly
                            if (parseFloat(value) === 0 || isNaN(parseFloat(value))) {
                                return null;
                            }
                            
                            // Use absolute value for percentage calculation
                            const percentage = total > 0 ? (Math.abs(parseFloat(value)) / total) * 100 : 0;
                            
                            // Don't show percentage if it's less than 1%
                            if (percentage < 1) {
                                return null;
                            }
                            return percentage.toFixed(1) + '%';
                        } catch (e) {
                            console.error('Error calculating percentage:', e);
                            return value; // Fallback to the actual value
                        }
                    }
                    
                    // For 'yes' or any other value, show the actual value
                    // Format the number according to the number_format setting
                    let formattedValue = formatNumber(value, this.numberFormat, context.chart.data.datasets[context.datasetIndex].decimal, context.chart.data.datasets[context.datasetIndex].postfix, options.formatting_text);

                    return formattedValue;
                }
            };
        }
    
        if (['pie', 'doughnut', 'polarArea'].includes(type) && !options.plugins.datalabels) {
            options.plugins.datalabels = {
                color: function(context) {
                    return '#fff'; // Testo bianco per contrasto con i colori del grafico
                },
                font: function(context) {
                    var w = context.chart.width;
                    return {
                        size: w < 512 ? 11 : 13,
                        weight: 'bold'
                    };
                },
                formatter: (value, context) => {
                    // If show_datalabels is 'no', don't show any labels
                    if (showDataLabels === 'no') {
                        return null;
                    }
                    if (context.parsed < 5) { // Se il valore percentuale è minore del 5%
                        return null;
                    }
                    // If value is 0, don't show label
                   
                    
                    // For percentage calculation
                    if (showDataLabels === 'percent') {
                        try {
                            // Safely calculate the total
                            let total = 0;
                            if (context.chart && context.chart.data && 
                                context.chart.data.datasets && 
                                context.chart.data.datasets[context.datasetIndex] && 
                                context.chart.data.datasets[context.datasetIndex].data) {
                                
                                // Calculate total using absolute values to handle mixed positive/negative values
                                total = context.chart.data.datasets[context.datasetIndex].data.reduce(
                                    (sum, val) => sum + Math.abs(parseFloat(val) || 0), 0
                                );
                            } else if (context.dataset && context.dataset.data) {
                                // Alternative approach if chart structure isn't available
                                // Calculate total using absolute values to handle mixed positive/negative values
                                total = context.dataset.data.reduce(
                                    (sum, val) => sum + Math.abs(parseFloat(val) || 0), 0
                                );
                            }
                            
                            // Handle zero values correctly
                            if (parseFloat(value) === 0 || isNaN(parseFloat(value))) {
                                return null;
                            }
                            
                            // Use absolute value for percentage calculation
                            const percentage = total > 0 ? (Math.abs(parseFloat(value)) / total) * 100 : 0;
                            if (percentage < 1) {
                                return null;
                            }
                            
                            return percentage.toFixed(1) + '%';
                        } catch (e) {
                            console.error('Error calculating percentage:', e);
                            return value; // Fallback to the actual value
                        }
                    }
                    
                    // Format the number according to the number_format setting
                    let formattedValue = formatNumber(value, this.numberFormat, context.chart.data.datasets[context.datasetIndex].decimal, context.chart.data.datasets[context.datasetIndex].postfix, options.formatting_text);
                    
                    
                    return formattedValue;
                },
                textAlign: 'center',
                anchor: 'end',
                backgroundColor: function(context) {
                    return context.dataset.backgroundColor;
                },
                borderColor: 'white',
                borderRadius: 25,
                borderWidth: 2,
                color: 'white',
                padding: 6
            };
        }
      
        // Configure datalabels for scatter charts
        if (type === 'scatter') {
            options.plugins.datalabels = {
                align: 'top',
                anchor: 'center',
                color: function(context) {
                    return context.dataset.backgroundColor;
                },
                borderRadius: 4,
                font: {
                    weight: 'bold'
                },
                formatter: (value, context) => {
                    // If show_datalabels is 'no', don't show any labels
                    if (showDataLabels === 'no') {
                        return null;
                    }
                    
                    // For percentage calculation - explain it doesn't make sense for scatter
                    if (showDataLabels === 'percent') {
                        // Instead of showing percentages, show the actual (x,y) coordinates
                        return `(${formatNumber(value.x, this.numberFormat, 1)}, ${formatNumber(value.y, this.numberFormat, 1)})`;
                    }
                    
                    // Format the coordinates
                    let formattedValue = `(${formatNumber(value.x, this.numberFormat, 1)}, ${formatNumber(value.y, this.numberFormat, 1)})`;
                    return formattedValue;
                }
            };
        }
        
        // Configure datalabels for bubble charts
        if (type === 'bubble') {
            options.plugins.datalabels = {
                anchor: function(context) {
                    var value = context.dataset.data[context.dataIndex];
                    return value.r < 50 ? 'end' : 'center';
                },
                align: function(context) {
                    var value = context.dataset.data[context.dataIndex];
                    return value.r < 50 ? 'end' : 'center';
                },
                color: function(context) {
                    var value = context.dataset.data[context.dataIndex];
                    console.log(value);
                    return value.r < 50 ? context.dataset.backgroundColor : 'white';
                },
                borderRadius: 4,
                font: {
                    weight: 'bold'
                },
                formatter: (value, context) => {
                    // If show_datalabels is 'no', don't show any labels
                    if (showDataLabels === 'no') {
                        return null;
                    }
                    
                    // For percentage calculation
                    if (showDataLabels === 'percent') {
                        try {
                            // For bubble charts, we'll calculate percentage based on the r value (size)
                            // Safely calculate the total of all bubble sizes
                            let total = 0;
                            if (context.chart && context.chart.data && 
                                context.chart.data.datasets && 
                                context.chart.data.datasets[context.datasetIndex] && 
                                context.chart.data.datasets[context.datasetIndex].data) {
                                
                                const bubbleData = context.chart.data.datasets[context.datasetIndex].data;
                                for (let i = 0; i < bubbleData.length; i++) {
                                    if (bubbleData[i] && bubbleData[i].r) {
                                        total += Math.abs(parseFloat(bubbleData[i].r) || 0);
                                    }
                                }
                            }
                            
                            // Get current bubble size (r value)
                            const bubbleSize = parseFloat(value.r) || 0;
                            
                            // Handle zero values correctly
                            if (bubbleSize === 0 || isNaN(bubbleSize)) {
                                return null;
                            }
                            
                            const percentage = total > 0 ? (Math.abs(bubbleSize) / total) * 100 : 0;
                            if (percentage < 1) {
                                return null; // Don't show very small percentages
                            }
                            
                            return percentage.toFixed(1) + '%';
                        } catch (e) {
                            console.error('Error calculating percentage for bubble chart:', e);
                            // Fallback to the regular formatting
                        }
                    }
                    
                    // Default: Format the number according to the number_format setting
                    let formattedValue = formatNumber(value.label || value.r, this.numberFormat, context.chart.data.datasets[context.datasetIndex].decimal, context.chart.data.datasets[context.datasetIndex].postfix, options.formatting_text);
                    return formattedValue;
                }
            };
        }
        
        // Configure datalabels for line charts
        if (type === 'line' && !options.plugins.datalabels) {
            options.plugins.datalabels = {
                backgroundColor: function(context) {
                    return context.dataset.backgroundColor;
                },
                borderRadius: 4,
                color: 'white',
                align: 'end',
                anchor: 'end',
                font: {
                    weight: 'bold'
                },
                formatter: (value, context) => {
                    // Format the number according to the number_format setting
                    let formattedValue = formatNumber(value, this.numberFormat, context.chart.data.datasets[context.datasetIndex].decimal, context.chart.data.datasets[context.datasetIndex].postfix, options.formatting_text);
                    if (showDataLabels === 'no') {
                        return null;
                    }
                     // For percentage calculation
                     if (showDataLabels === 'percent') {
                        try {
                            // Safely calculate the total
                            let total = 0;
                            if (context.chart && context.chart.data && 
                                context.chart.data.datasets && 
                                context.chart.data.datasets[context.datasetIndex] && 
                                context.chart.data.datasets[context.datasetIndex].data) {
                                
                                // Calculate total using absolute values to handle mixed positive/negative values
                                total = context.chart.data.datasets[context.datasetIndex].data.reduce(
                                    (sum, val) => sum + Math.abs(parseFloat(val) || 0), 0
                                );
                            } else if (context.dataset && context.dataset.data) {
                                // Alternative approach if chart structure isn't available
                                // Calculate total using absolute values to handle mixed positive/negative values
                                total = context.dataset.data.reduce(
                                    (sum, val) => sum + Math.abs(parseFloat(val) || 0), 0
                                );
                            }
                            
                            // Handle zero values correctly
                            if (parseFloat(value) === 0 || isNaN(parseFloat(value))) {
                                return null;
                            }
                            
                            // Use absolute value for percentage calculation
                            const percentage = total > 0 ? (Math.abs(parseFloat(value)) / total) * 100 : 0;
                            if (percentage < 1) {
                                return null;
                            }
                            
                            return percentage.toFixed(1) + '%';
                        } catch (e) {
                            console.error('Error calculating percentage:', e);
                            return value; // Fallback to the actual value
                        }
                    }
                    
                    return formattedValue;
                },
                padding: 6
            };
        }
    
      
        
        // Configurazione tooltip per mostrare il suffisso
        if (!options.plugins.tooltip) {
            options.plugins.tooltip = {};
        }
        
        // Aggiungi callback per mostrare il suffisso nei tooltip
        options.plugins.tooltip.callbacks = {
            label: (context) => {
                let label = context.dataset.label || '';
                let value;
                
                // Controlla se è un grafico orizzontale (indexAxis = 'y')
                const isHorizontal = options.indexAxis === 'y';
                const isBubble = type === 'bubble';
                
                // Per grafici orizzontali, il valore è in parsed.x invece di parsed.y
                if (isHorizontal && context.parsed.x !== undefined) {
                    value = context.parsed.x;
                }
                // Gestione speciale per scatter e bubble che hanno dati in formato {x: ..., y: ...}
                else if (!isHorizontal && context.parsed.y !== undefined) {
                    value = context.parsed.y;
                } else if (typeof context.parsed === 'object' && context.parsed !== null) {
                    // Se è un oggetto ma non ha y/x, prova a usare il valore raw
                    value = context.raw;
                } else {
                    value = context.parsed;
                }
                
                if (label) {
                    label += ': ';
                }
                
                if (value !== null && value !== undefined) {
                    // Assicurati che value sia un primitivo, non un oggetto
                    if (typeof value === 'object') {
                        value = JSON.stringify(value);
                    } else if (typeof value === 'number') {
                        // Format the number according to the number_format setting
                        value = formatNumber(value, this.numberFormat, context.chart.data.datasets[context.datasetIndex].decimal, context.chart.data.datasets[context.datasetIndex].postfix, options.formatting_text);
                    } else {
                        // Aggiungi il suffisso se presente
                        if (context.chart.data.datasets[context.datasetIndex].postfix) {
                            label += ' ' + context.chart.data.datasets[context.datasetIndex].postfix;
                        }
                    }
                    
                    label += value;
                    
                    // Per i grafici bubble, aggiungi il conteggio (label) come seconda riga
                    if (isBubble && context.raw && context.raw.label !== undefined) {
                        let temp_label = label;
                        label = [];
                        label.push(temp_label);
                        label.push('Count: ' + formatNumber(context.raw.label, this.numberFormat, context.chart.data.datasets[context.datasetIndex].decimal, '', options.formatting_text));
                    }
                }
                
                return label;
            }
        };
    
        if (!options.animation) {
            options.animation = false;
        }

        return options;
    }

    cleanupOptions(data, options, type) {
        if (!options || !data) return;
        try {
            delete data.query;
            delete options.order_dir;
            delete options.legend_position;
            // Don't delete show_datalabels here
        } catch (e) {
            console.warn('Warning cleaning up options:', e);
        }
        // se è line e ha più di un tot di dati tolgo i punti
        if (type == "line") {
            if (data && data.datasets) {
                data.datasets.forEach(dataset => {
                    if (dataset.data && dataset.data.length > 20) {
                        dataset.pointRadius = 0;
                        dataset.pointHoverRadius = 0;
                    }

                    // For line charts, we don't automatically remove datalabels anymore
                    // This is now controlled by the show_datalabels option
                    if (dataset.data && dataset.data.length > 100) {
                        delete dataset.datalabels;
                        if (options.plugins) delete options.plugins.datalabels;
                    }
                });
            }
        }
    }

    createChart(id, type, data, options) {
        var plugins = [];
        
        // Always add ChartDataLabels plugin, we'll control visibility through the formatter
        plugins.push(ChartDataLabels);
        
        if (data && data.datasets) {
            data.datasets.forEach(dataset => {
                if (dataset.data && dataset.data.length > 100) {
                    delete dataset.datalabels;
                    delete options.plugins.datalabels;
                    plugins = [];
                }
            });
        }
        if (type === 'table') {
            this.chart = new DrawJsTable(data, id, options);
            this.chart.render();
        } else {
            options.responsive = true;
            options.maintainAspectRatio = false;
            this.chart = new Chart(document.getElementById(id), {
                type: type,
                data: data,
                options: options,
                plugins: plugins
            });   
        }
        return this.chart;
    }


    draw(id, type, data, options = {}) {
        if (typeof Chart === 'undefined') {
            console.error('chart.js not found');
            return;
        }
        if (!document.getElementById(id)) {
            console.error('Element with id ' + id + ' not found');
            return;
        }
        
        // Check if options contains number_format and set it
        if (options && options.number_format) {
            this.numberFormat = options.number_format;
            // Remove from options to avoid conflicts with Chart.js
            delete options.number_format;
        }
        
        options = this.configureScaleOptions(options, type);
        options = this.configurePlugins(options, type);
        this.cleanupOptions(data, options, type);

        const chart = this.createChart(id, type, data, options);
        this.setLoader(id);
        
        return chart;
    }

    update(data) {
        this.chart.data = data;
        this.chart.update();
    }

    setLoader(id) {
        const container = document.getElementById(id + '_container');
        const loading = container?.querySelector('.js-ito-loading');
        if (loading) {
            this.loader = new Loading(loading);
        }
    }

    getLoader() {
        return this.loader || false;
    }
}

class LineChartManager extends ChartManager {
    constructor() {
        super();
    }

    configureDataset(dataset, index) {
        const color = dataset.backgroundColor || this.defaultColors[index % this.defaultColors.length];
        
        return {
            ...dataset,
            borderColor: color,
            backgroundColor: color,
            pointBackgroundColor: color,
            pointBorderColor: color,
            pointHoverBackgroundColor: color,
            pointHoverBorderColor: color,
            borderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: false,
            tension: 0
        };
    }

    draw(id, type, data, options = {}) {
        if (type !== 'line') {
            return super.draw(id, type, data, options);
        }

        if (options && options.number_format) {
            this.numberFormat = options.number_format;
            // Remove from options to avoid conflicts with Chart.js
            delete options.number_format;
        }

        if (options && options.start_by_zero) {
            if (!options.scales) options.scales = {};
            if (!options.scales.y) options.scales.y = {};
            options.scales.y.beginAtZero = true;
        }

        if (data && data.datasets) {
            data.datasets = data.datasets.map((dataset, index) => 
                this.configureDataset(dataset, index)
            );
        }
        
        // Configurazione aggiuntiva per il layout
        if (!options.layout) {
            options.layout = {};
        }
        if (!options.layout.padding) {
            options.layout.padding = {};
        }
        // Aggiungi padding per dare spazio ai label
        options.layout.padding = {
            top: options.layout.padding.top || 32,
            right: options.layout.padding.right || 16,
            bottom: options.layout.padding.bottom || 16,
            left: options.layout.padding.left || 8
        };
        
        // Configurazione per gli elementi della linea
        if (!options.elements) {
            options.elements = {};
        }
        if (!options.elements.line) {
            options.elements.line = {};
        }
        options.elements.line.fill = options.elements.line.fill !== undefined ? options.elements.line.fill : false;
        options.elements.line.tension = options.elements.line.tension !== undefined ? options.elements.line.tension : 0.4;
        
        options = this.configureScaleOptions(options, type);
        options = this.configurePlugins(options, type);
        
        // Set responsive options
        options.responsive = true;
        options.maintainAspectRatio = false;
        
        if (data.datasets && data.datasets.length > 0) {
            // Check if any dataset has a label
            const hasLabels = data.datasets.some(dataset => dataset.label);
            
            // Hide legend if no labels are found
            if (!hasLabels) {
                if (!options.plugins) options.plugins = {};
                if (!options.plugins.legend) options.plugins.legend = {};
                options.plugins.legend.display = false;
            }
        }
        
        this.cleanupOptions(data, options, type);
        
        const chart = this.createChart(id, type, data, options);
        this.chart = chart;
        this.setLoader(id);
        
        return chart;
    }

    update(data) {
        data.datasets = data.datasets.map((dataset, index) => 
            this.configureDataset(dataset, index)
        );
        
        super.update(data);
    }
}

class BarChartManager extends ChartManager {
    constructor() {
        super();
    }

    configureDataset(dataset, index) {
        const color = dataset.backgroundColor || this.defaultColors[index % this.defaultColors.length];
        
        return {
            ...dataset,
            backgroundColor: color,
            borderColor: color,
            borderWidth: 1,
            borderRadius: 4,
        };
    }

   

    configureStackedOptions(options) {
        if (options.stacked === true) {
            options.scales = options.scales || {};
            options.scales.x = options.scales.x || {};
            options.scales.y = options.scales.y || {};
            
            options.scales.x.stacked = true;
            options.scales.y.stacked = true;
            delete options.stacked;
        }
        return options;
    }

    draw(id, data, options = {}) {
        // Supporto sia per 'bar' che per 'horizontal-bar'

        // Configure datasets with colors and styles
        if (data && data.datasets) {
            data.datasets = data.datasets.map((dataset, index) => 
                this.configureDataset(dataset, index)
            );
        }

        if (options && options.number_format) {
            this.numberFormat = options.number_format;
            // Remove from options to avoid conflicts with Chart.js
            delete options.number_format;
        }
        
        // Handle start_by_zero option for bar charts
        if (options && options.start_by_zero) {
            if (!options.scales) options.scales = {};
            if (!options.scales.y) options.scales.y = {};
            options.scales.y.beginAtZero = true;
        }

        // Configure stacked bar if needed
        options = this.configureStackedOptions(options);

        // Configure scales and plugins using parent methods
        options = this.configureScaleOptions(options, 'bar');
        options = this.configurePlugins(options, 'bar');
        
        // Configurazione aggiuntiva per il layout delle barre
        if (!options.layout) {
            options.layout = {};
        }
        if (!options.layout.padding) {
            options.layout.padding = {};
        }
        options.layout.padding.top = options.layout.padding.top || 32;
        
        // Configurazione aggiuntiva per le scale
        if (!options.scales) {
            options.scales = {};
        }
        if (!options.scales.x) {
            options.scales.x = {};
        }
        if (!options.scales.y) {
            options.scales.y = {};
        }
        
        // Mantieni visibile l'asse X se non specificato diversamente
        if (options.scales.x.display === undefined) {
            options.scales.x.display = true;
            options.scales.x.offset = true;
        }
        
        // Inizia da zero per l'asse Y se non specificato diversamente
        if (options.scales.y.beginAtZero === undefined) {
            options.scales.y.beginAtZero = true;
        }
        
        // Aggiungi spazio sopra il grafico per i valori delle barre
        if (options.scales.y.suggestedMax === undefined) {
            // Calcola il valore massimo nei dataset
            let maxValue = 0;
            if (data && data.datasets) {
                data.datasets.forEach(dataset => {
                    if (dataset.data) {
                        const datasetMax = Math.max(...dataset.data.filter(v => !isNaN(v)));
                        maxValue = Math.max(maxValue, datasetMax);
                    }
                });
            }
            
            // Aggiungi un 15% di spazio sopra il valore massimo
            if (maxValue > 0) {
                options.scales.y.suggestedMax = maxValue * 1.15;
            }
        }
        
        if (options.indexAxis === 'y') {
            options.scales['y']['type'] = 'category';
            if (options.scales && options.scales['y'] && options.scales['y'].type) {
                delete options.scales['y'].type;
            }
        }
        // Set responsive options
        options.responsive = true;
        options.maintainAspectRatio = false;

        if (data.datasets && data.datasets.length > 0) {
            // Check if any dataset has a label
            const hasLabels = data.datasets.some(dataset => dataset.label);
            
            // Hide legend if no labels are found
            if (!hasLabels) {
                if (!options.plugins) options.plugins = {};
                if (!options.plugins.legend) options.plugins.legend = {};
                options.plugins.legend.display = false;
            }
        }
        // Clean up any unnecessary options
        this.cleanupOptions(data, options, 'bar');

        // Create and return the chart
        for (let i = 0; i < data.datasets.length; i++) {
            data.datasets[i]['type'] = 'bar';
        }
        
        const chart = this.createChart(id, 'bar', data, options);
        this.chart = chart;
        this.setLoader(id);
        
        return chart;
    }

    update(data) {
        // Ensure datasets are properly configured during update
        if (data && data.datasets) {
            data.datasets = data.datasets.map((dataset, index) => 
                this.configureDataset(dataset, index)
            );
        }
        
        super.update(data);
    }
}


class CircularChartManager extends ChartManager {
    constructor() {
        super();
    }

    draw(id, type, data, options = {}) {
        if (!['pie', 'doughnut', 'polarArea'].includes(type)) {
            return super.draw(id, type, data, options);
        }

        if (options && options.number_format) {
            this.numberFormat = options.number_format;
            // Remove from options to avoid conflicts with Chart.js
            delete options.number_format;
        }

        // Set default legend position to right for circular charts
        if (!options.plugins) {
            options.plugins = {};
        }
        if (!options.plugins.legend) {
            options.plugins.legend = {};
        }
        options.plugins.legend.position = options.plugins.legend.position || 'right';
        
        const showDataLabels = options.showDataLabels || 'no'; 
        // Configurazione core per grafici circolari
        options.aspectRatio = options.aspectRatio || 4 / 3;
        
        if (!options.layout) {
            options.layout = {};
        }
        options.layout.padding = options.layout.padding || 16;
        
        if (!options.elements) {
            options.elements = {};
        }
        if (!options.elements.line) {
            options.elements.line = {};
        }
        options.elements.line.fill = options.elements.line.fill !== undefined ? options.elements.line.fill : false;
        
        if (!options.elements.point) {
            options.elements.point = {};
        }
        options.elements.point.hoverRadius = options.elements.point.hoverRadius || 7;
        options.elements.point.radius = options.elements.point.radius || 5;
        
        // Configurazione specifica per tooltip nei grafici circolari
        if (!options.plugins.tooltip) {
            options.plugins.tooltip = {};
        }
        
        // Personalizza il tooltip per mostrare il suffisso nei grafici circolari
        options.plugins.tooltip.callbacks = {
            label: (context) => {
                const labels = context.chart.data.labels;
                const label = labels[context.dataIndex] || '';
                let value = context.raw;
                
                // Assicurati che value sia un primitivo, non un oggetto
                if (typeof value === 'object' && value !== null) {
                    value = JSON.stringify(value);
                } else if (typeof value === 'number') {
                    // Format the number according to the number_format setting
                    value = formatNumber(value, this.numberFormat, context.chart.data.datasets[context.datasetIndex].decimal, context.chart.data.datasets[context.datasetIndex].postfix, options.formatting_text);
                } else {
                    // Aggiungi il suffisso se presente nel dataset
                    if (context.chart.data.datasets[context.datasetIndex].postfix) {
                        value += ' |' + context.chart.data.datasets[context.datasetIndex].postfix;
                    }   
                }
                
                let formattedLabel = label + ': ' + value;
                
              
                
                // Aggiungi la percentuale
                if (context.parsed && typeof context.parsed !== 'object') {
                    // Format the percentage with the number_format setting
                    formattedLabel += ' (' + formatNumber(context.parsed, this.numberFormat) + '%)';
                } else if (context.parsed && typeof context.parsed === 'object') {
                    // Se parsed è un oggetto, non aggiungere la percentuale
                }
                
                return formattedLabel;
            }
        };
        
        options = this.configureScaleOptions(options, type);
        options = this.configurePlugins(options, type);
        this.cleanupOptions(data, options, type);

        const chart = this.createChart(id, type, data, options);
        this.chart = chart;
        this.setLoader(id);
        
        return chart;
    }
}

// Create the ChartManager instance at global scope
var itoCharts = (function() {
    const charts = new Map();
    
    return {
        draw: function(id, type, data, options = {}) {
            let chart;
            switch(type) {
                case 'line':
                    var line_manager = new LineChartManager();
                    chart = line_manager.draw(id, 'line', data, options);
                    break;
                    
                case 'bar':
                case 'stacked-bar':
                case 'horizontal-bar':
                case 'combo-bar-line':
                    if (type === 'horizontal-bar') {
                        options.indexAxis = 'y';
                        options.scales = options.scales || {};
                        options.scales.x = options.scales.x || {};
                        options.scales.y = options.scales.y || {};
                        
                        // Per i grafici orizzontali, dobbiamo assicurarci che gli assi siano correttamente configurati
                        if (options.scales.y && options.scales.y.type === undefined) {
                            // Imposta esplicitamente il tipo come 'category' se non è definito
                            options.scales.y.type = 'category';
                        }
                        
                        // Assicuriamoci che beginAtZero sia impostato per l'asse x
                        options.scales.x.beginAtZero = true;

                    }
                    
                    if (type === 'bar') {
                        options.barThickness = 'flex';
                    }             
                    if (type === 'stacked-bar') {
                        options.stacked = true;
                    }
                    var bar_manager = new BarChartManager();
                    chart = bar_manager.draw(id, data, options);
                    break;
                    
                case 'pie':
                case 'doughnut':
                case 'polarArea':
                    var circular_manager = new CircularChartManager();
                    chart = circular_manager.draw(id, type, data, options);
                    break;
                case 'value-cards':
                    var value_cards = new ValueCards(data, id, options);
                    value_cards.render();
                    break;
                    
                case 'bubble':
                case 'scatter':
                    // Gestione speciale per bubble e scatter che possono avere opzioni di scala personalizzate
                    // Merge delle opzioni fornite dal backend con quelle passate come parametro
                    if (data.options) {
                        options = Object.assign({}, data.options, options);
                        // Rimuovi le opzioni dal data per evitare problemi con Chart.js
                        delete data.options;
                    }
                    
                    // Handle start_by_zero option for scatter charts
                    if (options.start_by_zero) {
                        if (!options.scales) options.scales = {};
                        if (!options.scales.y) options.scales.y = {};
                        options.scales.y.beginAtZero = true;
                    }
                    
                    var manager = new ChartManager();
                    chart = manager.draw(id, type, data, options);
                    break;
                    
                default:
                    var manager = new ChartManager();
                    chart = manager.draw(id, type, data, options);
                    break;
            }
            
            charts.set(id, chart);
            return chart;
        },
        
        getChart: function(id) {
            return charts.get(id);
        },
        
        update: function(id, data) {
            let chart = charts.get(id);
            if (chart) {
                chart.data = data;
                chart.update();
                return true;
            }
            return false;
        },
        
        getLoader: function(id) {
            let chart = charts.get(id);
            return chart ? chart.getLoader(id) : null;
        }
    };
})();
