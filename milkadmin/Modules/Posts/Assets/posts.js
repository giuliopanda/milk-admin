registerHook('table-action-download', function (ids, sendform) {
    // Create a temporary form to handle the file download
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = milk_url;
    form.style.display = 'none';
    
    // Add form fields
    const fields = {
        'ids': ids,
        'action': 'download-data',
        'page': 'posts'
    };
    
    Object.keys(fields).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    });
    
    // Submit form to trigger download
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    return false;
})
