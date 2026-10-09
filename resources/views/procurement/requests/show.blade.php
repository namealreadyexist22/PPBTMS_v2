@extends('BackEnd.layouts.master')

@section('content')
@php
    use App\Enums\RequestKind;
    use App\Enums\RequestStatus;
    $jr = $pr->kind === RequestKind::Jr;
    $canPrintSign = $canManage && in_array($pr->status, [RequestStatus::Draft, RequestStatus::Submitted], true);
    $papMissing = is_string($lines);
@endphp

<div class="mb-3">
    <a href="{{ route('procurement.requests.index', ['kind' => $pr->kind->value]) }}" class="text-decoration-none small text-muted">
        <i class="fas fa-arrow-left me-1"></i> Back to {{ $pr->kind->label() }}s
    </a>
</div>

{{-- Header and items are redrawn in place after item changes (no full reload) --}}
<div id="pr_header" data-total="{{ number_format((float) $pr->total_amount, 2) }}">
<div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="small text-muted text-uppercase fw-semibold" style="letter-spacing: .5px;">{{ $pr->kind->label() }} · {{ $pr->procurementLabel() }}</div>
                <h4 class="fw-bold mb-1">
                    {{ $pr->kind->short() }} {{ $pr->request_no ?? '(not yet numbered)' }}
                    @if ($pr->revision)<span class="badge bg-info text-dark align-middle" style="font-size: .7rem;">Rev. {{ $pr->revision }}</span>@endif
                    <span class="badge bg-{{ $pr->status->color() }} align-middle" style="font-size: .7rem;">{{ $pr->status->label() }}</span>
                </h4>
                <div class="text-muted small">{{ $pr->office->pathLabel() }}</div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @if ($canPrintSign)
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#PRINT_MODAL"><i class="fas fa-print me-1"></i> Print</button>
                @else
                    <a href="{{ route('procurement.requests.print', $pr) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i> Print</a>
                @endif
                @if ($canEdit)
                    <button class="btn btn-sm btn-primary" id="btn_submit" @disabled($pr->items->isEmpty())><i class="fas fa-paper-plane me-1"></i> Submit</button>
                    <button class="btn btn-sm btn-outline-danger" id="btn_delete"><i class="fas fa-trash-alt me-1"></i> Delete Draft</button>
                @endif
                @if ($canManage && $pr->status === RequestStatus::Submitted)
                    @if ($openRevision)
                        <a href="{{ route('procurement.requests.show', $openRevision) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-code-branch me-1"></i> Open Rev. {{ $openRevision->revision }} (draft)</a>
                    @else
                        <button class="btn btn-sm btn-outline-primary" id="btn_revise"><i class="fas fa-code-branch me-1"></i> Revise</button>
                    @endif
                    <button class="btn btn-sm btn-outline-danger" id="btn_cancel"><i class="fas fa-ban me-1"></i> Cancel</button>
                @endif
            </div>
        </div>

        <hr class="my-3">

        <div class="row g-3 small">
            <div class="col-12 col-md-4">
                <div class="text-muted">Charge to</div>
                <div class="fw-semibold">{{ $pr->pap->label() }}</div>
                <a href="{{ route('procurement.ppmp.show', $pr->ppmp) }}" class="text-decoration-none">PPMP {{ $pr->ppmp->ppmp_no }}</a> · FY {{ $pr->fiscal_year }}
            </div>
            <div class="col-6 col-md-2">
                <div class="text-muted">Date</div>
                <div class="fw-semibold">{{ $pr->submitted_at?->format('M d, Y') ?? 'On submit' }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted">Revisions</div>
                <div>
                    @forelse ($revisions as $rev)
                        @if ($rev->uuid === $pr->uuid)
                            <span class="badge bg-dark">{{ $rev->revision ? 'Rev. ' . $rev->revision : 'Original' }}</span>
                        @else
                            <a href="{{ route('procurement.requests.show', $rev->uuid) }}" class="badge bg-light text-dark border text-decoration-none" title="{{ $rev->status->label() }}">{{ $rev->revision ? 'Rev. ' . $rev->revision : 'Original' }}</a>
                        @endif
                    @empty
                        <span class="text-muted">—</span>
                    @endforelse
                </div>
            </div>
            <div class="col-12 col-md-3 text-md-end">
                <div class="text-muted">{{ $jr ? 'ABC' : 'Total' }}</div>
                <div class="fw-bold fs-5">₱ {{ number_format((float) $pr->total_amount, 2) }}</div>
            </div>
        </div>

        @if ($pr->status === RequestStatus::Draft && $pr->revised_from_id)
            <div class="alert alert-info small mt-3 mb-0">
                <i class="fas fa-code-branch me-1"></i> Revision of <a href="{{ route('procurement.requests.show', $pr->revisedFrom) }}">{{ $pr->revisedFrom->title() }}</a>. It keeps the number and the signatories;
                when submitted, it replaces the request before it and the PPMP charges are updated to this revision's amounts. Have it signed again by the same signatories.
            </div>
        @endif
        @if ($pr->status === RequestStatus::Submitted)
            <div class="alert alert-success small mt-3 mb-0">
                <i class="fas fa-check-circle me-1"></i> Submitted {{ $pr->submitted_at->format('M d, Y h:i A') }}. ₱{{ number_format((float) $pr->total_amount, 2) }} is charged to the PPMP projects.
                To change it, use <strong>Revise</strong>; to drop it, <strong>Cancel</strong> (the amount goes back to the PPMP).
            </div>
        @endif
        @if ($pr->status === RequestStatus::Superseded)
            <div class="alert alert-secondary small mt-3 mb-0"><i class="fas fa-info-circle me-1"></i> Replaced by a newer revision.</div>
        @endif
        @if ($pr->status === RequestStatus::Cancelled)
            <div class="alert alert-danger small mt-3 mb-0"><i class="fas fa-ban me-1"></i> Cancelled {{ $pr->cancelled_at?->format('M d, Y') }}: {{ $pr->cancel_reason }}</div>
        @endif
        @if ($canEdit && $papMissing)
            <div class="alert alert-warning small mt-3 mb-0"><i class="fas fa-exclamation-triangle me-1"></i> {{ $lines }}</div>
        @endif
    </div>
</div>
</div>

{{-- Details and signatories --}}
<div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
    <div class="card-header bg-white pt-3 pb-2 px-4" style="border-bottom: 1px solid #f1f5f9;">
        <h6 class="m-0 fw-bold"><i class="fas fa-pen text-muted me-2"></i>Details</h6>
    </div>
    <div class="card-body px-4">
        <form id="form_header" autocomplete="off">
            @csrf
            <fieldset class="border-0 p-0 m-0" @disabled(! $canEdit)>
                <div class="row g-3">
                    <div class="col-md-{{ $jr ? 8 : 6 }}">
                        <label class="form-label small fw-semibold text-muted mb-1">Purpose</label>
                        <textarea name="purpose" class="form-control form-control-sm" rows="2">{{ $pr->purpose }}</textarea>
                    </div>
                    @if ($jr)
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted mb-1">JR Type</label>
                            <select name="jr_type" class="form-select form-select-sm">
                                <option value="">Choose…</option>
                                @foreach ($jrTypes as $value => $label)
                                    <option value="{{ $value }}" @selected($pr->jr_type === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">SAI No.</label>
                            <input type="text" name="sai_no" class="form-control form-control-sm" value="{{ $pr->sai_no }}" maxlength="50">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">SAI Date</label>
                            <input type="date" name="sai_date" class="form-control form-control-sm" value="{{ $pr->sai_date?->format('Y-m-d') }}">
                        </div>
                    @endif
                    @include('procurement.requests.extras.signatory_fields', ['prefix' => ''])
                </div>
                @if ($canEdit)
                    <div class="text-end mt-3"><button type="submit" class="btn btn-sm btn-success px-3"><i class="fas fa-save me-1"></i> Save Details</button></div>
                @endif
            </fieldset>
        </form>
    </div>
</div>

<div id="pr_items">
{{-- Items --}}
<div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white pt-3 pb-2 px-4 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #f1f5f9;">
        <h6 class="m-0 fw-bold"><i class="fas fa-list text-muted me-2"></i>Items ({{ $pr->items->count() }})</h6>
        @if ($canEdit && ! $papMissing)
            <button class="btn btn-sm btn-success" id="btn_add_line"><i class="fas fa-plus me-1"></i> Add Item</button>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle small mb-0">
            <thead class="table-light">
                <tr class="text-nowrap">
                    <th class="ps-4">{{ $jr ? 'Property No.' : 'Stock No.' }}</th>
                    <th>Unit</th>
                    <th>Item Description</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Unit Cost</th>
                    <th class="text-end">Total</th>
                    @if ($jr)<th>Nature of Work</th>@endif
                    <th>PPMP project</th>
                    @if ($canEdit)<th class="text-center pe-4" style="width: 80px;"></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($pr->items as $line)
                    <tr>
                        <td class="ps-4">{{ $line->stock_no }}</td>
                        <td>{{ $line->unit }}</td>
                        <td>
                            <div class="fw-semibold">{{ $line->description }}</div>
                            @if ($line->specifications)<div class="text-muted fst-italic" style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($line->specifications, 300) }}</div>@endif
                        </td>
                        <td class="text-end">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                        <td class="text-end">{{ number_format((float) $line->unit_cost, 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format((float) $line->total_cost, 2) }}</td>
                        @if ($jr)<td style="white-space: pre-line;">{{ $line->nature_of_work }}</td>@endif
                        <td class="text-muted">{{ $line->ppmpItem?->description }}</td>
                        @if ($canEdit)
                            <td class="text-center pe-4 text-nowrap">
                                <button class="btn btn-link btn-sm p-0 me-2 btn-edit-line" data-id="{{ $line->id }}" title="Edit"><i class="fas fa-pen"></i></button>
                                <button class="btn btn-link btn-sm p-0 text-danger btn-delete-line" data-id="{{ $line->id }}" title="Remove"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No items yet.@if ($canEdit) Add the items to request; similar items (e.g. hard drives, RAM, RJ45) can go in one {{ $pr->kind->short() }}.@endif</td></tr>
                @endforelse
            </tbody>
            @if ($pr->items->isNotEmpty())
                <tfoot class="table-light">
                    <tr><td colspan="5" class="ps-4 fw-bold text-end">{{ $jr ? 'ABC' : 'TOTAL' }}</td><td class="text-end fw-bold">{{ number_format((float) $pr->total_amount, 2) }}</td><td colspan="3"></td></tr>
                </tfoot>
            @endif
        </table>
    </div>
    @if ($canEdit && ! $papMissing && $lines->isNotEmpty())
        <div class="px-4 py-2 small text-muted border-top">
            Left to request under {{ $pr->pap->code }}:
            @foreach ($lines as $project)
                <span class="badge bg-light text-dark border me-1">{{ \Illuminate\Support\Str::limit($project->description, 40) }}: ₱{{ number_format($project->remaining_cents / 100, 2) }}</span>
            @endforeach
        </div>
    @endif
</div>
</div>

{{-- Print: set the signatories, then print --}}
@if ($canPrintSign)
    <div class="modal fade" id="PRINT_MODAL" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold"><i class="fas fa-print text-primary me-2"></i>Print {{ $pr->title() }}</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="form_print" autocomplete="off">
                    @csrf
                    <div class="modal-body p-4">
                        <p class="small text-muted">Signatories printed on the form. They are saved with the {{ $pr->kind->short() }} and kept on its revisions.</p>
                        <div class="row g-3">@include('procurement.requests.extras.signatory_fields', ['prefix' => 'p_'])</div>
                    </div>
                    <div class="modal-footer bg-light py-2">
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
    function loadLineModal(id = null) {
        if (isModalOpen) return;
        isModalOpen = true;
        $.get('{{ route("procurement.requests.lines.entry", $pr) }}', id ? { id: id } : {}, function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById('REQUEST_LINE_MODAL');
            new bootstrap.Modal(el).show();
            el.addEventListener('hidden.bs.modal', function () { isModalOpen = false; $('#modal-body').html(''); });
        }).fail(function (xhr) { isModalOpen = false; toastr.error(errorText(xhr), 'Error'); });
    }

    $(document).on('click', '#btn_add_line', () => loadLineModal());
    $(document).on('click', '.btn-edit-line', function () { loadLineModal($(this).data('id')); });

    $(document).off('submit', '#form_request_line').on('submit', '#form_request_line', function (e) {
        e.preventDefault();
        const form = $(this);
        const summary = $('#line_error_summary');
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback').text('');
        summary.addClass('d-none');
        $('#btn_save_line').prop('disabled', true);

        $.post('{{ route("procurement.requests.lines.store", $pr) }}', form.serialize())
            .done(function (res) {
                toastr.success(res.message, 'Success');
                bootstrap.Modal.getInstance(document.getElementById('REQUEST_LINE_MODAL')).hide();
                refreshView();
            })
            .fail(function (xhr) {
                $('#btn_save_line').prop('disabled', false);
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
