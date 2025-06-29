/**
 * Filter the executions table by job name
 */
function filterByJobName() {
    const jobNameFilter = document.getElementById('jobNameFilter');
    const selectedJobName = jobNameFilter.value;
    
    // Get the table component
    const tableComponent = getComponent('jobs-executions-table');
    if (!tableComponent) {
        console.error('Table component not found');
        return;
    }
    
    // Remove existing job name filters
    tableComponent.filter_remove_start('jobs_name:');
    
    // Add new filter if not "all"
    if (selectedJobName !== 'all' && selectedJobName !== '') {
        tableComponent.filter_add('jobs_name:' + selectedJobName);
    }
    
    // Reset to first page and reload
    tableComponent.set_page(1);
    tableComponent.reload();
}

/**
 * Clear the job name filter
 */
function clearJobFilter() {
    const jobNameFilter = document.getElementById('jobNameFilter');
    jobNameFilter.value = 'all';
    
    // Trigger the filter function
    filterByJobName();
}

/**
 * Run a single job
 */
function runJob(el) {
    const jobName = el.getAttribute('data-name');
    
    // Show loading state
    el.classList.add('disabled');
    el.innerHTML = '<i class="bi bi-hourglass-split spinner-border-sm"></i> Starting...';
    
    var formData = getFormData('?name=' + jobName);
    
    fetch(milk_url + '?page=jobs&action=run', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success == true) {
            window.toasts.show('Job started successfully', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            window.toasts.show(data.msg || 'Job failed to start', 'danger');
            // Restore button state
            el.classList.remove('disabled');
            el.innerHTML = '<i class="bi bi-play-fill"></i> Run';
        }
    })
    .catch(error => {
        console.error(error);
        window.toasts.show('Error starting job', 'danger');
        // Restore button state
        el.classList.remove('disabled');
        el.innerHTML = '<i class="bi bi-play-fill"></i> Run';
    });
}

/**
 * Stop/Block a running job
 */
function blockJob(el) {
    const jobName = el.getAttribute('data-name');
    
    if (!confirm('Do you want to stop the running job?')) {
        return;
    }
    
    // Show loading state
    el.classList.add('disabled');
    el.innerHTML = '<i class="bi bi-hourglass-split spinner-border-sm"></i> Stopping...';
    
    var formData = getFormData('?name=' + jobName);
    
    fetch(milk_url + '?page=jobs&action=block', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success == true) {
            window.toasts.show('Job stopped successfully', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            window.toasts.show(data.msg || 'Failed to stop job', 'danger');
            // Restore button state
            el.classList.remove('disabled');
            el.innerHTML = '<i class="bi bi-stop-fill"></i> Stop';
        }
    })
    .catch(error => {
        console.error(error);
        window.toasts.show('Error stopping job', 'danger');
        // Restore button state
        el.classList.remove('disabled');
        el.innerHTML = '<i class="bi bi-stop-fill"></i> Stop';
    });
}

/**
 * Block a pending job
 */
function blockPendingJob(el) {
    const jobName = el.getAttribute('data-name');
    
    if (!confirm('Do you want to block this pending job?')) {
        return;
    }
    
    // Show loading state
    el.classList.add('disabled');
    el.innerHTML = '<i class="bi bi-hourglass-split spinner-border-sm"></i> Blocking...';
    
    var formData = getFormData('?name=' + jobName);
    
    fetch(milk_url + '?page=jobs&action=block_pending', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success == true) {
            window.toasts.show('Pending job blocked successfully', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            window.toasts.show(data.msg || 'Failed to block pending job', 'danger');
            // Restore button state
            el.classList.remove('disabled');
            el.innerHTML = '<i class="bi bi-x-octagon-fill"></i> Block';
        }
    })
    .catch(error => {
        console.error(error);
        window.toasts.show('Error blocking pending job', 'danger');
        // Restore button state
        el.classList.remove('disabled');
        el.innerHTML = '<i class="bi bi-x-octagon-fill"></i> Block';
    });
}

/**
 * Run all due jobs
 */
function runAllDueJobs(el) {
    if (!confirm('Do you want to run all due jobs?')) {
        return;
    }
    
    // Show loading state
    el.classList.add('disabled');
    el.innerHTML = '<i class="bi bi-hourglass-split spinner-border-sm"></i> Running all...';
    
    fetch(milk_url + '?page=jobs&action=run_all', {
        method: 'POST',
        credentials: 'same-origin',
    })
    .then(response => response.json())
    .then(data => {
        if (data.success == true) {
            window.toasts.show(data.msg || 'Jobs executed successfully', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            window.toasts.show(data.msg || 'Some jobs failed', 'warning');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
    })
    .catch(error => {
        console.error(error);
        window.toasts.show('Error running jobs', 'danger');
        // Restore button state
        el.classList.remove('disabled');
        el.innerHTML = '<i class="bi bi-play-circle-fill"></i> Run all due jobs';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Restore filter state on page load
    const urlParams = new URLSearchParams(window.location.search);
    const tableParams = urlParams.get('jobs-executions-table');
    
    if (tableParams) {
        try {
            const params = JSON.parse(decodeURIComponent(tableParams));
            if (params.filters) {
                const filters = JSON.parse(params.filters);
                filters.forEach(filter => {
                    if (filter.startsWith('jobs_name:')) {
                        const jobName = filter.replace('jobs_name:', '');
                        const select = document.getElementById('jobNameFilter');
                        if (select) {
                            select.value = jobName;
                        }
                    }
                });
            }
        } catch (e) {
            console.log('No filter state to restore');
        }
    }

    // Attach event handlers for job buttons
    eIs('.jsfn-runjobs', (el, i) => {
        el.onclick = function(e) {
            e.preventDefault();
            runJob(e.currentTarget);
        }
    });
    
    eIs('.jsfn-blockjobs', (el, i) => {
        el.onclick = function(e) {
            e.preventDefault();
            blockJob(e.currentTarget);
        }
    });
    
    eIs('.jsfn-blockpendingjobs', (el, i) => {
        el.onclick = function(e) {
            e.preventDefault();
            blockPendingJob(e.currentTarget);
        }
    });
    
    eIs('.jsfn-runalljobs', (el, i) => {
        el.onclick = function(e) {
            e.preventDefault();
            runAllDueJobs(e.currentTarget);
        }
    });
});

registerHook('table-init', function (table_class) {
    if(table_class.el_container) {
        let id = table_class.el_container.getAttribute('id');
        if (id == "jobs-executions-table") {
            table_class.el_container.querySelectorAll('.js-show-info').forEach((element) => {
                element.onclick = function(e) {
                    e.preventDefault();
                    const logId = this.getAttribute('data-id');
                    window.offcanvasEnd.show();
                    window.offcanvasEnd.loading_show();
                    
                    // Fetch log details
                    fetch('?page=jobs&action=get-log-details&id=' + logId, {
                        method: 'POST',
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        window.offcanvasEnd.loading_hide();
                        if (data.success) {
                            window.offcanvasEnd.title(data.title);
                            window.offcanvasEnd.body(data.html);
                        } else {
                            window.offcanvasEnd.title('Error');
                            window.offcanvasEnd.body('<div class="alert alert-danger">' + data.msg + '</div>');
                        }
                    })
                    .catch(error => {
                        window.offcanvasEnd.loading_hide();
                        window.offcanvasEnd.title('Error');
                        window.offcanvasEnd.body('<div class="alert alert-danger">Failed to load log details</div>');
                    });
                };
            });
        }
    }
});