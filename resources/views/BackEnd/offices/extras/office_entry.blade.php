<div class="modal fade" id="{{ $modalName }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-light py-3 border-bottom border-light">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                    @if ($office)
                        <i class="fas fa-edit text-warning me-2"></i> Update {{ $office->typeLabel() }}
                    @else
                        <i class="fas fa-sitemap text-primary me-2"></i> Add {{ \App\Models\Procurement\Office::TYPES[$type] }}
                    @endif
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="form_office_entry" autocomplete="off" novalidate>
                @csrf
                @if ($office)
                    <input type="hidden" name="id" value="{{ $office->id }}">
                @endif

                <div class="modal-body p-4">
                    <div id="modal_error_summary" class="alert alert-danger d-none py-2 px-3 small rounded mb-3 shadow-sm">
                        <i class="fas fa-exclamation-triangle me-1"></i> <span>Please correct the highlighted errors below.</span>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Type</label>
                            <select name="type" class="form-select form-select-sm" id="office_type">
                                @foreach (\App\Models\Procurement\Office::TYPES as $value => $label)
                                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-6" id="office_budget_fund_wrap" style="{{ $type === 'department' ? '' : 'display: none;' }}">
                            <label class="form-label small fw-semibold text-muted mb-1">Budget fund</label>
                            <select name="budget_fund" class="form-select form-select-sm">
                                @foreach (\App\Enums\FundGroup::cases() as $fund)
                                    <option value="{{ $fund->value }}" @selected(($office->budget_fund ?? \App\Enums\FundGroup::Regular) === $fund)>{{ $fund->label() }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Office No.</label>
                            <input type="text" name="code" class="form-control form-control-sm" value="{{ $office->code ?? '' }}" placeholder="e.g. 05000" inputmode="numeric">
                            <div class="invalid-feedback"></div>
                            <div class="form-text">Used in PPMP numbers, e.g. <b>05000</b>-2027-V1. A department with divisions leaves it blank; its Office of the Manager has it.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Acronym</label>
                            <input type="text" name="acronym" class="form-control form-control-sm" value="{{ $office->acronym ?? '' }}" placeholder="e.g. PPSPD">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Name</label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $office->name ?? '' }}" placeholder="e.g. Planning Section" required>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Under</label>
                        <select name="parent_id" class="form-select form-select-sm">
                            <option value="">— None (top-level department) —</option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}" @selected($parentId == $parent->id)>
                                    {{ $parent->typeLabel() }}: {{ $parent->label() }}
                                </option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                        <div class="form-text">This office's PPMP is approved by the nearest head above it. A top-level office's head approves its own.</div>
                    </div>


                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Head</label>
                        <select name="head_user_id" class="form-select form-select-sm">
                            <option value="">— Not set —</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(($office->head_user_id ?? null) == $user->id)>
                                    {{ $user->fullname }}{{ $user->designation ? ' (' . $user->designation . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_consolidating" value="1" id="office_is_consolidating"
                            @checked($office->is_consolidating ?? false)>
                        <label class="form-check-label small" for="office_is_consolidating">
                            Approves PPMPs <span class="text-muted">— the PPMPs of the units under it are combined into its Division PPMP, which its head approves and submits to BAC (usually divisions)</span>
                        </label>
                    </div>



                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="office_is_active"
                            @checked($office->is_active ?? true)>
                        <label class="form-check-label small" for="office_is_active">Active</label>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top border-light py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn_save_office" class="btn btn-sm btn-primary px-3">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('office_type').addEventListener('change', function () {
        document.getElementById('office_budget_fund_wrap').style.display = this.value === 'department' ? '' : 'none';
    });
</script>
