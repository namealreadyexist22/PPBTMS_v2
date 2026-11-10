{{-- Budget-only line of a PAP: MOOE that is not procured (travel, training, representation, subsidies, ...) --}}
<div class="modal fade" id="BUDGET_LINE_MODAL" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i class="fas {{ $line ? 'fa-edit text-warning' : 'fa-receipt text-muted' }} me-2"></i>{{ $line ? 'Edit Budget Line' : 'Add Budget Line' }}
                    {{-- Hover only: the dialog focuses its first focusable element when it opens, which would show it --}}
                    <a role="button" class="text-muted ms-1 fs-6" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-placement="bottom"
                       title="Budget-only line" data-bs-content="MOOE this PAP needs that is not procured: traveling, training, representation, subsidies and the like. It counts toward the department's budget and the Corporate Operating Budget, but never goes to the APP, PRs or RFQs."><i class="far fa-question-circle"></i></a>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form_budget_line" autocomplete="off" novalidate>
                @csrf
                @if ($line)<input type="hidden" name="id" value="{{ $line->id }}">@endif
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">PAP</label>
                            <select name="budget_pap_id" class="form-select form-select-sm" required>
                                @foreach ($paps as $item)
                                    <option value="{{ $item->id }}" @selected(($line->budget_pap_id ?? $pap->id) == $item->id)>{{ $item->label() }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">Expense Class</label>
                            <select name="expense_class_id" class="form-select form-select-sm" required>
                                <option value="">Choose...</option>
                                @foreach ($expenseClasses as $class)
                                    <option value="{{ $class->id }}" @selected(($line->expense_class_id ?? null) == $class->id)>{{ $class->name }}{{ $class->is_active ? '' : ' [inactive]' }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">Description <span class="fw-normal">(optional: not printed; tells the PAP's lines apart)</span></label>
                            <input type="text" name="description" class="form-control form-control-sm" maxlength="1000" value="{{ $line->description ?? '' }}" placeholder="e.g. Travel for the regional consultations">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Amount (PHP)</label>
                            <input type="text" inputmode="decimal" name="amount" class="form-control form-control-sm text-end js-money" required
                                   value="{{ $line ? number_format((float) $line->amount, 2) : '' }}" placeholder="0.00">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Location <span class="fw-normal">(optional)</span></label>
                            <select name="location_id" class="form-select form-select-sm">
                                <option value="">—</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected(($line->location_id ?? null) == $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">Source of Funds</label>
                            <select name="fund_source_id" class="form-select form-select-sm" required>
                                @foreach ($fundSources as $fund)
                                    <option value="{{ $fund->id }}" @selected($defaultFundId == $fund->id)>{{ $fund->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">Remarks <span class="fw-normal">(optional)</span></label>
                            <input type="text" name="remarks" class="form-control form-control-sm" maxlength="1000" value="{{ $line->remarks ?? '' }}">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fas fa-save me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    (function () {
        const form = document.getElementById('form_budget_line');
        const peso = (n) => n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        form.closest('.modal').querySelectorAll('[data-bs-toggle="popover"]').forEach((el) => bootstrap.Popover.getOrCreateInstance(el, { container: form.closest('.modal') }));
        // Money: thousands commas while typing, two decimals on leaving the field
        const amount = form.querySelector('[name="amount"]');
        amount.addEventListener('input', function () {
            const raw = this.value.replace(/[^0-9.]/g, '');
            const [w, d] = raw.split('.');
            this.value = (w || '').replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (d !== undefined ? '.' + d.slice(0, 2) : '');
        });
        amount.addEventListener('blur', function () {
            const n = parseFloat(this.value.replace(/,/g, ''));
            if (!isNaN(n)) this.value = peso(n);
        });
    })();
</script>
