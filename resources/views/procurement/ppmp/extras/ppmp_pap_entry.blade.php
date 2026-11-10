{{-- Add PAP: pick one of the office's PAPs from its department's Budget Proposal --}}
<div class="modal fade" id="{{ $modalName }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-folder-plus text-muted me-2"></i>Add PAP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form_ppmp_pap" autocomplete="off" novalidate>
                @csrf
                <div class="modal-body p-4 small">
                    <div id="pap_error_summary" class="alert alert-danger d-none py-2 px-3 mb-3"><i class="fas fa-exclamation-triangle me-1"></i> <span>Please choose a PAP.</span></div>
                    @if ($available->isNotEmpty())
                        <label class="form-label fw-semibold text-muted mb-1">PAP from the FY {{ $ppmp->fiscal_year }} Budget Proposal</label>
                        <select name="budget_pap_id" class="form-select form-select-sm" required>
                            @foreach ($available as $pap)
                                <option value="{{ $pap->id }}">{{ $pap->label() }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                        <div class="form-text">Your office's PAPs not yet in this PPMP. The code shows once the evaluator gives it.</div>
                    @else
                        <div class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            @if ($proposal)
                                Every PAP of {{ $ppmp->office->shortName() }} in the FY {{ $ppmp->fiscal_year }} Budget Proposal is already in this PPMP.
                            @else
                                {{ $ppmp->office->department?->shortName() ?? $ppmp->office->shortName() }} has no FY {{ $ppmp->fiscal_year }} Budget Proposal yet.
                            @endif
                            Write the PAP title on the Budget Proposal first, then pick it here.
                        </div>
                    @endif
                    @if ($proposal)
                        <a href="{{ route('procurement.budget-proposals.show', $proposal) }}" target="_blank" class="d-inline-block mt-3"><i class="fas fa-external-link-alt me-1"></i> Open the Budget Proposal</a>
                    @else
                        <a href="{{ route('procurement.budget-proposals.index', ['fy' => $ppmp->fiscal_year]) }}" target="_blank" class="d-inline-block mt-3"><i class="fas fa-external-link-alt me-1"></i> Go to Budget Proposals</a>
                    @endif
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    @if ($available->isNotEmpty())
                        <button type="submit" id="btn_save_pap" class="btn btn-sm btn-primary px-3"><i class="fas fa-plus me-1"></i> Add</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
