{{--
    Specifications as a two-column table (Specification | Requirement), rows added, removed and moved;
    saved in the hidden field as "Label: value" lines (see App\Support\Specs). Pasting a copied
    two-column table (Word / Excel) into a cell fills in rows.
    Expects $name (field name), $value (the text).
--}}
@php
    $uid = 'specs_' . uniqid();
    $rows = \App\Support\Specs::rows($value ?? '');
    if ($rows === []) {
        $rows = [['', '']];
    }
@endphp
<div class="specs-editor" id="{{ $uid }}">
    <textarea name="{{ $name }}" class="d-none js-specs-value">{{ $value ?? '' }}</textarea>
    <div class="border rounded-3 overflow-hidden">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr class="small">
                    <th style="width: 34%;" class="ps-2">Specification</th>
                    <th>Requirement</th>
                    <th style="width: 84px;"></th>
                </tr>
            </thead>
            <tbody class="js-specs-rows">
                @foreach ($rows as [$label, $text])
                    <tr class="js-specs-row">
                        <td class="ps-2"><input type="text" class="form-control form-control-sm js-specs-label" value="{{ $label }}" placeholder="e.g. Memory" aria-label="Specification"></td>
                        <td><input type="text" class="form-control form-control-sm js-specs-text" value="{{ $text }}" placeholder="e.g. 16GB DDR5" aria-label="Requirement"></td>
                        <td class="text-end pe-2 text-nowrap">
                            <button type="button" class="btn btn-sm btn-link p-0 text-secondary js-specs-up" title="Move up" aria-label="Move up"><i class="fas fa-arrow-up"></i></button>
                            <button type="button" class="btn btn-sm btn-link p-0 ms-1 text-secondary js-specs-down" title="Move down" aria-label="Move down"><i class="fas fa-arrow-down"></i></button>
                            <button type="button" class="btn btn-sm btn-link p-0 ms-2 text-danger js-specs-remove" title="Remove row" aria-label="Remove row"><i class="fas fa-times"></i></button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
        <button type="button" class="btn btn-sm btn-outline-primary js-specs-add"><i class="fas fa-plus me-1"></i> Add row</button>
        <span class="form-text m-0">Tip: paste the rows of a two-column table (Word / Excel) into a cell to fill in several rows. A row with a blank Specification continues the row above it.</span>
    </div>
</div>
<script>
(function () {
    const box = document.getElementById(@json($uid));
    const body = box.querySelector('.js-specs-rows');
    const hidden = box.querySelector('.js-specs-value');
    const template = body.querySelector('.js-specs-row').cloneNode(true);

    // "Label: value" per row; a row without a label is free text; empty rows are left out
    function save() {
        hidden.value = [...body.querySelectorAll('.js-specs-row')].map((row) => {
            const label = row.querySelector('.js-specs-label').value.trim().replace(/:$/, '');
            const text = row.querySelector('.js-specs-text').value.trim();
            return label ? label + ': ' + text : text;
        }).filter((line) => line !== '').join('\n');
    }

    function newRow(label = '', text = '') {
        const row = template.cloneNode(true);
        row.querySelector('.js-specs-label').value = label;
        row.querySelector('.js-specs-text').value = text;
        return row;
    }

    box.querySelector('.js-specs-add').addEventListener('click', function () {
        const row = newRow();
        body.appendChild(row);
        row.querySelector('.js-specs-label').focus();
    });

    body.addEventListener('click', function (e) {
        const row = e.target.closest('.js-specs-row');
        if (!row) return;
        if (e.target.closest('.js-specs-remove')) {
            if (body.querySelectorAll('.js-specs-row').length > 1) row.remove();
            else row.querySelectorAll('input').forEach((i) => (i.value = ''));
        } else if (e.target.closest('.js-specs-up') && row.previousElementSibling) {
            body.insertBefore(row, row.previousElementSibling);
        } else if (e.target.closest('.js-specs-down') && row.nextElementSibling) {
            body.insertBefore(row.nextElementSibling, row);
        } else {
            return;
        }
        save();
    });

    body.addEventListener('input', save);

    // Enter in the last cell adds a row
    body.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const row = e.target.closest('.js-specs-row');
        if (e.target.classList.contains('js-specs-label')) { row.querySelector('.js-specs-text').focus(); return; }
        const next = row.nextElementSibling || body.appendChild(newRow());
        next.querySelector('.js-specs-label').focus();
    });

    // A pasted table (tab between the columns, one row per line) fills in rows from this one down
    body.addEventListener('paste', function (e) {
        const data = (e.clipboardData || window.clipboardData).getData('text');
        if (!data.includes('\t') && !data.includes('\n')) return;
        e.preventDefault();
        const lines = data.split(/\r?\n/).filter((l) => l.trim() !== '');
        let row = e.target.closest('.js-specs-row');
        lines.forEach((line, i) => {
            const cells = line.split('\t');
            const label = cells.length > 1 ? cells[0].trim() : '';
            const text = (cells.length > 1 ? cells.slice(1).join(' ') : cells[0]).trim();
            if (i > 0) { const added = newRow(); row.after(added); row = added; }
            row.querySelector('.js-specs-label').value = label;
            row.querySelector('.js-specs-text').value = text;
        });
        save();
    });
})();
</script>
