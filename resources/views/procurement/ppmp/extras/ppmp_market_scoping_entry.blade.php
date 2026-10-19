{{-- GPPB Market Scoping Checklist of one project (RA 12009 Sec. 10), opened from the project's menu. --}}
@php
    $ms = $item->market_scoping ?? [];
    $msDone = $item->marketScopingComplete();
@endphp
<div class="modal fade" id="MARKET_SCOPING_MODAL" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <form class="modal-content border-0 shadow" id="form_market_scoping" autocomplete="off" novalidate>
            <div class="modal-header bg-light py-3">
                <div>
                    <h5 class="modal-title fw-bold"><i class="fas fa-clipboard-check text-muted me-2"></i>Market Scoping Checklist
                        <span class="badge {{ $msDone ? 'bg-success' : 'bg-light text-dark border' }} align-middle ms-1" style="font-size: .7rem;">{{ $msDone ? 'Complete' : 'Not complete' }}</span>
                    </h5>
                    <div class="small text-muted">{{ $item->description }} · GPPB, RA 12009 Sec. 10</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 small">
                <div id="ms_error_summary" class="alert alert-danger d-none py-2 px-3 mb-3"><i class="fas fa-exclamation-triangle me-1"></i> <span></span></div>
                <fieldset class="border-0 p-0 m-0" @disabled(! $canEdit)>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted mb-1">Period of market scoping — from</label>
                            <input type="month" name="market_scoping[period_from]" class="form-control form-control-sm" value="{{ $ms['period_from'] ?? '' }}">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted mb-1">to</label>
                            <input type="month" name="market_scoping[period_to]" class="form-control form-control-sm" value="{{ $ms['period_to'] ?? '' }}">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted mb-1">Date signed <span class="fw-normal">(printed under both names)</span></label>
                            <input type="date" name="market_scoping[signed_on]" class="form-control form-control-sm" value="{{ $ms['signed_on'] ?? '' }}">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12 text-muted mt-1">Agency, end-user, representative, project name, budget and delivery are taken from the PPMP and the project.</div>
                    </div>

                    <div class="fw-semibold text-muted mb-1">Market scoping activity/ies conducted <span class="fw-normal">(check all that apply)</span></div>
                    {{-- Each checked activity: what was gathered (printed in the Documentation column; unchecked ones print N/A) --}}
                    @foreach (config('market_scoping.activities') as $key => [$label, $docs])
                        @php $on = in_array($key, $ms['activities'] ?? []); @endphp
                        <div class="row g-2 align-items-start mb-2 ms-activity">
                            <div class="col-md-7">
                                <div class="form-check mb-0">
                                    <input class="form-check-input js-ms-act" type="checkbox" name="market_scoping[activities][]" value="{{ $key }}" id="ms_act_{{ $key }}" @checked($on)>
                                    <label class="form-check-label" for="ms_act_{{ $key }}">{{ $label }}</label>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <input type="text" name="market_scoping[documentation][{{ $key }}]" class="form-control form-control-sm js-ms-doc" value="{{ $ms['documentation'][$key] ?? '' }}" maxlength="500"
                                       placeholder="Documentation, e.g. Price quotations, Screen Shot" title="May be: {{ $docs }}" @disabled(! $on)>
                            </div>
                        </div>
                    @endforeach
                    <div class="row g-2 align-items-start mb-3">
                        <div class="col-md-7">
                            <input type="text" name="market_scoping[activity_other]" class="form-control form-control-sm js-ms-other" value="{{ $ms['activity_other'] ?? '' }}" maxlength="500" placeholder="Other analogous market scoping activity/ies undertaken (specify)">
                        </div>
                        <div class="col-md-5">
                            <input type="text" name="market_scoping[documentation][other]" class="form-control form-control-sm js-ms-other-doc" value="{{ $ms['documentation']['other'] ?? '' }}" maxlength="500" placeholder="Documentation" @disabled(empty($ms['activity_other']))>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Parameter</th><th style="width: 140px;">Considered?</th><th>Recommendations based on the market scoping</th></tr></thead>
                            <tbody>
                                @foreach (config('market_scoping.parameters') as $key => [$label, $question])
                                    <tr>
                                        <td><span class="fw-semibold">{{ $label }}</span><span class="d-block text-muted" style="font-size: .72rem;">{{ $question }}</span></td>
                                        <td>
                                            <select name="market_scoping[parameters][{{ $key }}][answer]" class="form-select form-select-sm">
                                                <option value="">—</option>
                                                @foreach (config('market_scoping.answers') as $value => $answer)
                                                    <option value="{{ $value }}" @selected(($ms['parameters'][$key]['answer'] ?? null) === $value)>{{ $answer }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        {{-- Several lines allowed (e.g. one requirement per line); printed as typed --}}
                                        <td><textarea name="market_scoping[parameters][{{ $key }}][recommendation]" class="form-control form-control-sm" rows="2" maxlength="3000">{{ $ms['parameters'][$key]['recommendation'] ?? '' }}</textarea></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="text-muted mt-2"><i class="fas fa-paperclip me-1"></i>Attach the market survey, quotations or canvass sheets to the project (Edit project → Attached Supporting Documents).</div>
                </fieldset>
            </div>
            <script>
                // Documentation is filled in for the activities checked
                $('#form_market_scoping').on('change', '.js-ms-act', function () {
                    $(this).closest('.ms-activity').find('.js-ms-doc').prop('disabled', !this.checked).trigger(this.checked ? 'focus' : 'blur');
                }).on('input', '.js-ms-other', function () {
                    $('#form_market_scoping .js-ms-other-doc').prop('disabled', !this.value.trim());
                });
            </script>
            <div class="modal-footer bg-light py-2">
                <a href="{{ route('procurement.ppmp.items.market-scoping', [$ppmp, $item]) }}" target="_blank" class="btn btn-sm btn-outline-secondary me-auto"><i class="fas fa-print me-1"></i> Print</a>
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">{{ $canEdit ? 'Cancel' : 'Close' }}</button>
                @if ($canEdit)
                    <button type="submit" class="btn btn-sm btn-primary px-3" id="btn_save_ms" data-url="{{ route('procurement.ppmp.items.market-scoping.store', [$ppmp, $item]) }}"><i class="fas fa-save me-1"></i> Save</button>
                @endif
            </div>
        </form>
    </div>
</div>
