@php
    $jr = $pr->kind === \App\Enums\RequestKind::Jr;
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp
<style>
    .pk-search { width: 280px; max-width: 100%; }
    .pk-table thead th { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; color: var(--bs-secondary-color); }
    .pk-row:not(.is-locked) { cursor: pointer; }
    .pk-row.is-locked > td { color: var(--bs-secondary-color); background: var(--bs-tertiary-bg); }
    .pk-table tbody tr:last-child > td { border-bottom: 0; }
    /* Phones: each project is a small card (name, then quantity | budget, then the button) */
    @media (max-width: 767.98px) {
        .pk-search { width: 100%; }
        .pk-table thead { display: none; }
        .pk-table tr.pk-row { display: grid; grid-template-columns: 1fr 1fr; column-gap: 1rem; padding: .75rem 1rem; border-bottom: var(--bs-border-width) solid var(--bs-border-color); }
        .pk-table tr.pk-row:last-of-type { border-bottom: 0; }
        .pk-table tr.pk-row > td { border: 0; padding: 0 !important; background: transparent; }
        .pk-table tr.pk-row > td:first-child { grid-column: 1 / -1; margin-bottom: .5rem; }
        .pk-table tr.pk-row > td:last-child { grid-column: 1 / -1; text-align: start !important; margin-top: .5rem; }
        .pk-table tr.pk-row.is-locked { background: var(--bs-tertiary-bg); }
    }
</style>
<div class="modal fade" id="{{ $modalName }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    {{-- Scrollable: the form is a flex column (page style), so the body scrolls and Save stays in view --}}
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl modal-fullscreen-lg-down">
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

                    @php
                        $pct = fn ($left, $total) => $total > 0 ? (int) max(0, min(100, round(($total - $left) / $total * 100))) : 100;
                        $barColor = fn ($left, $used) => $left < 0 ? 'bg-danger' : ($used >= 90 ? 'bg-warning' : 'bg-success');
                    @endphp
                    {{-- Step 1: what each PPMP project of the PAP still has; choosing one opens the item form --}}
                    <div id="project_picker" @class(['d-none' => $selected])>
                        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                            <div>
                                <div class="fw-semibold">Left to request under {{ $pr->pap->code }}</div>
                                <div class="small text-muted">Approved PPMP, less submitted requests and this {{ $pr->kind->short() }}. Choose a project to add an item.</div>
                            </div>
                            <div class="input-group input-group-sm pk-search">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                <input type="search" id="pick_search" class="form-control border-start-0" placeholder="Search projects…" aria-label="Search projects">
                            </div>
                        </div>
                        <div class="border rounded-3 overflow-hidden">
                            <table class="table table-hover align-middle small mb-0 pk-table">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Project</th>
                                        <th style="width: 22%;">Quantity left</th>
                                        <th style="width: 22%;">Budget left</th>
                                        <th class="text-end pe-3" style="width: 140px;"><span class="visually-hidden">Add</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($projects as $p)
                                        @php
                                            $usedUp = $p['remaining'] <= 0 || ($p['remaining_qty'] !== null && $p['remaining_qty'] <= 0);
                                            $isCurrent = $line && $selected == $p['id'];
                                            $locked = $usedUp && ! $isCurrent;
                                            $qtyUsed = $p['planned_qty'] === null ? 0 : $pct($p['remaining_qty'], $p['planned_qty']);
                                            $budgetUsed = $pct($p['remaining'], $p['budget']);
                                        @endphp
                                        <tr class="pk-row {{ $locked ? 'is-locked' : '' }}" data-id="{{ $p['id'] }}" data-search="{{ \Illuminate\Support\Str::lower($p['description'] . ' ' . $p['mode']) }}">
                                            <td class="ps-3">
                                                <div class="fw-semibold">{{ $p['description'] }}</div>
                                                <div class="text-muted">{{ collect([$p['mode'], $p['unit']])->filter()->implode(' · ') }}</div>
                                            </td>
                                            <td>
                                                @if ($p['planned_qty'] === null)
                                                    <span class="badge text-bg-light border fw-normal">Lot · no quantity limit</span>
                                                @else
                                                    <div @class(['text-danger' => $p['remaining_qty'] < 0])>
                                                        @if ($p['remaining_qty'] < 0)
                                                            <strong>{{ trim($num(-$p['remaining_qty']) . ' ' . $p['unit']) }} over</strong>
                                                        @else
                                                            <strong>{{ $num($p['remaining_qty']) }}</strong> of {{ trim($num($p['planned_qty']) . ' ' . $p['unit']) }}
                                                        @endif
                                                    </div>
                                                    <div class="progress pr-bar mt-1"><div class="progress-bar {{ $barColor($p['remaining_qty'], $qtyUsed) }}" style="width: {{ $qtyUsed }}%"></div></div>
                                                @endif
                                            </td>
                                            <td>
                                                <div @class(['text-danger' => $p['remaining'] < 0])><strong>₱{{ number_format(abs($p['remaining']), 2) }}</strong> {{ $p['remaining'] < 0 ? 'over' : 'left' }}</div>
                                                <div class="progress pr-bar mt-1"><div class="progress-bar {{ $barColor($p['remaining'], $budgetUsed) }}" style="width: {{ $budgetUsed }}%"></div></div>
                                            </td>
                                            <td class="text-end pe-3">
                                                @if ($locked)
                                                    <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border fw-normal">Fully requested</span>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-outline-primary js-pick text-nowrap" data-id="{{ $p['id'] }}" aria-label="Add item from {{ $p['description'] }}">
                                                        <i class="fas fa-plus me-1"></i>{{ $isCurrent ? 'Keep' : 'Add item' }}
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr id="pick_empty" class="d-none"><td colspan="4" class="text-center text-muted py-4">No project matches the search.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Step 2: the item --}}
                    <div id="line_fields" @class(['d-none' => ! $selected])>
                    <div class="mb-3">
                        <select name="ppmp_item_id" class="d-none" aria-hidden="true" tabindex="-1">
                            <option value=""></option>
                            @foreach ($projects as $p)
                                <option value="{{ $p['id'] }}" @selected($selected == $p['id'])>{{ $p['description'] }}</option>
                            @endforeach
                        </select>
                        {{-- The chosen project: what is left of its planned quantity and budget (filled by the script below) --}}
                        <div id="project_info" class="d-none rounded-3 border bg-body-tertiary p-3">
                            <div class="d-flex justify-content-between gap-2">
                                <div class="min-w-0">
                                    <div class="pr-label">PPMP project ({{ $pr->pap->code }})</div>
                                    <div id="pi_name" class="fw-semibold"></div>
                                    <div id="pi_meta" class="small text-muted"></div>
                                </div>
                                <button type="button" class="btn btn-sm btn-light border flex-shrink-0 align-self-start" id="btn_change_project"><i class="fas fa-exchange-alt me-1"></i> Change</button>
                            </div>
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
                    </div>{{-- #line_fields --}}
                </div>

                <div class="modal-footer bg-body-tertiary border-0 py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn_save_line" class="btn btn-sm btn-primary px-3 {{ $selected ? '' : 'd-none' }}"><i class="fas fa-save me-1"></i> Save</button>
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
            $('#pi_name').text(p.description);
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

    // Pick a project from the list -> the item form; Change goes back to the list
    function showPicker(on) {
        $('#project_picker').toggleClass('d-none', !on);
        $('#line_fields').toggleClass('d-none', on);
        $('#btn_save_line').toggleClass('d-none', on);
        $('#line_error_summary').addClass('d-none');
    }
    // The whole row picks the project (except used-up ones); search filters by name and mode
    form.on('click', '.pk-row:not(.is-locked)', function (e) {
        if (!$(e.target).closest('.js-pick').length) $(this).find('.js-pick').trigger('click');
    });
    $('#pick_search').on('input', function () {
        const q = this.value.trim().toLowerCase();
        let shown = 0;
        form.find('.pk-row').each(function () {
            const hit = !q || this.dataset.search.includes(q);
            $(this).toggleClass('d-none', !hit);
            shown += hit ? 1 : 0;
        });
        $('#pick_empty').toggleClass('d-none', shown > 0);
    }).on('keydown', function (e) {
        // Enter picks the first match
        if (e.key === 'Enter') { e.preventDefault(); form.find('.pk-row:not(.d-none):not(.is-locked) .js-pick').first().trigger('click'); }
    });
    form.on('click', '.js-pick', function () {
        const id = String($(this).data('id'));
        const changed = form.find('[name="ppmp_item_id"]').val() !== id;
        form.find('[name="ppmp_item_id"]').val(id);
        if (changed) form.find('[name="ppmp_item_id"]').trigger('change');
        showPicker(false);
        setTimeout(() => form.find('[name="quantity"]').trigger('focus'));
    });
    $('#btn_change_project').on('click', () => showPicker(true));
})();
</script>
