<div class="modal fade" id="{{ $modalName }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-light py-3 border-bottom border-light">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                    @if ($item)
                        <i class="fas fa-edit text-warning me-2"></i> Edit Procurement Project
                    @else
                        <i class="fas fa-plus-circle text-primary me-2"></i> Add Procurement Project
                    @endif
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="form_ppmp_item" autocomplete="off" novalidate>
                @csrf
                @if ($item)
                    <input type="hidden" name="id" value="{{ $item->id }}">
                @endif

                <div class="modal-body p-4">
                    <div id="item_error_summary" class="alert alert-danger d-none py-2 px-3 small rounded mb-3 shadow-sm">
                        <i class="fas fa-exclamation-triangle me-1"></i> <span>Please correct the highlighted errors below.</span>
                    </div>

                    {{-- 1. Procurement project --}}
                    <small class="text-uppercase fw-bold text-secondary d-block mb-2" style="font-size: 0.72rem;">Procurement Project</small>
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-muted mb-1">PAP</label>
                            <select name="ppmp_pap_id" class="form-select form-select-sm" required>
                                @foreach ($paps as $pap)
                                    <option value="{{ $pap->id }}" @selected(($item->ppmp_pap_id ?? $selectedPap) == $pap->id)>{{ $pap->label() }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold text-muted mb-1">General Description and Objective</label>
                            <textarea name="description" class="form-control form-control-sm" rows="2" required
                                placeholder="e.g. Procurement of office supplies for the operations of the Planning Section">{{ $item->description ?? '' }}</textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted mb-1">Type of Project</label>
                            <select name="project_type" class="form-select form-select-sm" required>
                                @foreach ($projectTypes as $type)
                                    <option value="{{ $type->value }}" @selected(($item?->project_type) === $type)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        @if ($catalog->isNotEmpty())
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold text-muted mb-1">Catalog Item (optional)</label>
                                <select name="item_id" class="form-select form-select-sm">
                                    <option value="">— None —</option>
                                    @foreach ($catalog as $catalogItem)
                                        <option value="{{ $catalogItem->id }}" @selected(($item->item_id ?? null) == $catalogItem->id)>{{ $catalogItem->code }} — {{ $catalogItem->name }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>
                        @endif
                    </div>

                    {{-- 2. Quantity and size --}}
                    <small class="text-uppercase fw-bold text-secondary d-block mb-2" style="font-size: 0.72rem;">Quantity and Size (QTY and Specs)</small>
                    <div class="row g-3 mb-3">
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-muted mb-1">Quantity</label>
                            <input type="number" step="0.01" min="0" name="quantity" class="form-control form-control-sm js-budget-calc" value="{{ $item?->quantity !== null ? (float) $item->quantity : '' }}">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-muted mb-1">Unit</label>
                            <select name="unit_id" class="form-select form-select-sm">
                                <option value="">—</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}" @selected(($item->unit_id ?? null) == $unit->id)>{{ $unit->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Unit Price (PHP)</label>
                            <input type="text" inputmode="decimal" name="unit_cost" class="form-control form-control-sm text-end js-budget-calc js-money"
                                value="{{ $item?->unit_cost !== null ? number_format((float) $item->unit_cost, 2) : '' }}" placeholder="0.00">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-muted mb-1">Specifications (optional)</label>
                            <textarea name="quantity_size" class="form-control form-control-sm" rows="2" placeholder="e.g. 3.5&quot; HDD, SATA, 7200 RPM">{{ $item->quantity_size ?? '' }}</textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    {{-- 3. Procurement details --}}
                    <small class="text-uppercase fw-bold text-secondary d-block mb-2" style="font-size: 0.72rem;">Procurement Details</small>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Recommended Mode of Procurement</label>
                            <select name="procurement_mode_id" class="form-select form-select-sm" required>
                                <option value="">Choose...</option>
                                @foreach ($modes as $mode)
                                    <option value="{{ $mode->id }}" @selected(($item->procurement_mode_id ?? null) == $mode->id)>{{ $mode->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" name="pre_proc_conference" value="1" id="pre_proc_conference"
                                    @checked($item->pre_proc_conference ?? false)>
                                <label class="form-check-label small" for="pre_proc_conference">Pre-Procurement Conference required</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted mb-1">Start of Procurement Activity</label>
                            <input type="month" name="proc_start" class="form-control form-control-sm" required value="{{ $item?->proc_start?->format('Y-m') ?? $ppmp->fiscal_year . '-01' }}">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted mb-1">End of Procurement Activity</label>
                            <input type="month" name="proc_end" class="form-control form-control-sm" required value="{{ $item?->proc_end?->format('Y-m') ?? $ppmp->fiscal_year . '-01' }}">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted mb-1">Expected Delivery / Implementation</label>
                            <input type="text" name="delivery_period" class="form-control form-control-sm" value="{{ $item->delivery_period ?? '' }}" placeholder="e.g. March {{ $ppmp->fiscal_year }}">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    {{-- 4. Funding --}}
                    <small class="text-uppercase fw-bold text-secondary d-block mb-2" style="font-size: 0.72rem;">Funding</small>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted mb-1">Source of Funds</label>
                            <select name="fund_source_id" class="form-select form-select-sm" required>
                                <option value="">Choose...</option>
                                @foreach ($fundSources as $fund)
                                    <option value="{{ $fund->id }}" data-group="{{ $fund->fund_group?->value }}" @selected(($item->fund_source_id ?? null) == $fund->id)>{{ $fund->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Allotment Class</label>
                            <select name="allotment_class" class="form-select form-select-sm" required>
                                @foreach ($allotments as $allotment)
                                    <option value="{{ $allotment->value }}" @selected(($item?->allotment_class ?? \App\Enums\AllotmentClass::Mooe) === $allotment)>{{ $allotment->label() }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-muted mb-1">Estimated Budget / ABA (PHP)</label>
                            <input type="text" inputmode="decimal" name="estimated_budget" class="form-control form-control-sm text-end js-money" required
                                value="{{ $item ? number_format((float) $item->estimated_budget, 2) : '' }}" placeholder="0.00">
                            <div class="invalid-feedback"></div>
                            <div class="form-text" id="budget_formula"></div>
                            @if ($item && (float) $item->committed_amount > 0)
                                <div class="form-text text-warning">Already charged by PRs: {{ number_format((float) $item->committed_amount, 2) }}. The budget cannot go below this.</div>
                            @endif
                        </div>
                    </div>

                    {{-- Budget allocation left for this PPMP, before and after this project --}}
                    <div id="budget_left" class="alert py-2 px-3 small mb-3 d-none"></div>

                    {{-- 5. Others --}}
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Attached Supporting Documents</label>
                            <textarea name="supporting_documents" class="form-control form-control-sm" rows="2" placeholder="e.g. Technical specifications, market study">{{ $item->supporting_documents ?? '' }}</textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Remarks</label>
                            <textarea name="remarks" class="form-control form-control-sm" rows="2">{{ $item->remarks ?? '' }}</textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top border-light py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn_save_item" class="btn btn-sm btn-primary px-3">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        const form = document.getElementById('form_ppmp_item');
        const qty = form.querySelector('[name="quantity"]');
        const price = form.querySelector('[name="unit_cost"]');
        const budget = form.querySelector('[name="estimated_budget"]');
        const formula = document.getElementById('budget_formula');
        const peso = (n) => n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const toNumber = (value) => parseFloat(String(value).replace(/,/g, ''));

        // Money fields: add thousands commas while typing (1234567.5 -> 1,234,567.5), keep the cursor in place
        function formatMoney(el) {
            const caret = el.selectionStart;
            const digitsBeforeCaret = el.value.slice(0, caret).replace(/[^0-9.]/g, '').length;

            let raw = el.value.replace(/[^0-9.]/g, '');
            const dot = raw.indexOf('.');
            if (dot !== -1) {
                raw = raw.slice(0, dot + 1) + raw.slice(dot + 1).replace(/\./g, '').slice(0, 2);   // one dot, 2 decimals
            }
            const [whole, decimals] = raw.split('.');
            const grouped = (whole || '').replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            el.value = grouped + (decimals !== undefined ? '.' + decimals : '');

            let pos = 0, seen = 0;
            while (pos < el.value.length && seen < digitsBeforeCaret) {
                if (/[0-9.]/.test(el.value[pos])) seen++;
                pos++;
            }
            el.setSelectionRange(pos, pos);
        }

        form.querySelectorAll('.js-money').forEach(function (el) {
            el.addEventListener('input', function () { if (!el.readOnly) formatMoney(el); });
            el.addEventListener('blur', function () {   // 1,234 -> 1,234.00
                if (el.value !== '' && !isNaN(toNumber(el.value))) el.value = peso(toNumber(el.value));
            });
        });

        // Estimated budget = quantity x unit price (locked while both are filled; type it for lot budgets)
        function recalc() {
            const q = parseFloat(qty.value);
            const p = toNumber(price.value);

            if (!isNaN(q) && !isNaN(p) && price.value !== '') {
                const total = Math.round(q * Math.round(p * 100)) / 100;
                budget.value = peso(total);
                budget.readOnly = true;
                budget.classList.add('bg-light');
                formula.textContent = '= ' + q.toLocaleString('en-PH') + ' × ₱' + peso(p) + ' = ₱' + peso(total);
            } else {
                budget.readOnly = false;
                budget.classList.remove('bg-light');
                formula.textContent = 'Enter quantity and unit price to compute, or type the budget (e.g. for a lot).';
            }
        }

        // Budget left for this PPMP per fund (COB / SIDA), from the tightest allocation above the office
        const budgetBase = @json($budgetBase);
        const fundSelect = form.querySelector('[name="fund_source_id"]');
        const fundLabels = @json(collect(\App\Enums\FundGroup::cases())->mapWithKeys(fn ($f) => [$f->value => $f->label()]));
        const leftBox = document.getElementById('budget_left');

        function showBudgetLeft() {
            const group = fundSelect.selectedOptions[0]?.dataset.group || '';
            if (!group) { leftBox.classList.add('d-none'); return; }

            const label = fundLabels[group] || group;
            leftBox.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-secondary');

            if (!budgetBase[group]) {
                leftBox.classList.add('alert-secondary');
                leftBox.innerHTML = '<i class="fas fa-coins me-1"></i> No ' + label + ' budget allocation set for this office yet — not checked.';
                return;
            }

            const before = budgetBase[group].left / 100;
            const amount = toNumber(budget.value) || 0;
            const after = Math.round((before - amount) * 100) / 100;

            leftBox.classList.add(after < 0 ? 'alert-danger' : 'alert-success');
            leftBox.innerHTML = '<i class="fas fa-coins me-1"></i> ' + label + ' budget left for this PPMP (' + budgetBase[group].office + '): <strong>₱' + peso(before) + '</strong>'
                + ' → after this project: <strong>' + (after < 0 ? '−₱' + peso(-after) + ' (over budget; the PPMP cannot be submitted)' : '₱' + peso(after)) + '</strong>';
        }

        form.querySelectorAll('.js-budget-calc').forEach((el) => el.addEventListener('input', recalc));
        form.querySelectorAll('.js-budget-calc, [name="estimated_budget"]').forEach((el) => el.addEventListener('input', showBudgetLeft));
        fundSelect.addEventListener('change', showBudgetLeft);
        recalc();
        showBudgetLeft();
    })();
</script>
