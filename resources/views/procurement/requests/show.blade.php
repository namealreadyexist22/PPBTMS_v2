@extends('BackEnd.layouts.master')

@section('content')
@php
    use App\Enums\RequestKind;
    use App\Enums\RequestStatus;
    $jr = $pr->kind === RequestKind::Jr;
    $canPrintSign = $canManage && in_array($pr->status, [RequestStatus::Draft, RequestStatus::Submitted], true);
    $papMissing = is_string($lines);
@endphp

<style>
    .pr-card { border-radius: 12px; }
    .pr-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; color: var(--bs-secondary-color); }
    .pr-stat { border: var(--bs-border-width) solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg); padding: .5rem 1rem; height: 100%; }
    .pr-bar { height: 6px; }
    .pr-ico, .pr-empty-ico { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; background-color: var(--bs-primary-bg-subtle); color: var(--bs-primary); }
    .pr-ico { width: 36px; height: 36px; border-radius: var(--bs-border-radius-lg); }
    .pr-empty-ico { width: 56px; height: 56px; border-radius: 50%; font-size: 1.4rem; }
    #pr_items .table > thead th { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; color: var(--bs-secondary-color); }
    /* AdminLTE's clearfix would be an extra flex item (and gap) after the Add Item button */
    #pr_items .card-header::after { content: none; }
    /* Edit / Remove stay in view when the table scrolls sideways (narrow screens) */
    #pr_items .pr-actions { position: sticky; right: 0; z-index: 1; }
    .pr-clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    /* Item modal: the form sits between .modal-content and .modal-body, so it joins the flex column
       (the body scrolls and the footer stays, fullscreen on phones) */
    #form_request_line { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    /* Read-only: show the values as text */
    #form_header fieldset:disabled .form-control,
    #form_header fieldset:disabled .form-select { background: transparent; border-color: transparent; box-shadow: none; padding-left: 0; resize: none; }
    #form_header fieldset:disabled .select2-container { pointer-events: none; }
    #form_header fieldset:disabled .select2-selection { background: transparent !important; border-color: transparent !important; padding-left: 0 !important; }
    #form_header fieldset:disabled .select2-selection__clear,
    #form_header fieldset:disabled .select2-selection__arrow { display: none; }
</style>

<div class="mb-3">
    <a href="{{ route('procurement.requests.index', ['kind' => $pr->kind->value]) }}" class="text-decoration-none small text-muted">
        <i class="fas fa-arrow-left me-1"></i> Back to {{ $pr->kind->label() }}s
    </a>
</div>

{{-- One card: header, items, then the purpose and signatories. Header and items are redrawn in
     place after item changes (no full reload) --}}
<div class="card border-0 shadow-sm pr-card overflow-hidden mb-3">
<div id="pr_header" data-total="{{ number_format((float) $pr->total_amount, 2) }}">
<div>
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="pr-label">{{ $pr->kind->label() }} · {{ $pr->procurementLabel() }}</div>
                <h4 class="fw-bold mb-1">
                    {{ $pr->kind->short() }} {{ $pr->request_no ?? '(not yet numbered)' }}
                    @if ($pr->revision)<span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle align-middle" style="font-size: .7rem;">Rev. {{ $pr->revision }}</span>@endif
                    @php $c = $pr->status->color(); @endphp
                    <span class="badge rounded-pill bg-{{ $c }}-subtle text-{{ $c }}-emphasis border border-{{ $c }}-subtle align-middle" style="font-size: .7rem;">{{ $pr->status->label() }}</span>
                    @if ($rfq)
                        {{-- The RFQ's details in a modal (its page linked there for those who may open RFQs) --}}
                        <a href="#" class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle align-middle text-decoration-none js-modal-view"
                           data-url="{{ route('procurement.requests.rfq', $pr) }}" data-modal="RFQ_DETAILS_MODAL" style="font-size: .7rem;" title="View the RFQ's details">On {{ $rfq->title() }} <i class="fas fa-eye ms-1"></i></a>
                    @endif
                </h4>
                <div class="text-muted small">{{ $pr->office->pathLabel() }}</div>
            </div>

            {{-- Phones: the buttons share one full-width row --}}
            <div class="d-flex flex-wrap gap-2 col-12 col-md-auto">
                @if ($canPrintSign)
                    <button class="btn btn-sm btn-outline-secondary flex-fill flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#PRINT_MODAL"><i class="fas fa-print me-1"></i> Print</button>
                @else
                    <a href="{{ route('procurement.requests.print', $pr) }}" target="_blank" class="btn btn-sm btn-outline-secondary flex-fill flex-md-grow-0"><i class="fas fa-print me-1"></i> Print</a>
                @endif
                <button type="button" class="btn btn-sm btn-outline-secondary flex-fill flex-md-grow-0 js-modal-view" data-url="{{ route('procurement.requests.trail', $pr) }}" data-modal="REQUEST_TRAIL_MODAL"><i class="fas fa-route me-1"></i> Status</button>
                @if ($canEdit)
                    <button class="btn btn-sm btn-outline-danger flex-fill flex-md-grow-0" id="btn_delete"><i class="fas fa-trash-alt me-1"></i> Delete Draft</button>
                    <button class="btn btn-sm btn-primary flex-fill flex-md-grow-0" id="btn_submit" @disabled($pr->items->isEmpty())><i class="fas fa-paper-plane me-1"></i> Submit</button>
                @endif
                {{-- Submitted = locked: Revise / Cancel only once the BAC returns it for that --}}
                @if ($canManage && $pr->isReturned())
                    @if ($pr->return_for === 'revision')
                        @if ($openRevision)
                            <a href="{{ route('procurement.requests.show', $openRevision) }}" class="btn btn-sm btn-outline-primary flex-fill flex-md-grow-0"><i class="fas fa-code-branch me-1"></i> Open Rev. {{ $openRevision->revision }} (draft)</a>
                        @else
                            <button class="btn btn-sm btn-outline-primary flex-fill flex-md-grow-0" id="btn_revise"><i class="fas fa-code-branch me-1"></i> Revise</button>
                        @endif
                    @else
                        <button class="btn btn-sm btn-outline-danger flex-fill flex-md-grow-0" id="btn_cancel"><i class="fas fa-ban me-1"></i> Cancel</button>
                    @endif
                @endif
            </div>
        </div>

        <div class="row g-2 mt-3 small">
            <div class="col-12 col-md-5">
                <div class="pr-stat">
                    <div class="pr-label">Charge to</div>
                    <div class="fw-semibold">{{ $pr->pap->label() }}</div>
                    <div><a href="#" class="text-decoration-none js-modal-view" data-url="{{ route('procurement.requests.ppmp', $pr) }}" data-modal="PPMP_DETAILS_MODAL" title="View the PPMP's details">PPMP {{ $pr->ppmp->ppmp_no }} <i class="fas fa-eye ms-1" style="font-size: .7rem;"></i></a> · FY {{ $pr->fiscal_year }}</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="pr-stat">
                    <div class="pr-label">Date</div>
                    <div class="fw-semibold">{{ $pr->submitted_at?->format('M d, Y') ?? 'On submit' }}</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="pr-stat">
                    <div class="pr-label">Revisions</div>
                    <div class="fw-semibold d-flex flex-wrap gap-1">
                        @forelse ($revisions as $rev)
                            @if ($rev->uuid === $pr->uuid)
                                <span class="badge rounded-pill bg-dark">{{ $rev->revision ? 'Rev. ' . $rev->revision : 'Original' }}</span>
                            @else
                                <a href="{{ route('procurement.requests.show', $rev->uuid) }}" class="badge rounded-pill bg-light text-dark border text-decoration-none" title="{{ $rev->status->label() }}">{{ $rev->revision ? 'Rev. ' . $rev->revision : 'Original' }}</a>
                            @endif
                        @empty
                            <span class="text-muted fw-normal">—</span>
                        @endforelse
                    </div>
                </div>
            </div>
            {{-- <div class="col-12 col-md-3">
                <div class="pr-stat text-md-end bg-primary-subtle border-primary-subtle">
                    <div class="pr-label">{{ $jr ? 'ABC' : 'Total' }}</div>
                    <div class="fs-5 fw-bold">₱ {{ number_format((float) $pr->total_amount, 2) }}</div>
                </div>
            </div> --}}
        </div>

        @if ($pr->status === RequestStatus::Draft && $pr->revised_from_id)
            <div class="alert alert-info d-flex gap-2 align-items-start border-0 small mt-3 mb-0">
                <i class="fas fa-code-branch mt-1"></i>
                <div>Revision of <a href="{{ route('procurement.requests.show', $pr->revisedFrom) }}">{{ $pr->revisedFrom->title() }}</a>. It keeps the number and the signatories;
                when submitted, it replaces the request before it and the PPMP charges are updated to this revision's amounts. Have it signed again by the same signatories.</div>
            </div>
        @endif
        @if ($pr->status === RequestStatus::Submitted && $rfq)
            <div class="alert alert-primary d-flex gap-2 align-items-start border-0 small mt-3 mb-0">
                <i class="fas fa-cogs mt-1"></i>
                <div>In process at the BAC ({{ $rfq->title() }}{{ $rfq->aoq ? ', ' . $rfq->aoq->title() . ' ' . mb_strtolower($rfq->aoq->status->label()) : '' }}).
                It can no longer be revised or cancelled by the office; if it needs to be, ask the BAC to <strong>return it</strong> for revision or for cancellation.</div>
            </div>
        @elseif ($pr->isReturned())
            <div class="alert alert-danger d-flex gap-2 align-items-start border-0 small mt-3 mb-0">
                <i class="fas fa-undo mt-1"></i>
                <div><strong>Returned by the BAC {{ \App\Models\Procurement\PurchaseRequest::RETURN_FOR[$pr->return_for] ?? '' }}</strong> {{ $pr->returned_at->format('M d, Y h:i A') }}: {{ $pr->return_reason }}
                    <div class="mt-1">{{ $pr->return_for === 'cancellation' ? 'Cancel it' : 'Revise it and submit the revision' }}{{ $canManage ? '' : ' (the requesting office does this)' }}; it goes on an RFQ again only once revised.</div></div>
            </div>
        @elseif ($pr->status === RequestStatus::Submitted)
            <div class="alert alert-success d-flex gap-2 align-items-start border-0 small mt-3 mb-0">
                <i class="fas fa-check-circle mt-1"></i>
                <div>Submitted {{ $pr->submitted_at->format('M d, Y h:i A') }}. ₱{{ number_format((float) $pr->total_amount, 2) }} is charged to the PPMP projects.
                It is now locked with the BAC: to change or drop it, ask the BAC to <strong>return it</strong> for revision or for cancellation.</div>
            </div>
        @endif
        @if ($pr->status === RequestStatus::Superseded)
            <div class="alert alert-secondary d-flex gap-2 align-items-start border-0 small mt-3 mb-0"><i class="fas fa-info-circle mt-1"></i><div>Replaced by a newer revision.</div></div>
        @endif
        @if ($pr->status === RequestStatus::Cancelled)
            <div class="alert alert-danger d-flex gap-2 align-items-start border-0 small mt-3 mb-0"><i class="fas fa-ban mt-1"></i><div>Cancelled {{ $pr->cancelled_at?->format('M d, Y') }}: {{ $pr->cancel_reason }}</div></div>
        @endif
        @if ($canEdit && $papMissing)
            <div class="alert alert-warning d-flex gap-2 align-items-start border-0 small mt-3 mb-0"><i class="fas fa-exclamation-triangle mt-1"></i><div>{{ $lines }}</div></div>
        @endif
    </div>
</div>
</div>

<div id="pr_items">
@php $cols = 6 + ($jr ? 1 : 0) + ($canEdit ? 1 : 0); @endphp
{{-- Items --}}
<div class="border-top">
    <div class="card-header bg-white py-3 px-4 d-flex align-items-center gap-2" style="border-bottom: 1px solid #f1f5f9;">
        <h6 class="m-0 fw-bold"><i class="fas fa-list text-muted me-2"></i>Items</h6>
        <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">{{ $pr->items->count() }}</span>
        @if ($canEdit && ! $papMissing)
            <button class="btn btn-sm btn-primary ms-auto" id="btn_add_line"><i class="fas fa-plus me-1"></i> Add Item</button>
        @endif
    </div>
    {{-- Empty: outside the table, so on phones it fits the card instead of the scrolled table width --}}
    @if ($pr->items->isEmpty())
        <div class="py-5 px-4 text-center">
            <span class="pr-empty-ico mb-3"><i class="fas fa-box-open"></i></span>
            <div class="fw-semibold">No items yet.</div>
            @if ($canEdit)<div class="text-muted small">Add the items to request; similar items (e.g. hard drives, RAM, RJ45) can go in one {{ $pr->kind->short() }}.</div>@endif
            @if ($canEdit && ! $papMissing)
                <button class="btn btn-sm btn-outline-primary js-add-line mt-3"><i class="fas fa-plus me-1"></i> Add the first item</button>
            @endif
        </div>
    @else
        {{-- md and up: the table; phones get the stacked list below --}}
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr class="text-nowrap">
                        <th class="ps-4">{{ $jr ? 'Property No.' : 'Stock No.' }}</th>
                        <th>Unit</th>
                        <th>Item Description</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Cost</th>
                        {{-- Read-only (no actions column): the last column mirrors the 24px left inset --}}
                        <th @class(['text-end', 'pe-4' => ! $jr && ! $canEdit])>Total</th>
                        @if ($jr)<th @class(['pe-4' => ! $canEdit])>Nature of Work</th>@endif
                        @if ($canEdit)<th class="text-end pe-4 pr-actions" style="width: 96px;"><span class="visually-hidden">Actions</span></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pr->items as $line)
                        <tr>
                            <td class="ps-4 text-nowrap">{{ $line->stock_no }}</td>
                            <td>{{ $line->unit }}</td>
                            <td style="min-width: 180px;">
                                <div class="fw-semibold">{{ $line->description }}</div>
                                @if ($line->ppmpItem)<div class="text-muted small"><i class="fas fa-diagram-project me-1"></i>{{ $line->ppmpItem->description }}</div>@endif
                                @if ($line->specifications)<div class="mt-2">@include('procurement.partials.specs', ['text' => $line->specifications])</div>@endif
                            </td>
                            <td class="text-end">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                            <td class="text-end">{{ number_format((float) $line->unit_cost, 2) }}</td>
                            <td @class(['text-end fw-semibold', 'pe-4' => ! $jr && ! $canEdit])>{{ number_format((float) $line->total_cost, 2) }}</td>
                            @if ($jr)<td @class(['pe-4' => ! $canEdit]) style="white-space: pre-line;">{{ $line->nature_of_work }}</td>@endif
                            @if ($canEdit)
                                <td class="text-end pe-4 text-nowrap pr-actions">
                                    <button class="btn btn-sm btn-light border btn-edit-line" data-id="{{ $line->id }}" title="Edit" aria-label="Edit item"><i class="fas fa-pen"></i></button>
                                    <button class="btn btn-sm btn-light border text-danger btn-delete-line" data-id="{{ $line->id }}" title="Remove" aria-label="Remove item"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="5" class="ps-4 fw-bold text-end">{{ $jr ? 'ABC' : 'TOTAL' }}</td>
                        <td @class(['text-end fw-bold', 'pe-4' => $cols === 6])>{{ number_format((float) $pr->total_amount, 2) }}</td>
                        @if ($cols - 6 > 0)<td colspan="{{ $cols - 6 }}" class="pe-4"></td>@endif
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Phones: one block per item, so the description is not hidden behind a scrolled table --}}
        <div class="list-group list-group-flush small d-md-none">
            @foreach ($pr->items as $line)
                <div class="list-group-item px-3 py-3">
                    <div class="d-flex align-items-start gap-2">
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="fw-semibold">{{ $line->description }}</div>
                            @if ($line->ppmpItem)<div class="text-muted"><i class="fas fa-diagram-project me-1"></i>{{ $line->ppmpItem->description }}</div>@endif
                        </div>
                        @if ($canEdit)
                            <div class="text-nowrap flex-shrink-0">
                                <button class="btn btn-sm btn-light border btn-edit-line" data-id="{{ $line->id }}" title="Edit" aria-label="Edit item"><i class="fas fa-pen"></i></button>
                                <button class="btn btn-sm btn-light border text-danger btn-delete-line" data-id="{{ $line->id }}" title="Remove" aria-label="Remove item"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        @endif
                    </div>
                    @if ($line->specifications)<div class="mt-2">@include('procurement.partials.specs', ['text' => $line->specifications])</div>@endif
                    @if ($jr && $line->nature_of_work)
                        <div class="mt-2"><div class="pr-label">Nature of Work</div><div style="white-space: pre-line;">{{ $line->nature_of_work }}</div></div>
                    @endif
                    <div class="d-flex align-items-baseline justify-content-between gap-2 mt-2">
                        <span class="text-muted">@if ($line->stock_no){{ $line->stock_no }} · @endif<span class="text-nowrap">{{ trim(rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') . ' ' . $line->unit) }} × ₱{{ number_format((float) $line->unit_cost, 2) }}</span></span>
                        <span class="fw-semibold text-nowrap">₱{{ number_format((float) $line->total_cost, 2) }}</span>
                    </div>
                </div>
            @endforeach
            <div class="list-group-item px-3 py-2 bg-body-tertiary d-flex justify-content-between fw-bold">
                <span>{{ $jr ? 'ABC' : 'TOTAL' }}</span>
                <span class="text-nowrap">₱{{ number_format((float) $pr->total_amount, 2) }}</span>
            </div>
        </div>
    @endif
</div>

</div>

{{-- Purpose and signatories --}}
<div class="border-top">
    <div class="card-body p-4">
        <form id="form_header" autocomplete="off">
            @csrf
            <fieldset class="border-0 p-0 m-0" @disabled(! $canEdit)>
                {{-- <div class="pr-label mb-2">Request</div> --}}
                <div class="row g-3">
                    <div class="{{ $jr ? 'col-lg-9' : 'col-12' }}">
                        <label class="form-label small fw-semibold text-muted mb-1">Purpose</label>
                        @if ($canEdit)
                            <textarea name="purpose" class="form-control form-control-sm" rows="2" style="resize: none;">{{ $pr->purpose }}</textarea>
                        @else
                            {{-- Read-only: the whole purpose as text, however long --}}
                            <div class="small" style="white-space: pre-line;">{{ $pr->purpose ?: '—' }}</div>
                        @endif
                    </div>
                    @if ($jr)
                        <div class="col-lg-3">
                            <label class="form-label small fw-semibold text-muted mb-1">JR Type</label>
                            <select name="jr_type" class="form-select form-select-sm">
                                <option value="">Choose…</option>
                                @foreach ($jrTypes as $value => $label)
                                    <option value="{{ $value }}" @selected($pr->jr_type === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <div class="pr-label mt-4 mb-2">Signatories</div>
                {{-- align-items-end: the inputs line up when a label wraps --}}
                <div class="row g-3 align-items-end">
                    @include('procurement.requests.extras.signatory_fields', ['prefix' => '', 'col' => 'col-sm-6 col-xl-3'])
                </div>
                @if ($canEdit)
                    <div class="d-flex justify-content-end border-top pt-3 mt-3">
                        <button type="submit" class="btn btn-sm btn-success px-3"><i class="fas fa-save me-1"></i> Save Details</button>
                    </div>
                @endif
            </fieldset>
        </form>
    </div>
</div>
</div>{{-- one card --}}


{{-- Print: set the signatories, then print --}}
@if ($canPrintSign)
    <div class="modal fade" id="PRINT_MODAL" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-white py-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="pr-ico"><i class="fas fa-print"></i></span>
                        <h5 class="modal-title fw-bold mb-0">Print {{ $pr->title() }}</h5>
                    </div>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="form_print" autocomplete="off">
                    @csrf
                    <div class="modal-body p-4">
                        <p class="small text-muted">Signatories printed on the form. They are saved with the {{ $pr->kind->short() }} and kept on its revisions.</p>
                        <div class="row g-3">@include('procurement.requests.extras.signatory_fields', ['prefix' => 'p_', 'col' => 'col-sm-6'])</div>
                    </div>
                    <div class="modal-footer bg-body-tertiary py-2">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <a href="{{ route('procurement.requests.print', $pr) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Print without saving</a>
                        <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fas fa-print me-1"></i> Save &amp; Print</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<div id="modal-body"></div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';
    let isModalOpen = false;

    function errorText(xhr) {
        const res = xhr.responseJSON || {};
        return res.errors ? Object.values(res.errors)[0][0] : (res.message || 'Something went wrong.');
    }

    // Read-only views in a modal: the status trail, the PPMP's details
    $(document).on('click', '.js-modal-view', function (e) {
        e.preventDefault();
        if (isModalOpen) return;
        isModalOpen = true;
        const modalId = $(this).data('modal');
        $.get($(this).data('url'), function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById(modalId);
            new bootstrap.Modal(el).show();
            el.addEventListener('hidden.bs.modal', function () { isModalOpen = false; $('#modal-body').html(''); });
        }).fail(function (xhr) { isModalOpen = false; toastr.error(errorText(xhr), 'Error'); });
    });

    // Redraw the header (total, Submit button) and the items table from the server
    function refreshView() {
        const y = window.scrollY;
        return $.get(window.location.href).done(function (html) {
            const page = $('<div>').append($.parseHTML(html));
            if (!page.find('#pr_items').length) { window.location.reload(); return; }
            $('#pr_header').replaceWith(page.find('#pr_header'));
            $('#pr_items').replaceWith(page.find('#pr_items'));
            window.scrollTo(0, y);
        }).fail(() => window.location.reload());
    }

    function sendEdit(url, data, method = 'POST') {
        return $.ajax({
            url: url, type: 'POST',
            data: Object.assign({ _token: csrf, _method: method }, data || {}),
            success: (res) => { toastr.success(res.message, 'Success'); refreshView(); },
            error: (xhr) => toastr.error(errorText(xhr), 'Error')
        });
    }

    function sendAction(url, data, method = 'POST') {
        return $.ajax({
            url: url, type: 'POST',
            data: Object.assign({ _token: csrf, _method: method }, data || {}),
            success: function (res) {
                toastr.success(res.message, 'Success');
                setTimeout(function () { window.location.href = res.url || window.location.href; }, 600);
            },
            error: (xhr) => toastr.error(errorText(xhr), 'Error')
        });
    }


    $('#form_header').on('submit', function (e) {
        e.preventDefault();
        $.post('{{ route("procurement.requests.header", $pr) }}', $(this).serialize())
            .done((res) => toastr.success(res.message, 'Success'))
            .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
    });

    // Print dialog: save the signatories, then open the form
    $('#form_print').on('submit', function (e) {
        e.preventDefault();
        const win = window.open('about:blank', '_blank');
        const data = $(this).serializeArray().map((f) => ({ name: f.name.replace(/^p_/, ''), value: f.value }));
        $.post('{{ route("procurement.requests.signatories", $pr) }}', $.param(data))
            .done(function () {
                win.location = '{{ route("procurement.requests.print", $pr) }}';
                window.location.reload();
            })
            .fail(function (xhr) { win.close(); toastr.error(errorText(xhr), 'Error'); });
    });

    // ---------- Items ----------
    // id: edit that line; project: a new line on that PPMP project (from "Left to request")
    function loadLineModal(id = null, project = null) {
        if (isModalOpen) return;
        isModalOpen = true;
        $.get('{{ route("procurement.requests.lines.entry", $pr) }}', id ? { id: id } : (project ? { project: project } : {}), function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById('REQUEST_LINE_MODAL');
            new bootstrap.Modal(el).show();
            // Deferred: AdminLTE focuses the modal's first button (the close button) on shown
            el.addEventListener('shown.bs.modal', () => setTimeout(() => ($('#project_picker').is(':visible') ? $('#pick_search') : $('#form_request_line [name=quantity]')).trigger('focus')));
            el.addEventListener('hidden.bs.modal', function () { isModalOpen = false; $('#modal-body').html(''); });
        }).fail(function (xhr) { isModalOpen = false; toastr.error(errorText(xhr), 'Error'); });
    }

    $(document).on('click', '#btn_add_line, .js-add-line', function () { loadLineModal(null, $(this).data('project') || null); });
    $(document).on('click', '.btn-edit-line', function () { loadLineModal($(this).data('id')); });

    $(document).off('submit', '#form_request_line').on('submit', '#form_request_line', function (e) {
        e.preventDefault();
        const form = $(this);
        const summary = $('#line_error_summary');
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback').text('');
        summary.addClass('d-none');
        form.data('saving', true);   // the modal keeps Save off while this is set
        $('#btn_save_line').prop('disabled', true);

        $.post('{{ route("procurement.requests.lines.store", $pr) }}', form.serialize())
            .done(function (res) {
                toastr.success(res.message, 'Success');
                bootstrap.Modal.getInstance(document.getElementById('REQUEST_LINE_MODAL')).hide();
                refreshView();
            })
            .fail(function (xhr) {
                // Save comes back on unless the item is still over a limit
                form.data('saving', false).find('[name="quantity"]').trigger('input');
                const res = xhr.responseJSON || {};
                summary.removeClass('d-none');
                if (res.errors) {
                    summary.find('span').text('Please correct the highlighted errors below.');
                    $.each(res.errors, function (key, messages) {
                        const input = form.find('[name="' + key + '"]');
                        input.addClass('is-invalid');
                        input.siblings('.invalid-feedback').text(messages[0]);
                    });
                } else {
                    summary.find('span').text(res.message ?? 'Something went wrong.');
                }
            });
    });

    $(document).on('click', '.btn-delete-line', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Remove this item?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Remove', reverseButtons: true })
            .then((r) => { if (r.isConfirmed) sendEdit('{{ route("procurement.requests.lines.destroy", $pr) }}', { id: id }, 'DELETE'); });
    });

    // ---------- Workflow ----------
    $(document).on('click', '#btn_submit', function () {
        Swal.fire({
            title: 'Submit this {{ $pr->kind->short() }}?',
            html: @if ($pr->revised_from_id)
                'It replaces <b>{{ $pr->revisedFrom?->title() }}</b> and keeps its number. The PPMP charges change to this revision\'s amounts.'
            @else
                'It gets its {{ $pr->kind->short() }} number and <b>₱' + $('#pr_header').data('total') + '</b> is charged to the PPMP projects. It can no longer be edited, only revised or cancelled.'
            @endif,
            icon: 'question', showCancelButton: true, confirmButtonColor: '#0d6efd', confirmButtonText: 'Submit', reverseButtons: true
        }).then((r) => {
            if (!r.isConfirmed) return;
            const data = {};
            $('#form_header').serializeArray().forEach((f) => { if (f.name !== '_token') data[f.name] = f.value; });
            sendAction('{{ route("procurement.requests.submit", $pr) }}', data);
        });
    });

    $(document).on('click', '#btn_revise', function () {
        Swal.fire({
            title: 'Revise {{ $pr->title() }}?',
            text: 'A draft revision with the same number and signatories is created. This one stays in effect until the revision is submitted.',
            icon: 'question', showCancelButton: true, confirmButtonColor: '#0d6efd', confirmButtonText: 'Create revision', reverseButtons: true
        }).then((r) => { if (r.isConfirmed) sendAction('{{ route("procurement.requests.revise", $pr) }}'); });
    });

    $(document).on('click', '#btn_cancel', function () {
        Swal.fire({
            title: 'Cancel {{ $pr->title() }}?', input: 'textarea', inputPlaceholder: 'Reason (required)',
            inputValidator: (v) => !v ? 'Please state the reason.' : undefined,
            text: 'Its amount goes back to the PPMP projects.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Cancel request', cancelButtonText: 'Keep', reverseButtons: true
        }).then((r) => { if (r.isConfirmed) sendAction('{{ route("procurement.requests.cancel", $pr) }}', { reason: r.value }); });
    });

    $(document).on('click', '#btn_delete', function () {
        Swal.fire({ title: 'Delete this draft?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Delete', reverseButtons: true })
            .then((r) => { if (r.isConfirmed) sendAction('{{ route("procurement.requests.destroy", $pr) }}', {}, 'DELETE'); });
    });
});
</script>
@endpush
