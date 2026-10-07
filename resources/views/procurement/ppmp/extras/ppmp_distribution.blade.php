{{-- Who gets this project's items (procuring unit's assessment). Not printed on the PPMP / APP. --}}
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); @endphp
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
                <div class="text-muted mb-2">Which offices receive these items, based on {{ $ppmp->office->shortName() }}'s assessment. This list is not printed on the PPMP or APP; it can be updated any time and will be used for issuance (ICS / PAR).</div>
                <table class="table table-sm align-middle mb-2" id="dist_table">
                    <thead class="table-light"><tr><th style="width: 38%;">Office</th><th style="width: 12%;" class="text-end">Qty</th><th>Recipient / end-user</th><th>Remarks</th>@if ($canEdit)<th></th>@endif</tr></thead>
                    <tbody>
                        @foreach ($item->distributions as $d)
                            <tr>
                                @if ($canEdit)
                                    <td><select class="form-select form-select-sm d-office">@foreach ($offices as $o)<option value="{{ $o->id }}" @selected($o->id === $d->office_id)>{{ $o->shortName() }} — {{ $o->name }}</option>@endforeach</select></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end d-qty" value="{{ $fmt($d->quantity) }}"></td>
                                    <td><input type="text" class="form-control form-control-sm d-recipient" value="{{ $d->recipient }}"></td>
                                    <td><input type="text" class="form-control form-control-sm d-remarks" value="{{ $d->remarks }}"></td>
                                    <td><button type="button" class="btn btn-sm btn-link text-danger p-0 d-remove"><i class="fas fa-times"></i></button></td>
                                @else
                                    <td>{{ $d->office->shortName() }} — {{ $d->office->name }}</td>
                                    <td class="text-end">{{ $fmt($d->quantity) }}</td>
                                    <td>{{ $d->recipient }}</td>
                                    <td>{{ $d->remarks }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold"><td class="text-end">Total</td><td class="text-end" id="dist_total">{{ $fmt($item->distributions->sum('quantity')) }}</td><td colspan="3" class="text-muted fw-normal">@if ($item->quantity !== null) of {{ $fmt($item->quantity) }} <span id="dist_left"></span>@endif</td></tr>
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
<script>
(function () {
    const table = document.querySelector('#dist_table tbody');
    const max = {{ $item->quantity !== null ? (float) $item->quantity : 'null' }};
    const officeOptions = @json($offices->map(fn ($o) => ['id' => $o->id, 'label' => $o->shortName() . ' — ' . $o->name])->values());
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
        tr.innerHTML = '<td><select class="form-select form-select-sm d-office">' + officeOptions.map((o) => '<option value="' + o.id + '">' + o.label.replace(/</g, '&lt;') + '</option>').join('') + '</select></td>'
            + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end d-qty" value="1"></td>'
            + '<td><input type="text" class="form-control form-control-sm d-recipient"></td>'
            + '<td><input type="text" class="form-control form-control-sm d-remarks"></td>'
            + '<td><button type="button" class="btn btn-sm btn-link text-danger p-0 d-remove"><i class="fas fa-times"></i></button></td>';
        table.appendChild(tr);
        total();
    }

    document.getElementById('dist_add').addEventListener('click', addRow);
    table.addEventListener('input', total);
    table.addEventListener('click', (e) => { if (e.target.closest('.d-remove')) { e.target.closest('tr').remove(); total(); } });
    total();

    document.getElementById('dist_save').addEventListener('click', function () {
        const rows = [...table.querySelectorAll('tr')].map((tr) => ({
            office_id: tr.querySelector('.d-office').value,
            quantity: tr.querySelector('.d-qty').value,
            recipient: tr.querySelector('.d-recipient').value,
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
