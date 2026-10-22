@extends('BackEnd.layouts.master')

@section('content')
@php
    use App\Enums\RfqStatus;
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $requests = $rfq->requests();
    $issued = $rfq->status === RfqStatus::Issued;
    // Terms are fixed once issued; the signatory can still change (it is only printed)
    $canSign = $canEdit || $issued;
@endphp

<style>
    .pr-card { border-radius: 12px; }
    .pr-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; color: var(--bs-secondary-color); }
    .pr-stat { border: var(--bs-border-width) solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg); padding: .5rem 1rem; height: 100%; }
    .pr-ico, .pr-empty-ico { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; background-color: var(--bs-primary-bg-subtle); color: var(--bs-primary); }
    .pr-ico { width: 36px; height: 36px; border-radius: var(--bs-border-radius-lg); }
    .pr-empty-ico { width: 56px; height: 56px; border-radius: 50%; font-size: 1.4rem; }
    #rfq_items .table > thead th { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; color: var(--bs-secondary-color); }
    #rfq_items .card-header::after { content: none; }
    #form_rfq fieldset:disabled .form-control { background: transparent; border-color: transparent; box-shadow: none; padding-left: 0; resize: none; }
    /* Picker modal: the form joins the modal's flex column so the body scrolls and the footer stays */
    #form_rfq_picker { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    #form_rfq_picker .modal-body { overflow-y: auto; }
</style>

<div class="mb-3">
    <a href="{{ route('procurement.rfqs.index') }}" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i> Back to Requests for Quotation</a>
</div>

{{-- One card: header, items, then the terms and signatory. Header and items are redrawn in place. --}}
<div class="card border-0 shadow-sm pr-card overflow-hidden mb-3">
<div id="rfq_header">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="pr-label">Request for Quotation · {{ $rfq->procurementMode?->name ?? 'mode set by the first item' }} · {{ $rfq->region->label() }}</div>
                <h4 class="fw-bold mb-1">
                    {{ $rfq->title() }}
                    <span class="badge rounded-pill bg-{{ $rfq->status->color() }}-subtle text-{{ $rfq->status->color() }}-emphasis border border-{{ $rfq->status->color() }}-subtle align-middle" style="font-size: .7rem;">{{ $rfq->status->label() }}</span>
                </h4>
                <div class="text-muted">{{ $rfq->label() }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if ($rfq->items->isNotEmpty())
                    <button class="btn btn-sm btn-outline-secondary" id="btn_print"><i class="fas fa-print me-1"></i> Print</button>
                @endif
                @if ($canEdit)
                    <button class="btn btn-sm btn-outline-danger" id="btn_delete"><i class="fas fa-trash-alt me-1"></i> Delete Draft</button>
                    <button class="btn btn-sm btn-primary" id="btn_issue" @disabled($rfq->items->isEmpty())><i class="fas fa-paper-plane me-1"></i> Issue</button>
                @elseif ($issued)
                    @unless ($rfq->aoq)
                        <button class="btn btn-sm btn-outline-danger" id="btn_cancel"><i class="fas fa-ban me-1"></i> Cancel RFQ</button>
                    @endunless
                    @if (auth()->user()->canAccessPermission(\App\Models\Procurement\Aoq::MANAGE_PERMISSION))
                        @if ($rfq->aoq)
                            <a href="{{ route('procurement.aoqs.show', $rfq->aoq) }}" class="btn btn-sm btn-success"><i class="fas fa-balance-scale me-1"></i> Abstract of Quotations ({{ $rfq->aoq->status->label() }})</a>
                        @else
                            <button class="btn btn-sm btn-success" id="btn_start_aoq"><i class="fas fa-balance-scale me-1"></i> Abstract of Quotations</button>
                        @endif
                    @endif
                @endif
            </div>
        </div>

        <div class="row g-2 mt-3">
            <div class="col-md-6">
                <div class="pr-stat">
                    <div class="pr-label">PR / JR</div>
                    @forelse ($requests as $pr)
                        <a href="#" class="badge rounded-pill bg-light text-dark border text-decoration-none me-1 js-view-request" data-url="{{ route('procurement.rfqs.request', [$rfq, $pr]) }}" data-title="{{ $pr->title() }}" title="View {{ $pr->title() }} ({{ $pr->office->shortName() }})"><i class="far fa-eye me-1"></i>{{ $pr->title() }}</a>
                    @empty
                        <div class="text-muted small">None yet</div>
                    @endforelse
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pr-stat"><div class="pr-label">Date</div><div class="fw-semibold small">{{ $rfq->issued_at?->format('M d, Y') ?? 'On issue' }}</div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pr-stat"><div class="pr-label">Deadline</div><div class="fw-semibold small">{{ $rfq->submission_deadline?->format('M d, Y g:i A') ?? '—' }}</div></div>
            </div>
        </div>

        @if ($rfq->status === RfqStatus::Cancelled)
            <div class="alert alert-danger d-flex gap-2 align-items-start border-0 small mt-3 mb-0"><i class="fas fa-ban mt-1"></i><div>Cancelled {{ $rfq->cancelled_at?->format('M d, Y') }}: {{ $rfq->cancel_reason }}</div></div>
        @endif
    </div>
</div>

<div id="rfq_items">
{{-- Items --}}
<div class="border-top">
    <div class="card-header bg-white py-3 px-4 d-flex align-items-center gap-2" style="border-bottom: 1px solid #f1f5f9;">
        <h6 class="m-0 fw-bold"><i class="fas fa-list text-muted me-2"></i>Items</h6>
        <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">{{ $rfq->items->count() }}</span>
        @if ($canEdit)
            <button class="btn btn-sm btn-primary ms-auto" id="btn_add_lines"><i class="fas fa-plus me-1"></i> Add Items</button>
        @endif
    </div>
    @if ($rfq->items->isEmpty())
        <div class="py-5 px-4 text-center">
            <span class="pr-empty-ico mb-3"><i class="fas fa-box-open"></i></span>
            <div class="fw-semibold">No items yet.</div>
            <div class="text-muted small">Add items from submitted PRs / JRs of {{ $rfq->region->label() }}. Items of one procurement mode can go on one RFQ.</div>
            @if ($canEdit)<button class="btn btn-sm btn-outline-primary js-add-lines mt-3"><i class="fas fa-plus me-1"></i> Add items</button>@endif
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr class="text-nowrap">
                        <th class="ps-4" style="width: 50px;">#</th>
                        <th>Item &amp; Description</th>
                        <th class="text-center">Qty</th>
                        <th>Unit</th>
                        <th class="text-end">ABC / Unit</th>
                        <th class="text-end {{ $canEdit ? '' : 'pe-4' }}">ABC Total</th>
                        <th>PR / JR</th>
                        @if ($canEdit)<th class="pe-4" style="width: 60px;"><span class="visually-hidden">Remove</span></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rfq->items as $i => $item)
                        @php $line = $item->line; @endphp
                        <tr>
                            <td class="ps-4 text-muted">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $line->description }}</div>
                                @if ($line->specifications)<div class="text-muted fst-italic" style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($line->specifications, 300) }}</div>@endif
                            </td>
                            <td class="text-center">{{ $qty($line->quantity) }}</td>
                            <td>{{ $line->unit }}</td>
                            <td class="text-end text-nowrap">{{ number_format((float) $line->unit_cost, 2) }}</td>
                            <td class="text-end text-nowrap fw-semibold {{ $canEdit ? '' : 'pe-4' }}">{{ number_format((float) $line->total_cost, 2) }}</td>
                            <td class="text-nowrap">
                                <a href="#" class="text-decoration-none js-view-request" data-url="{{ route('procurement.rfqs.request', [$rfq, $line->request]) }}" data-title="{{ $line->request->title() }}">{{ $line->request->title() }}</a>
                                <div class="text-muted">{{ $line->request->office->shortName() }}</div>
                            </td>
                            @if ($canEdit)
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-light border text-danger btn-remove-line" data-id="{{ $item->id }}" title="Take off the RFQ" aria-label="Take {{ $line->description }} off the RFQ"><i class="fas fa-times"></i></button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td colspan="5" class="text-end">ABC</td>
                        <td class="text-end text-nowrap {{ $canEdit ? '' : 'pe-4' }}">{{ number_format((float) $rfq->total_abc, 2) }}</td>
                        <td colspan="{{ $canEdit ? 2 : 1 }}"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
</div>

{{-- Terms and signatory --}}
<div class="border-top">
    <div class="card-body p-4">
        <form id="form_rfq" autocomplete="off">
            @csrf
            <fieldset class="border-0 p-0 m-0" @disabled(! $canEdit)>
                <div class="pr-label mb-2">Request</div>
                <div class="row g-3">
                    <div class="col-lg-8">
                        <label class="form-label small fw-semibold text-muted mb-1">Title <span class="fw-normal">(optional, not printed)</span></label>
                        <input type="text" name="title" class="form-control form-control-sm" value="{{ $rfq->title }}" maxlength="500" placeholder="Blank: named after its items">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Deadline for quotations</label>
                        <input type="{{ $canEdit || $rfq->submission_deadline ? 'datetime-local' : 'text' }}" name="submission_deadline" class="form-control form-control-sm" value="{{ $rfq->submission_deadline?->format('Y-m-d\TH:i') }}">
                    </div>
                    {{-- Notes 2 and 3 of the printed RFQ; left blank, they are filled in by hand --}}
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Delivery period</label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="delivery_period" class="form-control" value="{{ is_numeric($rfq->delivery_period) ? (int) $rfq->delivery_period : '' }}" min="1" max="3650" placeholder="—">
                            <span class="input-group-text">calendar days</span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Price validity</label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="price_validity" class="form-control" value="{{ is_numeric($rfq->price_validity) ? (int) $rfq->price_validity : '' }}" min="1" max="3650" placeholder="—">
                            <span class="input-group-text">calendar days</span>
                        </div>
                    </div>
                    {{-- How the quotations are evaluated and awarded; per lot is printed as a note for the suppliers --}}
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Award</label>
                        <select name="award_basis" class="form-select form-select-sm" title="Per item: each item to its lowest offer that meets the specs. Per lot: all items to the one supplier with the lowest total.">
                            @foreach (\App\Enums\AwardBasis::cases() as $basis)
                                <option value="{{ $basis->value }}" @selected(($rfq->award_basis ?? \App\Enums\AwardBasis::Item) === $basis)>{{ $basis->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Other note</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="1" maxlength="2000" style="resize: none;" placeholder="Printed as note 7, e.g. samples may be required">{{ $rfq->remarks }}</textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset class="border-0 p-0 m-0" @disabled(! $canSign)>
                <div class="pr-label mt-4 mb-2">Signatory and canvasser</div>
                <div class="row g-3 align-items-end">
                    <div class="col-sm-6 col-xl-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Signed by — printed name</label>
                        @include('procurement.partials.signatory_select', [
                            'name' => 'signatory_name', 'value' => $rfq->signatory_name, 'designationField' => 'signatory_designation',
                            'people' => $people, 'placeholder' => 'e.g. Head, BAC Secretariat',
                        ])
                    </div>
                    <div class="col-sm-6 col-xl-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Designation</label>
                        <input type="text" name="signatory_designation" class="form-control form-control-sm" value="{{ $rfq->signatory_designation }}" maxlength="255">
                    </div>
                    <div class="col-sm-6 col-xl-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Canvassed by</label>
                        @include('procurement.partials.signatory_select', [
                            'name' => 'canvassed_by_name', 'value' => $rfq->canvassed_by_name, 'designationField' => '',
                            'people' => $people, 'placeholder' => 'Blank: written in by the canvasser',
                        ])
                    </div>
                </div>
                @if ($canSign)
                    <div class="d-flex justify-content-end border-top pt-3 mt-3">
                        <button type="submit" class="btn btn-sm btn-success px-3"><i class="fas fa-save me-1"></i> Save {{ $canEdit ? 'Details' : 'Names' }}</button>
                    </div>
                @endif
            </fieldset>
        </form>
    </div>
</div>
</div>{{-- one card --}}

<div id="modal-body"></div>

{{-- A PR / JR on the RFQ, as printed --}}
<div class="modal fade" id="REQUEST_VIEW_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <h5 class="modal-title fw-bold mb-0" id="request_view_title"></h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 bg-body-tertiary">
                <iframe id="request_view_frame" title="Request" style="width: 100%; height: 75vh; border: 0; display: block;"></iframe>
            </div>
            <div class="modal-footer bg-body-tertiary border-0 py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_request_view_print"><i class="fas fa-print me-1"></i> Print</button>
            </div>
        </div>
    </div>
</div>
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

    function refreshView() {
        const y = window.scrollY;
        return $.get(window.location.href).done(function (html) {
            const page = $('<div>').append($.parseHTML(html));
            if (!page.find('#rfq_items').length) { window.location.reload(); return; }
            $('#rfq_header').replaceWith(page.find('#rfq_header'));
            $('#rfq_items').replaceWith(page.find('#rfq_items'));
            window.scrollTo(0, y);
        }).fail(() => window.location.reload());
    }

    function post(url, data, method = 'POST') {
        return $.ajax({ url: url, type: 'POST', data: Object.assign({ _token: csrf, _method: method }, data || {}), headers: { Accept: 'application/json' } });
    }

    function formData() {
        const data = {};
        $('#form_rfq').serializeArray().forEach((f) => { if (f.name !== '_token') data[f.name] = f.value; });
        return data;
    }

    $('#form_rfq').on('submit', function (e) {
        e.preventDefault();
        post('{{ route("procurement.rfqs.header", $rfq) }}', formData())
            .done((res) => { toastr.success(res.message, 'Success'); refreshView(); })
            .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
    });

    // ---------- Items ----------
    $(document).on('click', '#btn_add_lines, .js-add-lines', function () {
        if (isModalOpen) return;
        isModalOpen = true;
        $.get('{{ route("procurement.rfqs.picker", $rfq) }}', function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById('RFQ_PICKER_MODAL');
            new bootstrap.Modal(el).show();
            el.addEventListener('shown.bs.modal', () => setTimeout(() => $('#rfq_pick_search').trigger('focus')));
            el.addEventListener('hidden.bs.modal', function () { isModalOpen = false; $('#modal-body').html(''); });
        }).fail(function (xhr) { isModalOpen = false; toastr.error(errorText(xhr), 'Error'); });
    });

    $(document).off('submit', '#form_rfq_picker').on('submit', '#form_rfq_picker', function (e) {
        e.preventDefault();
        const lines = $(this).find('.js-line:checked').map((_, el) => el.value).get();
        if (!lines.length) { toastr.warning('Choose at least one item.'); return; }
        $('#btn_add_selected').prop('disabled', true);
        post('{{ route("procurement.rfqs.lines.store", $rfq) }}', { lines: lines })
            .done(function (res) {
                toastr.success(res.message, 'Success');
                bootstrap.Modal.getInstance(document.getElementById('RFQ_PICKER_MODAL')).hide();
                refreshView();
            })
            .fail(function (xhr) { $('#btn_add_selected').prop('disabled', false); toastr.error(errorText(xhr), 'Error'); });
    });

    $(document).on('click', '.btn-remove-line', function () {
        post('{{ route("procurement.rfqs.lines.destroy", $rfq) }}', { id: $(this).data('id') }, 'DELETE')
            .done((res) => { toastr.success(res.message, 'Success'); refreshView(); })
            .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
    });

    // ---------- PR / JR viewer ----------
    const viewModal = document.getElementById('REQUEST_VIEW_MODAL');
    $(document).on('click', '.js-view-request', function (e) {
        e.preventDefault();
        $('#request_view_title').text($(this).data('title'));
        $('#request_view_frame').attr('src', $(this).data('url') + '?embed=1');
        bootstrap.Modal.getOrCreateInstance(viewModal).show();
    });
    viewModal.addEventListener('hidden.bs.modal', () => $('#request_view_frame').attr('src', 'about:blank'));
    $('#btn_request_view_print').on('click', () => document.getElementById('request_view_frame').contentWindow.print());

    // ---------- Workflow ----------
    $(document).on('click', '#btn_print', function () {
        window.open('{{ route("procurement.rfqs.print", $rfq) }}', '_blank');
    });

    $(document).on('click', '#btn_issue', function () {
        Swal.fire({
            title: 'Issue this RFQ?',
            html: 'It gets its RFQ number and the terms are fixed. Print it for the canvassers; the PRs / JRs on it cannot be revised or cancelled while it is open.',
            icon: 'question', showCancelButton: true, confirmButtonColor: '#0d6efd', confirmButtonText: 'Issue', reverseButtons: true
        }).then((r) => {
            if (!r.isConfirmed) return;
            post('{{ route("procurement.rfqs.issue", $rfq) }}', formData())
                .done((res) => { toastr.success(res.message, 'Success'); setTimeout(() => window.location.reload(), 600); })
                .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
        });
    });

    $(document).on('click', '#btn_start_aoq', function () {
        const btn = $(this).prop('disabled', true);
        post('{{ route("procurement.aoqs.start", $rfq) }}', {})
            .done((res) => { toastr.success(res.message, 'Success'); window.location.href = res.url; })
            .fail((xhr) => { btn.prop('disabled', false); toastr.error(errorText(xhr), 'Error'); });
    });

    $(document).on('click', '#btn_cancel', function () {
        Swal.fire({
            title: 'Cancel {{ $rfq->title() }}?', input: 'textarea', inputPlaceholder: 'Reason (required)',
            inputValidator: (v) => !v ? 'Please state the reason.' : undefined,
            text: 'Its items can then go on another RFQ.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Cancel RFQ', cancelButtonText: 'Keep', reverseButtons: true
        }).then((r) => {
            if (!r.isConfirmed) return;
            post('{{ route("procurement.rfqs.cancel", $rfq) }}', { reason: r.value })
                .done((res) => { toastr.success(res.message, 'Success'); setTimeout(() => window.location.reload(), 600); })
                .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
        });
    });

    $(document).on('click', '#btn_delete', function () {
        Swal.fire({ title: 'Delete this draft?', text: 'Its items go back to the list of items to quote.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Delete', reverseButtons: true })
            .then((r) => {
                if (!r.isConfirmed) return;
                post('{{ route("procurement.rfqs.destroy", $rfq) }}', {}, 'DELETE')
                    .done((res) => { toastr.success(res.message, 'Success'); setTimeout(() => window.location.href = '{{ route("procurement.rfqs.index") }}', 600); })
                    .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
            });
    });
});
</script>
@endpush
