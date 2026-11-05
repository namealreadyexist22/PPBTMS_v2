{{-- Record a step of the bidding: its date, and by step the conference / bidding dates, the links and remarks.
     Expects $bidding, $step (BiddingStep). --}}
@php
    use App\Enums\BiddingStep;
    $local = fn ($d) => $d?->format('Y-m-d\TH:i');
    $resets = in_array($step, [BiddingStep::PrebidHeld, BiddingStep::Supplemental], true);
    $dateLabel = match ($step) {
        BiddingStep::Advertised   => 'Date advertised',
        BiddingStep::Supplemental => 'Date of the bulletin',
        default                   => 'Date held',
    };
@endphp
<div class="modal fade" id="BID_STEP_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered {{ $step === BiddingStep::Advertised ? 'modal-lg' : '' }}">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0"><i class="{{ $step->icon() }} text-muted me-2"></i>{{ $step->label() }}</h5>
                    <div class="small text-muted">{{ $bidding->title() }} · {{ $bidding->label() }}</div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form_bid_step" data-url="{{ route('procurement.biddings.steps.store', [$bidding, $step->value]) }}" autocomplete="off">
                <div class="modal-body p-4 small">
                    @if ($step === BiddingStep::Advertised)
                        <div class="alert alert-info border-0 py-2 mb-3">Once advertised, the lots and their items can no longer change.</div>
                    @endif

                    <div class="row g-3">
                        @if ($step === BiddingStep::PreprocSet)
                            <div class="col-sm-8">
                                <label class="form-label fw-semibold text-muted mb-1">Date and time of the conference</label>
                                <input type="datetime-local" name="preproc_at" class="form-control form-control-sm" value="{{ $local($bidding->preproc_at) }}" required>
                                @if ($bidding->preproc_at)<div class="form-text">Set for {{ $bidding->preproc_at->format('M d, Y g:i A') }}; a new date re-sets it.</div>@endif
                            </div>
                        @else
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold text-muted mb-1">{{ $dateLabel }}</label>
                                <input type="date" name="date" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}" required>
                            </div>
                        @endif

                        @if ($step === BiddingStep::Advertised)
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold text-muted mb-1">Public bidding (opening of bids)</label>
                                <input type="datetime-local" name="bidding_at" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold text-muted mb-1">Pre-bid conference <span class="fw-normal">(if any)</span></label>
                                <input type="datetime-local" name="prebid_at" class="form-control form-control-sm">
                            </div>
                            <div class="col-sm-6"></div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold text-muted mb-1">PhilGEPS link</label>
                                <input type="url" name="philgeps_link" class="form-control form-control-sm" maxlength="1000" placeholder="https://notices.philgeps.gov.ph/…">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold text-muted mb-1">Agency website link</label>
                                <input type="url" name="website_link" class="form-control form-control-sm" maxlength="1000" placeholder="https://…">
                            </div>
                        @endif

                        @if ($resets)
                            <div class="col-12">
                                <label class="form-label fw-semibold text-muted mb-1">Public bidding {{ $step === BiddingStep::PrebidHeld ? '(re-set, if applicable)' : '(re-set by the bulletin, if applicable)' }}</label>
                                <input type="datetime-local" name="bidding_at" class="form-control form-control-sm" value="{{ $local($bidding->bidding_at) }}">
                                <div class="form-text">Now {{ $bidding->bidding_at?->format('M d, Y g:i A') ?? 'not set' }}. A new date keeps this one in the history; state why below.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold text-muted mb-1">Agency website link <span class="fw-normal">(if changed)</span></label>
                                <input type="url" name="website_link" class="form-control form-control-sm" maxlength="1000" placeholder="{{ $bidding->website_link ?: 'https://…' }}">
                            </div>
                        @endif

                        <div class="col-12">
                            <label class="form-label fw-semibold text-muted mb-1">{{ $resets ? 'Remarks / reason for a re-set' : 'Remarks' }} <span class="fw-normal">{{ $resets ? '(required when the public bidding is re-set)' : '(optional)' }}</span></label>
                            <textarea name="remarks" class="form-control form-control-sm" rows="2" maxlength="1000"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-body-tertiary border-0 py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fas fa-check me-1"></i> Record</button>
                </div>
            </form>
        </div>
    </div>
</div>
