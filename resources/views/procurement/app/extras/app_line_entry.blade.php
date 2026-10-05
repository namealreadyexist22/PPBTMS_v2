<div class="modal fade" id="{{ $modalName }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content border-0 shadow" id="form_app_line" novalidate>
            @csrf
            <input type="hidden" name="id" value="{{ $line->id }}">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit text-warning me-2"></i>Edit APP Line</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 small">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted mb-1">PAP Code</label>
                        <input type="text" name="pap_code" class="form-control form-control-sm" value="{{ $line->pap_code }}">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted mb-1">PAP Title</label>
                        <input type="text" name="pap_title" class="form-control form-control-sm" value="{{ $line->pap_title }}">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="is_cse" value="1" id="is_cse" @checked($line->is_cse)>
                            <label class="form-check-label" for="is_cse">CSE from PS-DBM</label>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted mb-1">Project Title (Column 1)</label>
                        <textarea name="project_title" class="form-control form-control-sm" rows="2" required>{{ $line->project_title }}</textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted mb-1">End-User or Implementing Unit (Column 2)</label>
                        <textarea name="end_user" class="form-control form-control-sm" rows="2" required>{{ $line->end_user }}</textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-muted mb-1">General Description of the Project (Column 3)</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2" required>{{ $line->description }}</textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold text-muted mb-1">Mode of Procurement (Column 4)</label>
                        <select name="procurement_mode_id" class="form-select form-select-sm">
                            @foreach ($modes as $mode)
                                <option value="{{ $mode->id }}" @selected($line->procurement_mode_id === $mode->id)>{{ $mode->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="early_procurement" value="1" id="early_procurement" @checked($line->early_procurement)>
                            <label class="form-check-label" for="early_procurement">Early Procurement (Col. 5)</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted mb-1">Criteria for Bid Evaluation (Col. 6)</label>
                        <input type="text" name="bid_criteria" class="form-control form-control-sm" value="{{ $line->bid_criteria }}" placeholder="e.g. LCRB">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted mb-1">Start (Column 7)</label>
                        <input type="month" name="proc_start" class="form-control form-control-sm" value="{{ $line->proc_start->format('Y-m') }}">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted mb-1">End (Column 8)</label>
                        <input type="month" name="proc_end" class="form-control form-control-sm" value="{{ $line->proc_end->format('Y-m') }}">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted mb-1">Source of Fund (Column 9)</label>
                        <select name="fund_source_id" class="form-select form-select-sm">
                            @foreach ($fundSources as $fund)
                                <option value="{{ $fund->id }}" @selected($line->fund_source_id === $fund->id)>{{ $fund->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted mb-1">Estimated Budget (Col. 10)</label>
                        <input type="text" class="form-control form-control-sm text-end bg-light" value="{{ number_format((float) $line->estimated_budget, 2) }}" readonly>
                        <div class="form-text">Sum of its PPMP projects.</div>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted mb-1">Procurement Strategy or Tools (Col. 11)</label>
                        <input type="text" name="procurement_strategy" class="form-control form-control-sm" value="{{ $line->procurement_strategy }}">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold text-muted mb-1">Remarks (Column 12)</label>
                        <input type="text" name="remarks" class="form-control form-control-sm" value="{{ $line->remarks }}">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="border rounded p-2 bg-light">
                    <div class="fw-semibold text-muted mb-1">PPMP projects in this line</div>
                    @foreach ($line->ppmpItems as $ppmpItem)
                        <div>• {{ $ppmpItem->ppmp->office->name }} — {{ $ppmpItem->description }} <span class="float-end">₱ {{ number_format((float) $ppmpItem->estimated_budget, 2) }}</span></div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fas fa-save me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>
