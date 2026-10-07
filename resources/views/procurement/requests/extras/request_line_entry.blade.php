@php $jr = $pr->kind === \App\Enums\RequestKind::Jr; @endphp
<div class="modal fade" id="{{ $modalName }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-box text-primary me-2"></i>{{ $line ? 'Edit' : 'Add' }} Item</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="form_request_line" autocomplete="off" novalidate>
                @csrf
                <input type="hidden" name="id" value="{{ $line?->id }}">
                <div class="modal-body p-4">
                    <div id="line_error_summary" class="alert alert-danger d-none py-2 px-3 small mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i> <span></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">PPMP project ({{ $pr->pap->code }})</label>
                        <select name="ppmp_item_id" class="form-select form-select-sm" required>
                            <option value="">Choose…</option>
                            @foreach ($projects as $p)
                                <option value="{{ $p['id'] }}" @selected($line && $line->ppmp_item_id == $p['id']) @disabled($p['remaining'] <= 0 && ! ($line && $line->ppmp_item_id == $p['id']))>
                                    {{ $p['description'] }} — ₱{{ number_format($p['remaining'], 2) }} left
                                </option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                        <div class="form-text" id="project_info"></div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">{{ $jr ? 'Property No.' : 'Stock No.' }}</label>
                            <input type="text" name="stock_no" class="form-control form-control-sm" value="{{ $line?->stock_no }}" maxlength="50">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label small fw-semibold text-muted mb-1">Item name</label>
                            <input type="text" name="description" class="form-control form-control-sm" value="{{ $line?->description }}" maxlength="500" required>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Specifications</label>
                        <textarea name="specifications" class="form-control form-control-sm" rows="4">{{ $line?->specifications }}</textarea>
                        <div class="invalid-feedback"></div>
                        <div class="form-text d-none" id="standard_note"><i class="fas fa-lock me-1"></i>Standard item: TWG specifications and the standard unit cost apply.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Unit</label>
                            <input type="text" name="unit" class="form-control form-control-sm" value="{{ $line?->unit }}" maxlength="50" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Quantity</label>
                            <input type="number" name="quantity" class="form-control form-control-sm" value="{{ $line ? (float) $line->quantity : '' }}" min="0.01" step="0.01" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Unit cost</label>
                            <input type="number" name="unit_cost" class="form-control form-control-sm" value="{{ $line ? (float) $line->unit_cost : '' }}" min="0.01" step="0.01" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Total</label>
                            <div class="form-control form-control-sm bg-light fw-semibold" id="line_total">0.00</div>
                        </div>
                    </div>
                    <div class="alert alert-danger small py-2 d-none" id="over_note"></div>

                    @if ($jr)
                        <div class="mb-0">
                            <label class="form-label small fw-semibold text-muted mb-1">Nature of work</label>
                            <textarea name="nature_of_work" class="form-control form-control-sm" rows="3">{{ $line?->nature_of_work }}</textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                    @endif
                </div>

                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn_save_line" class="btn btn-sm btn-primary px-3"><i class="fas fa-save me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    const projects = @json($projects);
    const form = $('#form_request_line');
    const peso = (n) => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const current = () => projects.find((p) => String(p.id) === form.find('[name="ppmp_item_id"]').val());

    function refresh() {
        const p = current();
        const total = (parseFloat(form.find('[name="quantity"]').val()) || 0) * (parseFloat(form.find('[name="unit_cost"]').val()) || 0);
        $('#line_total').text(peso(total));
        const over = p && total > p.remaining + 0.001;
        $('#over_note').toggleClass('d-none', !over).text(over ? 'Over the PPMP budget: only ₱' + peso(p.remaining) + ' is left on this project.' : '');
    }

    // Choosing a project fills in its name, unit, cost and (standard items) specifications
    form.find('[name="ppmp_item_id"]').on('change', function (e, initial) {
        const p = current();
        $('#project_info').text(p ? [p.mode, p.unit_cost !== null ? 'PPMP unit cost ₱' + peso(p.unit_cost) : null, '₱' + peso(p.remaining) + ' left'].filter(Boolean).join(' · ') : '');
        $('#standard_note').toggleClass('d-none', !(p && p.standard));
        form.find('[name="unit_cost"]').prop('readonly', !!(p && p.standard));
        if (p && !initial) {
            form.find('[name="description"]').val(p.description);
            if (p.unit) form.find('[name="unit"]').val(p.unit);
            if (p.unit_cost !== null) form.find('[name="unit_cost"]').val(p.unit_cost);
            if (p.specs) form.find('[name="specifications"]').val(p.specs);
        }
        if (p && p.standard) form.find('[name="unit_cost"]').val(p.unit_cost);
        refresh();
    }).trigger('change', [true]);

    form.find('[name="quantity"], [name="unit_cost"]').on('input', refresh);
})();
</script>
