registerHook('table-action-table_posts-edit', function (id, sendform) {
    window.location.href = milk_url + '?page=posts&action=edit&id=' + id + '';
    return false;
})

registerHook('table-action-table_posts-delete', function (id, elclick, form, sendform) {
    return confirm('Are you sure you want to delete this user? This action cannot be undone.');
});

function table_posts_search() {
    var comp_table = getComponent('table_posts');
    if (comp_table == null) return;
    let val =  document.getElementById('table_posts_search').value;
    comp_table.filter_remove_start('search:');
    if (val != '') {
        comp_table.filter_add('search:' + val);
    }
    comp_table.set_page(1);
    comp_table.reload();
}

document.getElementById('table_posts_search').addEventListener('input', table_posts_search);