/**
 * A list page whose own search box and filter form drive a server-side DataTable (see ServerSideList.php):
 * the spinner while it loads, the search box and filters, rows that open their document, and item popovers.
 * Call it on DOMContentLoaded before the table's own script, so the first load shows the spinner too.
 *
 *   const list = serverList({ table: '#tblRfqs', card: '#rfqs_card', form: '#rfqs_filter', url: '/procurement/rfqs' });
 *   list.reload();   // after saving: same page, fresh rows
 */
export function serverList({ table, card, form, url }) {
    const $table = $(table);
    const $form = $(form);
    const api = () => $table.DataTable();

    // Bound on the table itself: DataTables does not bubble its events up to the document
    $table.on('processing.dt', (e, settings, on) => $(card).toggleClass('list-busy', on));

    // 6 or more items in a row: hovering the count lists them all
    $table.on('draw.dt', () => {
        $('.popover').remove();
        $table.find('.js-items-popover').each((i, el) => bootstrap.Popover.getOrCreateInstance(el, { container: 'body' }));
    });

    // A row opens its document (Ctrl / middle click or Ctrl+Enter: in a new tab); its own links and buttons work as usual
    const open = (row, newTab) => newTab ? window.open(row.dataset.url, '_blank') : (window.location.href = row.dataset.url);
    const onOwnControl = (e) => $(e.target).closest('a, button, .js-items-popover').length > 0;
    $table.on('click', 'tbody tr.js-open-row', function (e) {
        if (onOwnControl(e) || window.getSelection().toString()) return;   // selecting text to copy, not opening
        open(this, e.ctrlKey || e.metaKey);
    });
    $table.on('auxclick', 'tbody tr.js-open-row', function (e) { if (e.button === 1 && !onOwnControl(e)) open(this, true); });
    $table.on('keydown', 'tbody tr.js-open-row', function (e) { if (e.key === 'Enter' && e.target === this) open(this, e.ctrlKey || e.metaKey); });

    // Search and filters: the table asks the server again (page 1); the URL keeps them (refresh / Back work)
    function apply() {
        const params = new URLSearchParams();
        $form.serializeArray().forEach((f) => { if (f.value.trim()) params.set(f.name, f.value.trim()); });
        history.replaceState(null, '', url + (params.toString() ? '?' + params : ''));
        api().search(($form.find('[name=q]').val() || '').trim()).draw();   // the other fields go with each request
    }
    let typing = null;
    $form.on('submit', (e) => { e.preventDefault(); clearTimeout(typing); apply(); });
    $form.on('input', '[name=q]', () => { clearTimeout(typing); typing = setTimeout(apply, 200); });
    $form.on('change', 'select', apply);

    return { api, apply, reload: () => api().ajax.reload(null, false) };
}
