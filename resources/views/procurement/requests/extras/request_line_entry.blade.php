@php
    $jr = $pr->kind === \App\Enums\RequestKind::Jr;
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp
<div class="modal fade" id="{{ $modalName }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    {{-- Scrollable: the form is a flex column (page style), so the body scrolls and Save stays in view --}}
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="pr-ico"><i class="fas fa-box"></i></span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0">{{ $line ? 'Edit' : 'Add' }} Item</h5>
                        <div class="small text-muted">{{ $pr->title() }} · charge to {{ $pr->pap->code }}</div>
                    </div>
                </div>
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
                                @php
                                    $left = '₱' . number_format($p['remaining'], 2) . ' left';
                                    $qtyText = match (true) {
                                        $p['planned_qty'] === null => 'Lot',
                                        $p['remaining_qty'] < 0    => trim($num(-$p['remaining_qty']) . ' ' . $p['unit']) . ' over',
                                        default                    => $num($p['remaining_qty']) . ' of ' . trim($num($p['planned_qty']) . ' ' . $p['unit']),
                                    };
                                @endphp
                                @php $usedUp = $p['remaining'] <= 0 || ($p['remaining_qty'] !== null && $p['remaining_qty'] <= 0); @endphp
                                <option value="{{ $p['id'] }}" @selected($selected == $p['id']) @disabled($usedUp && ! ($line && $selected == $p['id']))>
                                    {{ $p['description'] }} — {{ $qtyText }} · {{ $left }}
                                </option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                        {{-- The chosen project: what is left of its planned quantity and budget (filled by the script below) --}}
                        <div id="project_info" class="d-none rounded-3 border bg-body-tertiary p-3 mt-2">
                            <div id="pi_meta" class="small text-muted"></div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <div class="pr-label mb-1">Quantity</div>
                                    <div id="pi_qty" class="small fw-semibold"></div>
                                    <div class="progress pr-bar mt-1" id="pi_qty_progress"><div class="progress-bar" id="pi_qty_bar" style="width: 0%"></div></div>
                                    <span id="pi_lot" class="badge text-bg-light border d-none">Lot · no quantity limit</span>
                                </div>
                                <div class="col-md-6">
                                    <div class="pr-label mb-1">Budget</div>
                                    <div id="pi_budget" class="small fw-semibold"></div>
                                    <div class="progress pr-bar mt-1"><div class="progress-bar" id="pi_budget_bar" style="width: 0%"></div></div>
                                </div>
                            </div>
                            <div id="after_line" class="small mt-2"></div>
                        </div>
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
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Unit</label>
                            <input type="text" name="unit" class="form-control form-control-sm" value="{{ $line?->unit }}" maxlength="50" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Quantity</label>
                            <div class="input-group input-group-sm has-validation">
                                <input type="number" name="quantity" class="form-control" value="{{ $line ? (float) $line->quantity : '' }}" min="0.01" step="0.01" required>
                                <span class="input-group-text" id="qty_unit">—</span>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="form-text" id="qty_hint"></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Unit cost</label>
                            <div class="input-group input-group-sm has-validation">
                                <span class="input-group-text">₱</span>
                                <input type="number" name="unit_cost" class="form-control" value="{{ $line ? (float) $line->unit_cost : '' }}" min="0.01" step="0.01" required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Total</label>
                            <div class="form-control form-control-sm bg-light fw-bold" id="line_total">0.00</div>
                        </div>
                    </div>
                    <div class="alert alert-danger d-flex gap-2 small py-2 d-none" id="qty_note">
                        <i class="fas fa-exclamation-circle mt-1"></i><div></div>
                    </div>
                    <div class="alert alert-danger d-flex gap-2 small py-2 d-none" id="over_note">
                        <i class="fas fa-exclamation-circle mt-1"></i><div></div>
                    </div>

                    @if ($jr)
                        <div class="mb-0">
                            <label class="form-label small fw-semibold text-muted mb-1">Nature of work</label>
                            <textarea name="nature_of_work" class="form-control form-control-sm" rows="3">{{ $line?->nature_of_work }}</textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                    @endif
                </div>

                <div class="modal-footer bg-body-tertiary border-0 py-2">
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
    const num = (n) => Number(n).toLocaleString('en-PH', { maximumFractionDigits: 2 });
    const withUnit = (n, unit) => unit ? n + ' ' + unit : String(n);
    const hundredths = (n) => Math.round((parseFloat(n) || 0) * 100);
    const current = () => projects.find((p) => String(p.id) === form.find('[name="ppmp_item_id"]').val());
    const isLot = (p) => p.planned_qty === null;

    // Share used once this line is added (in hundredths / centavos), as on the Budget page:
    // green, amber from 90%, red when over
    function bar(el, total, left, thisLine) {
        const used = total > 0 ? Math.min(100, Math.max(0, Math.round((total - left + thisLine) / total * 100))) : 100;
        $(el).css('width', used + '%').removeClass('bg-success bg-warning bg-danger')
            .addClass(left - thisLine < 0 ? 'bg-danger' : (used >= 90 ? 'bg-warning' : 'bg-success'))
            .parent().attr('title', used + '% used');
    }

    function refresh() {
        const p = current();
        const qty = hundredths(form.find('[name="quantity"]').val());
        const totalCents = Math.round(qty * hundredths(form.find('[name="unit_cost"]').val()) / 100);
        const unit = form.find('[name="unit"]').val().trim() || (p && p.unit) || '';
        $('#qty_unit').text(unit || '—');
        $('#line_total').text(peso(totalCents / 100));

        // Budget and quantity are both hard limits (checked again on save): Save stays off while over either
        const over = !!p && totalCents > hundredths(p.remaining);
        $('#line_total').toggleClass('text-danger', over);
        $('#over_note').toggleClass('d-none', !over).find('div').text(over ? 'Over the PPMP budget: only ₱' + peso(p.remaining) + ' is left on this project.' : '');

        const qtyLeft = p && !isLot(p) ? hundredths(p.remaining_qty) : null;
        const overQty = qtyLeft !== null && qty > 0 && qty > qtyLeft;
        form.find('[name="quantity"]').toggleClass('is-invalid', overQty);
        $('#qty_note').toggleClass('d-none', !overQty).find('div').text(!overQty ? '' : (qtyLeft > 0
            ? 'Over the PPMP quantity: only ' + num(p.remaining_qty) + ' of ' + withUnit(num(p.planned_qty), p.unit) + ' are left on this project.'
            : 'The planned quantity (' + withUnit(num(p.planned_qty), p.unit) + ') is already requested. Nothing more can be requested on this project.'));
        $('#btn_save_line').prop('disabled', !!form.data('saving') || over || overQty);

        if (!p) return;
        const budgetLeft = hundredths(p.remaining) - totalCents;
        const money = budgetLeft < 0 ? '₱' + peso(-budgetLeft / 100) + ' over' : '₱' + peso(budgetLeft / 100) + ' left';
        let after = money;
        if (qtyLeft !== null) {
            const left = qtyLeft - qty;
            after = (left < 0 ? withUnit(num(-left / 100), p.unit) + ' over' : withUnit(num(left / 100), p.unit)) + ' · ' + money;
            bar('#pi_qty_bar', hundredths(p.planned_qty), qtyLeft, qty);
        }
        bar('#pi_budget_bar', hundredths(p.budget), hundredths(p.remaining), totalCents);
        $('#after_line').text('After this item: ' + after).removeClass('text-danger text-warning-emphasis text-body-secondary')
            .addClass(budgetLeft < 0 || overQty ? 'text-danger' : 'text-body-secondary');
    }

    // Choosing a project fills in its name, unit, cost and (standard items) specifications
    form.find('[name="ppmp_item_id"]').on('change', function (e, initial) {
        const p = current();
        $('#project_info').toggleClass('d-none', !p);
        $('#standard_note').toggleClass('d-none', !(p && p.standard));
        form.find('[name="unit_cost"]').prop('readonly', !!(p && p.standard));
        $('#qty_hint').text('').removeClass('text-danger');

        if (p) {
            const lot = isLot(p);
            $('#pi_meta').text([p.mode, p.unit_cost !== null ? 'PPMP unit cost ₱' + peso(p.unit_cost) : null].filter(Boolean).join(' · '));
            $('#pi_lot').toggleClass('d-none', !lot);
            $('#pi_qty, #pi_qty_progress').toggleClass('d-none', lot);
            if (!lot) {
                $('#pi_qty').text(p.remaining_qty < 0 ? withUnit(num(-p.remaining_qty), p.unit) + ' over · planned ' + num(p.planned_qty) : num(p.remaining_qty) + ' of ' + withUnit(num(p.planned_qty), p.unit) + ' left')
                    .toggleClass('text-danger', p.remaining_qty < 0);
            }
            $('#pi_budget').text(p.remaining < 0 ? '₱' + peso(-p.remaining) + ' over' : '₱' + peso(p.remaining) + ' of ₱' + peso(p.budget) + ' left')
                .toggleClass('text-danger', p.remaining < 0);
            $('#qty_hint').text(lot ? 'Lot · no quantity limit' : (p.remaining_qty > 0 ? 'Up to ' + withUnit(num(p.remaining_qty), p.unit) + ' on the PPMP' : 'Planned quantity already used'))
                .toggleClass('text-danger', !lot && p.remaining_qty <= 0);
        }
        // The spinner stops at what is left
        form.find('[name="quantity"]').attr('max', p && !isLot(p) && p.remaining_qty > 0 ? p.remaining_qty : null);

        if (p && !initial) {
            form.find('[name="description"]').val(p.description);
            if (p.unit) form.find('[name="unit"]').val(p.unit);
            if (p.unit_cost !== null) form.find('[name="unit_cost"]').val(p.unit_cost);
            if (p.specs) form.find('[name="specifications"]').val(p.specs);
        }
        if (p && p.standard) form.find('[name="unit_cost"]').val(p.unit_cost);
        refresh();
    }).trigger('change', [{{ $line ? 'true' : 'false' }}]);

    form.find('[name="quantity"], [name="unit_cost"], [name="unit"]').on('input', refresh);
})();
</script>
