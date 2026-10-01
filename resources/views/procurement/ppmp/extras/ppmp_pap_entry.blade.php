<div class="modal fade" id="{{ $modalName }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom border-light">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                    <i class="fas fa-folder-plus text-primary me-2"></i> {{ $pap ? 'Edit PAP' : 'Add PAP' }}
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="form_ppmp_pap" autocomplete="off" novalidate>
                @csrf
                @if ($pap)
                    <input type="hidden" name="id" value="{{ $pap->id }}">
                @endif

                <div class="modal-body p-4">
                    <div id="pap_error_summary" class="alert alert-danger d-none py-2 px-3 small rounded mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i> <span>Please correct the highlighted errors below.</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">PAP Code</label>
                        <input type="text" name="code" class="form-control form-control-sm" value="{{ $suggestedCode }}" placeholder="e.g. 26-05012-01" required>
                        <div class="invalid-feedback"></div>
                        <div class="form-text">Suggested: year - office no. - next number. You can change it (e.g. a PAP carried over from last year).</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold text-muted mb-1">PAP Title</label>
                        <input type="text" name="title" class="form-control form-control-sm" value="{{ $pap->title ?? '' }}" placeholder="e.g. ICT Infrastructure Management" required>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top border-light py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn_save_pap" class="btn btn-sm btn-primary px-3"><i class="fas fa-save me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
