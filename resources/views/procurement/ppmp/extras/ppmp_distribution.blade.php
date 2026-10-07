{{-- Who gets this project's items (procuring unit's assessment). Not printed on the PPMP / APP. --}}
@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    // Offices already on the list but outside this region (or inactive) stay selectable on their row
    $listed = collect($groups)->flatMap(fn ($g) => collect($g['units'])->pluck('office.id'));
    $officeSelect = function (?int $selected) use ($groups, $listed, $item) {
        $html = '<select class="form-select form-select-sm d-office"><option value="">Choose office…</option>';
        if ($selected && ! $listed->contains($selected) && ($o = $item->distributions->firstWhere('office_id', $selected)?->office)) {
            $html .= '<option value="' . $o->id . '" selected>' . e($o->displayName()) . '</option>';
        }
        foreach ($groups as $group) {
            $html .= '<optgroup label="' . e($group['department']?->displayName() ?? 'Other offices') . '">';
            foreach ($group['units'] as $unit) {
                $o = $unit['office'];
                $label = $unit['depth'] === 0 ? ($o->acronym ?: $o->name) . ' (whole department)' : $o->displayName();
                $html .= '<option value="' . $o->id . '" data-depth="' . $unit['depth'] . '"' . ($o->id === $selected ? ' selected' : '') . '>' . e($label) . '</option>';
            }
            $html .= '</optgroup>';
        }

        return $html . '</select>';
    };
@endphp
<div class="modal fade" id="DISTRIBUTION_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <div>
                    <h5 class="modal-title fw-bold"><i class="fas fa-people-carry text-muted me-2"></i>Distribution</h5>
                    <div class="small text-muted">{{ $item->description }}@if ($item->quantity !== null) · {{ $fmt($item->quantity) }} {{ $item->unit?->name }}@endif</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 small">
                <div class="text-muted mb-2">Which offices receive these items, based on {{ $ppmp->office->shortName() }}'s assessment. This list is not printed on the PPMP or APP; it can be updated any time and will be used for issuance (ICS / PAR). The end-user / accountable person is assigned later, when the items are delivered.</div>
                <table class="table table-sm align-middle mb-2" id="dist_table" style="table-layout: fixed;">
                    <thead class="table-light"><tr><th style="width: 58%;">Office</th><th style="width: 90px;" class="text-end">Qty</th><th>Remarks</th>@if ($canEdit)<th style="width: 28px;"></th>@endif</tr></thead>
                    <tbody>
                        @foreach ($item->distributions as $d)
                            <tr>
                                @if ($canEdit)
                                    <td>{!! $officeSelect($d->office_id) !!}</td>
                                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end d-qty" value="{{ $fmt($d->quantity) }}"></td>
                                    <td><input type="text" class="form-control form-control-sm d-remarks" value="{{ $d->remarks }}"></td>
                                    <td><button type="button" class="btn btn-sm btn-link text-danger p-0 d-remove"><i class="fas fa-times"></i></button></td>
                                @else
                                    <td>{{ $d->office->displayName() }}</td>
                                    <td class="text-end">{{ $fmt($d->quantity) }}</td>
                                    <td>{{ $d->remarks }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold"><td class="text-end">Total</td><td class="text-end" id="dist_total">{{ $fmt($item->distributions->sum('quantity')) }}</td><td colspan="2" class="text-muted fw-normal">@if ($item->quantity !== null) of {{ $fmt($item->quantity) }} <span id="dist_left"></span>@endif</td></tr>
                    </tfoot>
                </table>
                @if ($item->distributions->isEmpty() && ! $canEdit)<div class="text-muted">No distribution yet.</div>@endif
                @if ($canEdit)
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="dist_add"><i class="fas fa-plus me-1"></i> Add office</button>
                @endif
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
                @if ($canEdit)<button type="button" class="btn btn-sm btn-primary px-3" id="dist_save"><i class="fas fa-save me-1"></i> Save</button>@endif
            </div>
        </div>
    </div>
</div>

@if ($canEdit)
<style>
    #DISTRIBUTION_MODAL .select2-results__group { display: block; font-weight: 700; color: #111827; background: #f1f5f9; }
</style>
<script>
(function () {
    const table = document.querySelector('#dist_table tbody');
    const max = {{ $item->quantity !== null ? (float) $item->quantity : 'null' }};
    const officeSelectHtml = @json($officeSelect(null));
    const modal = $('#DISTRIBUTION_MODAL');

    // Searchable office dropdown: typing a department (e.g. PPSPD) lists all its units
    function matcher(params, data) {
        const term = (params.term || '').trim().toLowerCase();
        if (!term) return data;
        if (data.children) {
            if (data.text.toLowerCase().includes(term)) return data;
            const children = data.children.filter((c) => c.text.toLowerCase().includes(term));
            return children.length ? $.extend({}, data, { children: children }) : null;
        }
        return data.text.toLowerCase().includes(term) ? data : null;
    }
    function indent(data) {
        const depth = data.element ? parseInt(data.element.dataset.depth || '0', 10) : 0;
        return depth > 1 ? $('<span class="d-block">').css('padding-left', (depth - 1) * 16 + 'px').text(data.text) : data.text;
    }
    function enhance(select) {
        $(select).select2({ theme: 'bootstrap-5', dropdownParent: modal, width: '100%', selectionCssClass: 'select2--small', dropdownCssClass: 'select2--small', placeholder: 'Choose office…', matcher: matcher, templateResult: indent });
    }
    const fmt = (n) => Number(n.toFixed(2)).toString();

    function total() {
        let sum = 0;
        table.querySelectorAll('.d-qty').forEach((el) => { sum += parseFloat(el.value) || 0; });
        document.getElementById('dist_total').textContent = fmt(sum);
        const left = document.getElementById('dist_left');
        if (left && max !== null) {
            const diff = Math.round((max - sum) * 100) / 100;
            left.innerHTML = diff < 0 ? '<span class="text-danger">(over by ' + fmt(-diff) + ')</span>' : '(' + fmt(diff) + ' not yet assigned)';
        }
    }

    function addRow() {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td>' + officeSelectHtml + '</td>'
            + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end d-qty" value="1"></td>'
            + '<td><input type="text" class="form-control form-control-sm d-remarks"></td>'
            + '<td><button type="button" class="btn btn-sm btn-link text-danger p-0 d-remove"><i class="fas fa-times"></i></button></td>';
        table.appendChild(tr);
        enhance(tr.querySelector('.d-office'));
        total();
    }

    table.querySelectorAll('.d-office').forEach(enhance);

    document.getElementById('dist_add').addEventListener('click', addRow);
    table.addEventListener('input', total);
    table.addEventListener('click', (e) => { if (e.target.closest('.d-remove')) { e.target.closest('tr').remove(); total(); } });
    total();

    document.getElementById('dist_save').addEventListener('click', function () {
        const rows = [...table.querySelectorAll('tr')].map((tr) => ({
            office_id: tr.querySelector('.d-office').value,
            quantity: tr.querySelector('.d-qty').value,
            remarks: tr.querySelector('.d-remarks').value,
        }));
        $.ajax({
            url: '{{ route("procurement.ppmp.items.distribution.store", [$ppmp, $item]) }}',
            type: 'POST', contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: JSON.stringify({ rows: rows }),
            success: (res) => { toastr.success(res.message); setTimeout(() => window.location.reload(), 500); },
            error: (xhr) => {
                const res = xhr.responseJSON || {};
                toastr.error(res.message || Object.values(res.errors || {})[0]?.[0] || 'Could not save.');
            }
        });
    });
})();
</script>
@endif
