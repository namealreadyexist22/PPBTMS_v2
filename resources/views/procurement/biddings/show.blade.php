@extends('BackEnd.layouts.master')

@section('content')
@php
    use App\Enums\BiddingStatus;
    use App\Enums\BiddingStep;
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $requests = $bidding->requests();
    $editLots = $bidding->lotsEditable();
    $cancelled = $bidding->status === BiddingStatus::Cancelled;
    $dt = fn ($d) => $d?->format('M d, Y g:i A');
    // The bidding's dates (its steps show on the PRs' status trail; the full record is in the History)
    $dates = [
        'Created'            => $bidding->created_at?->format('M d, Y'),
        'Pre-proc conference' => $bidding->preproc_held_at ? 'Held ' . $bidding->preproc_held_at->format('M d, Y') : $dt($bidding->preproc_at),
        'Advertised'         => $bidding->advertised_at?->format('M d, Y'),
        'Pre-bid conference' => $bidding->prebid_held_at ? 'Held ' . $bidding->prebid_held_at->format('M d, Y') : $dt($bidding->prebid_at),
    ];
@endphp

<style>
    .pr-card { border-radius: 12px; }
    .pr-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; color: var(--bs-secondary-color); }
    .pr-stat { border: var(--bs-border-width) solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg); padding: .5rem 1rem; height: 100%; }
    .pr-empty-ico { display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 50%; font-size: 1.4rem; background-color: var(--bs-primary-bg-subtle); color: var(--bs-primary); }
    #bid_lots .table > thead th { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; color: var(--bs-secondary-color); }
    /* Picker modal: the form joins the modal's flex column so the body scrolls and the footer stays */
    #form_bid_picker { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    #form_bid_picker .modal-body { overflow-y: auto; }
</style>

<div class="mb-3">
    <a href="{{ route('procurement.biddings.index') }}" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i> Back to Competitive Bidding</a>
</div>

{{-- Header (redrawn in place) --}}
<div id="bid_header">
<div class="card border-0 shadow-sm pr-card overflow-hidden mb-3">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="pr-label">Competitive Bidding · {{ $bidding->fundSource?->name ?? 'fund set by the first item' }} · {{ $bidding->region->label() }}</div>
                <h4 class="fw-bold mb-1">
                    {{ $bidding->title() }}
                    <span class="badge rounded-pill bg-{{ $bidding->status->color() }}-subtle text-{{ $bidding->status->color() }}-emphasis border border-{{ $bidding->status->color() }}-subtle align-middle" style="font-size: .7rem;">{{ $bidding->status->label() }}</span>
                </h4>
                <div class="text-muted">{{ $bidding->label() }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#BID_HISTORY_MODAL">
                    <i class="fas fa-history me-1"></i> History <span class="badge bg-secondary ms-1">{{ $bidding->events->count() }}</span>
                </button>
                @if ($bidding->status === BiddingStatus::Ongoing)
                    <button class="btn btn-sm btn-outline-danger" id="btn_cancel"><i class="fas fa-ban me-1"></i> Cancel Bidding</button>
                @endif
            </div>
        </div>

        {{-- What can be recorded next (optional steps outlined); each one also goes on the PRs' status trail --}}
        @if ($next)
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <span class="small text-muted me-1">Record:</span>
                @foreach ($next as $step)
                    <button class="btn btn-sm {{ $step->isOptional() ? 'btn-outline-primary' : 'btn-primary' }} js-step" data-url="{{ route('procurement.biddings.steps.entry', [$bidding, $step->value]) }}">
                        <i class="{{ $step->icon() }} me-1"></i>{{ $step !== $bidding->stage ? $step->label() : ($step === BiddingStep::Supplemental ? 'Another supplemental bid bulletin' : 'Re-set the pre-procurement conference') }}
                    </button>
                @endforeach
            </div>
        @elseif ($bidding->stage === BiddingStep::Bidding)
            <div class="small text-muted mt-3"><i class="fas fa-info-circle me-1"></i>Public bidding held. The bids are evaluated next, lot by lot.</div>
        @endif

        <div class="row g-2 mt-2">
            <div class="col-12 col-lg-4">
                <div class="pr-stat">
                    <div class="pr-label">PR / JR</div>
                    @forelse ($requests as $pr)
                        <span class="badge rounded-pill bg-light text-dark border me-1 mb-1" title="{{ $pr->office->shortName() }}">{{ $pr->title() }}</span>
                    @empty
                        <div class="text-muted small">None yet</div>
                    @endforelse
                </div>
            </div>
            @foreach ($dates as $label => $value)
                <div class="col-6 col-md-3 col-lg"><div class="pr-stat"><div class="pr-label">{{ $label }}</div><div class="fw-semibold small">{{ $value ?? '—' }}</div></div></div>
            @endforeach
            <div class="col-6 col-md-3 col-lg">
                <div class="pr-stat">
                    <div class="pr-label">Public bidding</div>
                    <div class="fw-semibold small">
                        {{ $bidding->bidding_held_at ? 'Held ' . $bidding->bidding_held_at->format('M d, Y') : ($dt($bidding->bidding_at) ?? '—') }}
                        @if ($bidding->bidding_resets)<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" title="Re-set {{ $bidding->bidding_resets }} time(s); see the History">re-set {{ $bidding->bidding_resets }}×</span>@endif
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg"><div class="pr-stat"><div class="pr-label">ABC</div><div class="fw-bold text-nowrap">₱{{ number_format((float) $bidding->total_abc, 2) }}</div></div></div>
        </div>

        @if ($bidding->philgeps_link || $bidding->website_link)
            <div class="small mt-2">
                @if ($bidding->philgeps_link)<a href="{{ $bidding->philgeps_link }}" target="_blank" rel="noopener" class="me-3"><i class="fas fa-external-link-alt me-1"></i>PhilGEPS posting</a>@endif
                @if ($bidding->website_link)<a href="{{ $bidding->website_link }}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt me-1"></i>Agency website</a>@endif
            </div>
        @endif

        @unless ($cancelled)
            <form id="form_title" class="d-flex flex-wrap align-items-end gap-2 mt-3" autocomplete="off">
                <div class="flex-grow-1" style="min-width: 240px;">
                    <label class="form-label small fw-semibold text-muted mb-1">Title <span class="fw-normal">(optional)</span></label>
                    <input type="text" name="title" class="form-control form-control-sm" value="{{ $bidding->title }}" maxlength="500" placeholder="Blank: named after its items">
                </div>
                <button type="submit" class="btn btn-sm btn-outline-success"><i class="fas fa-save me-1"></i> Save</button>
            </form>
        @endunless

        @if ($cancelled)
            <div class="alert alert-danger d-flex gap-2 align-items-start border-0 small mt-3 mb-0"><i class="fas fa-ban mt-1"></i><div>Cancelled {{ $bidding->cancelled_at?->format('M d, Y') }}: {{ $bidding->cancel_reason }}</div></div>
        @endif
    </div>
</div>
</div>

        {{-- Lots (redrawn in place) --}}
        <div id="bid_lots">
        <div class="card border-0 shadow-sm pr-card overflow-hidden mb-3">
            <div class="card-header bg-white py-3 px-4 d-flex flex-wrap align-items-center gap-2" style="border-bottom: 1px solid #f1f5f9;">
                <h6 class="m-0 fw-bold"><i class="fas fa-layer-group text-muted me-2"></i>Lots</h6>
                <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">{{ $bidding->lots->count() }}</span>
                @if ($editLots)
                    <div class="ms-auto d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" id="btn_new_lot"><i class="fas fa-plus me-1"></i> New Lot</button>
                        <button class="btn btn-sm btn-primary js-add-lines" data-lot=""><i class="fas fa-plus me-1"></i> Add Items</button>
                    </div>
                @elseif (! $cancelled)
                    <span class="ms-auto small text-muted"><i class="fas fa-lock me-1"></i>Fixed since the advertisement</span>
                @endif
            </div>
            @if ($bidding->lots->isEmpty())
                <div class="py-5 px-4 text-center">
                    <span class="pr-empty-ico mb-3"><i class="fas fa-box-open"></i></span>
                    <div class="fw-semibold">No items yet.</div>
                    <div class="text-muted small">Add the Competitive Bidding items of submitted PRs / JRs of {{ $bidding->region->label() }}, of one fund source. Each lot is awarded on its own.</div>
                    @if ($editLots)<button class="btn btn-sm btn-outline-primary js-add-lines mt-3" data-lot=""><i class="fas fa-plus me-1"></i> Add items</button>@endif
                </div>
            @else
                @foreach ($bidding->lots as $lot)
                    <div class="{{ $loop->first ? '' : 'border-top' }}">
                        <div class="d-flex flex-wrap align-items-center gap-2 px-4 py-2 bg-body-tertiary">
                            <span class="fw-bold">{{ $lot->name() }}</span>
                            <span class="small text-muted">{{ $lot->items->count() }} {{ \Illuminate\Support\Str::plural('item', $lot->items->count()) }} · ABC <span class="fw-semibold text-body">₱{{ number_format((float) $lot->abc, 2) }}</span></span>
                            @if ($editLots)
                                <div class="ms-auto d-flex gap-1">
                                    <button class="btn btn-sm btn-link py-0 js-add-lines" data-lot="{{ $lot->id }}" title="Add items to {{ $lot->name() }}"><i class="fas fa-plus"></i></button>
                                    <button class="btn btn-sm btn-link py-0 js-rename-lot" data-id="{{ $lot->id }}" data-title="{{ $lot->title }}" data-name="Lot {{ $lot->lot_no }}" title="Name {{ $lot->name() }}"><i class="fas fa-pen"></i></button>
                                    @if ($lot->items->isEmpty())
                                        <button class="btn btn-sm btn-link text-danger py-0 js-delete-lot" data-id="{{ $lot->id }}" title="Remove {{ $lot->name() }}"><i class="fas fa-trash-alt"></i></button>
                                    @endif
                                </div>
                            @endif
                        </div>
                        @if ($lot->items->isEmpty())
                            <div class="px-4 py-3 small text-muted">No items in this lot yet.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle small mb-0">
                                    <thead class="table-light">
                                        <tr class="text-nowrap"><th class="ps-4">Item &amp; Description</th><th class="text-center">Qty</th><th class="text-end">ABC / Unit</th><th class="text-end">ABC Total</th><th>PR / JR</th>@if ($editLots)<th class="pe-4"></th>@endif</tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($lot->items as $item)
                                            @php $line = $item->line; @endphp
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="fw-semibold">{{ $line->description }}</div>
                                                    @if ($line->specifications)<div class="text-muted fst-italic" style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($line->specifications, 200) }}</div>@endif
                                                </td>
                                                <td class="text-center text-nowrap">{{ $qty($line->quantity) }} {{ $line->unit }}</td>
                                                <td class="text-end text-nowrap">{{ number_format((float) $line->unit_cost, 2) }}</td>
                                                <td class="text-end text-nowrap fw-semibold">{{ number_format((float) $line->total_cost, 2) }}</td>
                                                <td class="text-nowrap">{{ $line->request->title() }}<div class="text-muted">{{ $line->request->office->shortName() }}</div></td>
                                                @if ($editLots)
                                                    <td class="pe-4 text-end text-nowrap">
                                                        @if ($bidding->lots->count() > 1)
                                                            <select class="form-select form-select-sm d-inline-block w-auto js-move-line" data-id="{{ $item->id }}" aria-label="Move to another lot">
                                                                @foreach ($bidding->lots as $other)<option value="{{ $other->id }}" @selected($other->id === $lot->id)>Lot {{ $other->lot_no }}</option>@endforeach
                                                            </select>
                                                        @endif
                                                        <button class="btn btn-sm btn-light border text-danger js-remove-line" data-id="{{ $item->id }}" title="Take off the bidding" aria-label="Take {{ $line->description }} off the bidding"><i class="fas fa-times"></i></button>
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
        </div>

{{-- History, opened from the History button (redrawn in place): every step, including earlier bidding dates --}}
<div id="bid_history">
<div class="modal fade" id="BID_HISTORY_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-history text-muted me-2"></i>History — {{ $bidding->title() }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <ul class="list-group list-group-flush small">
                @forelse ($bidding->events->reverse() as $event)
                    <li class="list-group-item px-4">
                        <div class="fw-semibold"><i class="{{ $event->step->icon() }} text-muted me-1"></i>{{ $event->step->label() }}
                            <span class="fw-normal text-muted">· {{ $event->step === BiddingStep::PreprocSet ? $dt($event->happened_at) : $event->happened_at?->format('M d, Y') }}</span></div>
                        @php $d = $event->details ?? []; @endphp
                        @if (! empty($d['bidding_at']) && $event->step !== BiddingStep::Advertised)
                            <div>Public bidding re-set to <span class="fw-semibold">{{ \Illuminate\Support\Carbon::parse($d['bidding_at'])->format('M d, Y g:i A') }}</span>@if (! empty($d['previous'])) <span class="text-muted">(was {{ \Illuminate\Support\Carbon::parse($d['previous'])->format('M d, Y g:i A') }})</span>@endif</div>
                        @elseif ($event->step === BiddingStep::PreprocSet && ! empty($d['previous']))
                            <div class="text-muted">Was {{ \Illuminate\Support\Carbon::parse($d['previous'])->format('M d, Y g:i A') }}</div>
                        @elseif ($event->step === BiddingStep::Advertised)
                            <div>Public bidding {{ \Illuminate\Support\Carbon::parse($d['bidding_at'])->format('M d, Y g:i A') }}@if (! empty($d['prebid_at'])); pre-bid {{ \Illuminate\Support\Carbon::parse($d['prebid_at'])->format('M d, Y g:i A') }}@endif</div>
                        @endif
                        @if ($event->remarks)<div class="fst-italic text-body-secondary">"{{ $event->remarks }}"</div>@endif
                        <div class="text-muted">Recorded {{ $event->created_at?->format('M d, Y g:i A') }}@if ($event->user) · {{ $event->user->fullname }}@endif</div>
                    </li>
                @empty
                    <li class="list-group-item px-4 text-muted">Nothing recorded yet. Put the items into lots, then record the pre-procurement conference or the advertisement.</li>
                @endforelse
                </ul>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
</div>

<div id="modal-body"></div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';
    let isModalOpen = false;

    function errorText(xhr) {
        const res = xhr.responseJSON || {};
        return res.errors ? Object.values(res.errors)[0][0]
            : (res.message && res.message !== 'Server Error' ? res.message : 'Something went wrong (error ' + xhr.status + '). Please tell the system administrator.');
    }

    // Redraw the header, lots and history, keeping the scroll position
    function refreshView() {
        const y = window.scrollY;
        return $.get(window.location.href).done(function (html) {
            const page = $('<div>').append($.parseHTML(html));
            if (!page.find('#bid_lots').length) { window.location.reload(); return; }
            ['#bid_header', '#bid_lots', '#bid_history'].forEach((id) => $(id).replaceWith(page.find(id)));
            window.scrollTo(0, y);
        }).fail(() => window.location.reload());
    }

    function post(url, data, method = 'POST') {
        return $.ajax({ url: url, type: 'POST', data: Object.assign({ _token: csrf, _method: method }, data || {}), headers: { Accept: 'application/json' } });
    }
    const done = (res) => { toastr.success(res.message, 'Success'); refreshView(); };
    const failed = (xhr) => toastr.error(errorText(xhr), 'Error');

    function openModal(url, id, focus) {
        if (isModalOpen) return;
        isModalOpen = true;
        $.get(url).done(function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById(id);
            new bootstrap.Modal(el).show();
            if (focus) el.addEventListener('shown.bs.modal', () => setTimeout(() => $(el).find(focus).first().trigger('focus')));
            el.addEventListener('hidden.bs.modal', function () { isModalOpen = false; $('#modal-body').html(''); });
        }).fail(function (xhr) { isModalOpen = false; failed(xhr); });
    }

    $(document).on('submit', '#form_title', function (e) {
        e.preventDefault();
        post('{{ route("procurement.biddings.title", $bidding) }}', { title: $(this).find('[name=title]').val() }).done(done).fail(failed);
    });

    // ---------- Steps ----------
    $(document).on('click', '.js-step', function () { openModal($(this).data('url'), 'BID_STEP_MODAL', 'input:not([type=hidden])'); });

    $(document).off('submit', '#form_bid_step').on('submit', '#form_bid_step', function (e) {
        e.preventDefault();
        const form = $(this);
        const data = {};
        form.serializeArray().forEach((f) => { data[f.name] = f.value; });
        form.find('[type=submit]').prop('disabled', true);
        post(form.data('url'), data)
            .done(function (res) {
                bootstrap.Modal.getInstance(document.getElementById('BID_STEP_MODAL')).hide();
                done(res);
            })
            .fail(function (xhr) { form.find('[type=submit]').prop('disabled', false); failed(xhr); });
    });

    // ---------- Lots and items ----------
    $(document).on('click', '.js-add-lines', function () {
        const lot = $(this).data('lot');
        openModal('{{ route("procurement.biddings.picker", $bidding) }}' + (lot ? '?lot=' + lot : ''), 'BID_PICKER_MODAL', '#bid_pick_search');
    });

    $(document).off('submit', '#form_bid_picker').on('submit', '#form_bid_picker', function (e) {
        e.preventDefault();
        const lines = $(this).find('.js-line:checked').map((_, el) => el.value).get();
        if (!lines.length) { toastr.warning('Choose at least one item.'); return; }
        $('#btn_bid_add_selected').prop('disabled', true);
        post('{{ route("procurement.biddings.lines.store", $bidding) }}', { lines: lines, lot_id: $('#bid_pick_lot').val() })
            .done(function (res) {
                bootstrap.Modal.getInstance(document.getElementById('BID_PICKER_MODAL')).hide();
                done(res);
            })
            .fail(function (xhr) { $('#btn_bid_add_selected').prop('disabled', false); failed(xhr); });
    });

    $(document).on('click', '.js-remove-line', function () {
        post('{{ route("procurement.biddings.lines.destroy", $bidding) }}', { id: $(this).data('id') }, 'DELETE').done(done).fail(failed);
    });
    $(document).on('change', '.js-move-line', function () {
        post('{{ route("procurement.biddings.lines.move", $bidding) }}', { id: $(this).data('id'), lot_id: this.value }).done(done).fail(failed);
    });

    function lotTitle(title, name, value) {
        return Swal.fire({
            title: title, input: 'text', inputValue: value || '', inputPlaceholder: 'e.g. Laptops (optional)',
            inputAttributes: { maxlength: 500 }, text: name + ' can also go without a name.',
            showCancelButton: true, confirmButtonText: 'Save', reverseButtons: true,
        });
    }
    $(document).on('click', '#btn_new_lot', function () {
        lotTitle('New lot', 'The lot', '').then((r) => {
            if (r.isConfirmed) post('{{ route("procurement.biddings.lots.store", $bidding) }}', { title: r.value }).done(done).fail(failed);
        });
    });
    $(document).on('click', '.js-rename-lot', function () {
        const id = $(this).data('id');
        lotTitle('Name ' + $(this).data('name'), $(this).data('name'), $(this).data('title')).then((r) => {
            if (r.isConfirmed) post('{{ route("procurement.biddings.lots.store", $bidding) }}', { id: id, title: r.value }).done(done).fail(failed);
        });
    });
    $(document).on('click', '.js-delete-lot', function () {
        post('{{ route("procurement.biddings.lots.destroy", $bidding) }}', { id: $(this).data('id') }, 'DELETE').done(done).fail(failed);
    });

    // ---------- Cancel (a bidding is not deleted: its number is not used again) ----------
    $(document).on('click', '#btn_cancel', function () {
        Swal.fire({
            title: 'Cancel {{ $bidding->title() }}?', input: 'textarea', inputPlaceholder: 'Reason (required)',
            inputValidator: (v) => !v ? 'Please state the reason.' : undefined,
            text: 'Its items can then go to another bidding. The number is not used again.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Cancel Bidding', cancelButtonText: 'Keep', reverseButtons: true
        }).then((r) => {
            if (!r.isConfirmed) return;
            post('{{ route("procurement.biddings.cancel", $bidding) }}', { reason: r.value }).done(done).fail(failed);
        });
    });
});
</script>
@endpush
