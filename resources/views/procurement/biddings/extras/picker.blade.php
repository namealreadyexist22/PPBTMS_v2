{{-- Add items to a bidding: received PRs / JRs of the region with Competitive Bidding items of its fund in no other bidding, into a lot.
     Expects $bidding (with fundSource, lots), $requests (each with ->available lines), $lotId (the lot to add to; null: a new lot). --}}
@php $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.'); @endphp
<style>
    .rq-search { width: 300px; max-width: 100%; }
    .rq-group + .rq-group { border-top: var(--bs-border-width) solid var(--bs-border-color); }
    .rq-head { background: var(--bs-tertiary-bg); }
    .rq-line { cursor: pointer; }
    .rq-line.is-other-fund { opacity: .45; cursor: not-allowed; }
    @media (max-width: 767.98px) { .rq-search { width: 100%; } }
</style>
<div class="modal fade" id="BID_PICKER_MODAL" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0">Add Items</h5>
                    <div class="small text-muted">{{ $bidding->title() }} · {{ $bidding->region->label() }}{{ $bidding->fundSource ? ' · ' . $bidding->fundSource->code : '' }}</div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form_bid_picker" autocomplete="off">
                <div class="modal-body p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                        <div>
                            <div class="fw-semibold">Received PRs / JRs with Competitive Bidding items</div>
                            <div class="small text-muted">Only {{ $bidding->fundSource->code }} items are listed (this bidding's fund source). A PR / JR shows here once the BAC receives it (PR Receiving).</div>
                        </div>
                        <div class="input-group input-group-sm rq-search">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="search" id="bid_pick_search" class="form-control border-start-0" placeholder="Search PR / JR no., office, purpose or item…" aria-label="Search requests">
                        </div>
                    </div>

                    @if ($requests->isEmpty())
                        <div class="text-center text-muted py-5">
                            <div class="fw-semibold">Nothing to bid.</div>
                            <div class="small">Every {{ $bidding->fundSource->code }} Competitive Bidding item of the received PRs / JRs of {{ $bidding->region->label() }} is already in a bidding.</div>
                        </div>
                    @else
                        <div class="border rounded-3 overflow-hidden small" id="bid_rq_list">
                            @foreach ($requests as $pr)
                                <div class="rq-group" data-search="{{ \Illuminate\Support\Str::lower($pr->title() . ' ' . $pr->office->shortName() . ' ' . $pr->purpose . ' ' . $pr->available->pluck('description')->implode(' ')) }}">
                                    <label class="rq-head d-flex align-items-start gap-2 px-3 py-2 mb-0">
                                        <input type="checkbox" class="form-check-input mt-1 js-request" aria-label="All items of {{ $pr->title() }}">
                                        <span class="flex-grow-1">
                                            <span class="fw-semibold">{{ $pr->title() }}</span>
                                            <span class="text-muted"> · {{ $pr->office->shortName() }} · charge to {{ $pr->pap?->code }}</span>
                                            @if ($pr->purpose)<span class="d-block text-muted">{{ \Illuminate\Support\Str::limit($pr->purpose, 160) }}</span>@endif
                                        </span>
                                        <span class="text-nowrap fw-semibold">₱{{ number_format($pr->available->sum(fn ($l) => (float) $l->total_cost), 2) }}</span>
                                    </label>
                                    @foreach ($pr->available as $line)
                                        <label class="rq-line d-flex align-items-start gap-2 px-3 py-2 mb-0 border-top" data-fund="{{ $line->ppmpItem?->fund_source_id }}">
                                            <input type="checkbox" class="form-check-input mt-1 ms-3 js-line" name="lines[]" value="{{ $line->id }}">
                                            <span class="flex-grow-1">
                                                <span class="fw-semibold">{{ $line->description }}</span>
                                                <span class="d-block text-muted">{{ $qty($line->quantity) }} {{ $line->unit }} × ₱{{ number_format((float) $line->unit_cost, 2) }} · {{ $line->ppmpItem?->fundSource?->code ?? 'No fund' }}</span>
                                            </span>
                                            <span class="text-nowrap js-amount">₱{{ number_format((float) $line->total_cost, 2) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                            <div id="bid_rq_empty" class="d-none text-center text-muted py-4">No PR / JR matches the search.</div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer bg-body-tertiary border-0 py-2">
                    <span class="me-auto small text-muted" id="bid_rq_summary">No items chosen</span>
                    <label class="small text-muted mb-0" for="bid_pick_lot">Add to</label>
                    <select id="bid_pick_lot" class="form-select form-select-sm w-auto">
                        <option value="">A new lot (Lot {{ $bidding->lots->count() + 1 }})</option>
                        @foreach ($bidding->lots as $lot)<option value="{{ $lot->id }}" @selected($lotId === $lot->id)>{{ $lot->name() }}</option>@endforeach
                    </select>
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn_bid_add_selected" class="btn btn-sm btn-primary px-3" disabled><i class="fas fa-plus me-1"></i> Add</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    const form = $('#form_bid_picker');
    const peso = (n) => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fixedFund = @json($bidding->fund_source_id ? (string) $bidding->fund_source_id : null);

    // One fund source per bidding: once an item is ticked, items of other funds are greyed out
    function sync() {
        const checked = form.find('.js-line:checked');
        const fund = fixedFund ?? (checked.length ? String(checked.first().closest('.rq-line').data('fund')) : null);
        form.find('.rq-line').each(function () {
            const other = fund !== null && String($(this).data('fund')) !== fund;
            $(this).toggleClass('is-other-fund', other).find('.js-line').prop('disabled', other).prop('checked', (i, c) => c && !other);
        });
        form.find('.rq-group').each(function () {
            const boxes = $(this).find('.js-line:not(:disabled)');
            const on = boxes.filter(':checked').length;
            $(this).find('.js-request').prop('checked', boxes.length > 0 && on === boxes.length).prop('indeterminate', on > 0 && on < boxes.length).prop('disabled', boxes.length === 0);
        });
        const picked = form.find('.js-line:checked');
        const total = picked.closest('.rq-line').find('.js-amount').get().reduce((sum, el) => sum + parseFloat(el.textContent.replace(/[^\d.]/g, '')), 0);
        $('#bid_rq_summary').text(picked.length ? picked.length + (picked.length === 1 ? ' item' : ' items') + ' · ₱' + peso(total) : 'No items chosen');
        $('#btn_bid_add_selected').prop('disabled', picked.length === 0);
    }

    form.on('change', '.js-line', sync);
    form.on('change', '.js-request', function () {
        const group = $(this).closest('.rq-group');
        const on = this.checked;   // sync() below redraws this box, so keep what was clicked
        if (on && !fixedFund && !form.find('.js-line:checked').length) {
            // Nothing ticked yet: the request's first item sets the fund, then the rest of the same fund follow
            group.find('.js-line').first().prop('checked', true);
            sync();
        }
        group.find('.js-line:not(:disabled)').prop('checked', on);
        sync();
    });

    $('#bid_pick_search').on('input', function () {
        const q = this.value.trim().toLowerCase();
        let shown = 0;
        form.find('.rq-group').each(function () {
            const hit = !q || this.dataset.search.includes(q);
            $(this).toggleClass('d-none', !hit);
            shown += hit ? 1 : 0;
        });
        $('#bid_rq_empty').toggleClass('d-none', shown > 0);
    }).on('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });

    sync();
})();
</script>
