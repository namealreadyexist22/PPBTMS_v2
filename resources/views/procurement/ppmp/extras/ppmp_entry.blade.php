<div class="modal fade" id="{{ $modalName }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-light py-3 border-bottom border-light">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                    <i class="fas fa-clipboard-list text-primary me-2"></i> Create New PPMP
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="form_ppmp_entry" autocomplete="off" novalidate>
                @csrf

                <div class="modal-body p-4">

                    <div id="modal_error_summary" class="alert alert-danger d-none py-2 px-3 small rounded mb-3 shadow-sm">
                        <i class="fas fa-exclamation-triangle me-1"></i> <span>Please correct the highlighted errors below.</span>
                    </div>

                    @if ($offices->isEmpty())
                        <div class="alert alert-warning small mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Your account has no office assigned yet. Ask the administrator to set your office.
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Office / Section</label>
                            <select name="office_id" class="form-select form-select-sm" required>
                                @foreach ($offices as $office)
                                    <option value="{{ $office->id }}">{{ $office->code }} — {{ $office->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted mb-1">Fiscal Year</label>
                                <select name="fiscal_year" class="form-select form-select-sm" required>
                                    @foreach ($years as $year)
                                        <option value="{{ $year }}" @selected($year === now()->year + 1)>{{ $year }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted mb-1">Type</label>
                                <select name="type" class="form-select form-select-sm" required>
                                    @foreach ($types as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small fw-semibold text-muted mb-1">Remarks (optional)</label>
                            <textarea name="remarks" class="form-control form-control-sm" rows="2"></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                    @endif
                </div>

                <div class="modal-footer bg-light border-top border-light py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    @if ($offices->isNotEmpty())
                        <button type="submit" id="btn_save_ppmp" class="btn btn-sm btn-primary px-3">
                            <i class="fas fa-save me-1"></i> Create
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>