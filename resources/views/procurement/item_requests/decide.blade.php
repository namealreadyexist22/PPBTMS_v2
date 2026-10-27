{{-- The procuring office decides a request: how many, and into which of its PPMP projects. Expects $ir, $projects (ItemRequestService::projectsFor). --}}
@php $qty = fn ($q) => $q === null ? '—' : rtrim(rtrim(number_format((float) $q, 2), '0'), '.'); @endphp
<div class="modal fade" id="ITEM_DECIDE_MODAL" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <form class="modal-content border-0 shadow js-ir-form" data-action="{{ route('procurement.item-requests.decide', $ir) }}" autocomplete="off">
            <div class="modal-header bg-light py-3">
                <div>
                    <h5 class="modal-title fw-bold"><i class="fas fa-reply me-2"></i>Decide {{ $ir->request_no }}</h5>
                    <div class="small text-muted">{{ $ir->requestingOffice->shortName() }} requests <b>{{ $qty($ir->quantity) }} {{ $ir->item->unit?->name }}</b> of {{ $ir->item->code }} — {{ $ir->item->name }} for FY {{ $ir->fiscal_year }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 small">
                @if ($ir->purpose)
                    <div class="mb-3"><span class="fw-semibold text-muted">Purpose:</span> {{ $ir->purpose }}</div>
                @endif
                <div class="row g-3">
                    <div class="col-sm-4">
                        <label class="form-label fw-semibold text-muted mb-1" for="ir_approved">Approve</label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="approved_quantity" id="ir_approved" class="form-control" min="0" max="{{ (float) $ir->quantity }}" step="1" value="{{ (float) $ir->quantity }}" required>
                            <span class="input-group-text">of {{ $qty($ir->quantity) }}</span>
                        </div>
                        <div class="form-text">To approve none, use <b>Decline</b>.</div>
                    </div>
                    <div class="col-sm-8">
                        <label class="form-label fw-semibold text-muted mb-1" for="ir_project">Into the distribution of</label>
                        <select name="ppmp_item_id" id="ir_project" class="form-select form-select-sm">
                            <option value="">Choose your PPMP project…</option>
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}" @selected($projects->count() === 1) data-room="{{ $p->room }}">
                                    {{ $p->description }} — PPMP {{ $p->ppmp->ppmp_no }} ({{ $p->ppmp->status->label() }}) · qty {{ $qty($p->quantity) }}, {{ $qty($p->distributed) }} distributed{{ $p->room !== null ? ', room for ' . $qty($p->room) : '' }}
                                </option>
                            @endforeach
                        </select>
                        @if ($projects->isEmpty())
                            <div class="form-text text-danger">Your FY {{ $ir->fiscal_year }} PPMP has no project for {{ $ir->item->code }} yet. Add one (sized with the requests' totals), then approve; or decline with 0.</div>
                        @else
                            <div class="form-text">{{ $ir->requestingOffice->shortName() }}'s share in its distribution goes up by what you approve.</div>
                        @endif
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-muted mb-1" for="ir_remarks">Remarks to {{ $ir->requestingOffice->shortName() }}</label>
                        <textarea name="decision_remarks" id="ir_remarks" class="form-control form-control-sm" rows="2" maxlength="1000" placeholder="e.g. 1 approved; 2 units issued last year are still serviceable"></textarea>
                        <div class="form-text">Required when declining.</div>
                    </div>
                    @if ($ir->item->specifications)
                        <div class="col-12">
                            <div class="fw-semibold text-muted mb-1">Specifications (TWG)</div>
                            @include('procurement.partials.specs', ['text' => $ir->item->specifications])
                        </div>
                    @endif
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-outline-danger me-auto js-decline"><i class="fas fa-times me-1"></i> Decline</button>
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fas fa-check me-1"></i> Approve</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    // Decline: none approved, with the reason (asked for here when the remarks are blank)
    $('#ITEM_DECIDE_MODAL .js-decline').on('click', function () {
        const form = $(this).closest('form');
        const remarks = form.find('[name=decision_remarks]');
        Swal.fire({
            target: document.getElementById('ITEM_DECIDE_MODAL'),
            title: 'Decline {{ $ir->request_no }}?',
            html: 'None of the {{ $qty($ir->quantity) }} requested is approved. {{ $ir->requestingOffice->shortName() }} is told why.',
            input: 'textarea', inputValue: remarks.val(), inputPlaceholder: 'Reason, e.g. The units issued in 2025 are still serviceable',
            inputAttributes: { maxlength: 1000 }, inputValidator: (v) => !v.trim() && 'State why the request is declined.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Decline', reverseButtons: true,
        }).then((r) => {
            if (!r.isConfirmed) return;
            remarks.val(r.value.trim());
            form.find('[name=approved_quantity]').val(0);
            form.find('[name=ppmp_item_id]').val('');
            form.trigger('submit');
        });
    });
})();
</script>
