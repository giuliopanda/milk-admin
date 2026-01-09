'use strict';

/**
 * Chart Component
 * Handles filters and AJAX reloads for charts.
 */
class ChartComponent {
    el_container = null;
    component_name = 'Chart';
    plugin_loading = null;
    is_init = false;

    state = {
        page: '',
        action: '',
        chart_id: '',
        canvas_id: '',
        action_url: '',
        token: '',
        current_page: 1,
        filters: '',
        custom: {},
        chart_type: 'bar'
    };

    constructor(el_container) {
        if (!el_container) {
            throw new Error('Chart component requires a valid container element');
        }

        this.el_container = el_container;
        this.initStateFromAttributes();
        this.init();
        this.is_init = true;
        this.el_container.__itoComponent = this;

        const loading_element = this.el_container.querySelector('.js-ito-loading');
        if (loading_element) {
            this.plugin_loading = new Loading(loading_element);
        }
    }

    initStateFromAttributes() {
        const container = this.el_container;

        this.state.page = container.getAttribute('data-page') || '';
        this.state.action = container.getAttribute('data-action') || '';
        this.state.chart_id = container.getAttribute('id') || '';
        this.state.canvas_id = container.getAttribute('data-chart-id') || '';
        this.state.chart_type = container.getAttribute('data-chart-type') || 'bar';
        this.state.action_url = container.getAttribute('data-action-url') || '';
        this.state.token = container.getAttribute('data-token') || '';
        this.state.filters = container.getAttribute('data-filters') || '';

        const custom_json = container.getAttribute('data-custom');
        if (custom_json) {
            try {
                this.state.custom = JSON.parse(custom_json);
            } catch (e) {
                console.warn('Invalid custom data JSON:', e);
                this.state.custom = {};
            }
        }
    }

    init() {
        if (this.is_init) {
            return;
        }
        this.drawInitialChart();
        if (typeof callHook === 'function') {
            callHook('chart-init', this);
        }
    }

    buildFormData() {
        const formData = new FormData();
        const chart_id = this.state.chart_id;

        formData.append('page', this.state.page);
        formData.append('action', this.state.action);
        formData.append('page-output', 'json');
        formData.append('is-inside-request', '1');
        formData.append('chart_id', chart_id);

        if (this.state.token) {
            formData.append('token', this.state.token);
        }

        formData.append(`${chart_id}[page]`, this.state.current_page);
        formData.append(`${chart_id}[filters]`, this.state.filters);

        if (this.state.custom && typeof this.state.custom === 'object') {
            for (const [key, value] of Object.entries(this.state.custom)) {
                formData.append(key, value);
            }
        }

        return formData;
    }

    reload() {
        this.sendForm();
    }

    set_page(page = 1) {
        this.state.current_page = page;
    }

    filter_add(filter) {
        let json_val = [];
        if (this.state.filters !== '') {
            try {
                json_val = JSON.parse(this.state.filters);
            } catch (e) {
                console.warn('Invalid filter JSON, resetting filters');
                json_val = [];
            }
        }

        json_val.push(filter);
        this.state.filters = JSON.stringify(json_val);
    }

    filter_clear() {
        this.state.filters = '';
    }

    filter_remove(filter) {
        let json_val = [];
        if (this.state.filters !== '') {
            try {
                json_val = JSON.parse(this.state.filters);
            } catch (e) {
                console.warn('Invalid filter JSON');
                return;
            }
        }

        const index = json_val.indexOf(filter);
        if (index > -1) {
            json_val.splice(index, 1);
            this.state.filters = JSON.stringify(json_val);
        }
    }

    filter_remove_start(filter) {
        let json_val = [];
        if (this.state.filters !== '') {
            try {
                json_val = JSON.parse(this.state.filters);
            } catch (e) {
                console.warn('Invalid filter JSON');
                return;
            }
        }

        const new_json_val = json_val.filter((val) => {
            return !val.toString().startsWith(filter);
        });

        this.state.filters = JSON.stringify(new_json_val);
    }

    filter_get() {
        if (this.state.filters === '') {
            return [];
        }
        try {
            return JSON.parse(this.state.filters);
        } catch (e) {
            return [];
        }
    }

    async sendForm() {
        if (!this.state.action_url) {
            console.warn('Chart action URL not found');
            return;
        }

        if (this.plugin_loading) {
            this.plugin_loading.show();
        }

        try {
            const form_data = this.buildFormData();
            const response = await fetch(this.state.action_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: form_data
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.chart && data.chart.data) {
                this.updateChart(data.chart);
            }

            jsonAction(data);
        } catch (error) {
            console.error('Chart form submission failed:', error);
            if (typeof window.toasts !== 'undefined') {
                window.toasts.show('An error occurred while updating the chart', 'danger');
            }
        } finally {
            if (this.plugin_loading) {
                this.plugin_loading.hide();
            }
        }
    }

    drawInitialChart() {
        const data = this.getChartDataFromAttributes();
        if (!data) {
            return;
        }
        const canvas_id = this.state.canvas_id;
        if (!canvas_id || typeof itoCharts === 'undefined') {
            return;
        }

        const options = this.getChartOptionsFromAttributes();
        itoCharts.draw(canvas_id, this.state.chart_type, data, options);
    }

    getChartDataFromAttributes() {
        const encoded = this.el_container.getAttribute('data-chart-data');
        if (!encoded) {
            return null;
        }
        try {
            return JSON.parse(atob(encoded));
        } catch (e) {
            console.warn('Invalid chart data attribute:', e);
            return null;
        }
    }

    getChartOptionsFromAttributes() {
        const encoded = this.el_container.getAttribute('data-chart-options');
        if (!encoded) {
            return {};
        }
        try {
            const options = JSON.parse(atob(encoded));
            if (Array.isArray(options) || options === null) {
                return {};
            }
            return options;
        } catch (e) {
            console.warn('Invalid chart options attribute:', e);
            return {};
        }
    }

    updateChart(chartData) {
        const canvas_id = chartData.id || this.state.canvas_id;
        if (!canvas_id) {
            console.warn('Chart update skipped: missing canvas id');
            return;
        }

        const updated = typeof itoCharts !== 'undefined' ? itoCharts.update(canvas_id, chartData.data) : false;
        if (!updated && typeof itoCharts !== 'undefined') {
            itoCharts.draw(canvas_id, chartData.type || this.state.chart_type, chartData.data, chartData.options || {});
        }
    }
}

window.addEventListener('load', function() {
    document.querySelectorAll('.js-chart-container').forEach((el) => {
        new ChartComponent(el);
    });
});

document.addEventListener('updateContainer', function(event) {
    event.detail.el.querySelectorAll('.js-chart-container').forEach((el) => {
        new ChartComponent(el);
    });
});
