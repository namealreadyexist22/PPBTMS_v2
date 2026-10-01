@extends('BackEnd.layouts.master')

@section('content')
@php
    $statusColors = ['draft' => 'secondary', 'submitted' => 'primary', 'returned' => 'warning',
                     'approved' => 'success', 'superseded' => 'dark'];
    $returned = $ppmp->status === \App\Enums\PpmpStatus::Returned
        ? $ppmp->signatories->where('role', 'returned')->sortByDesc('signed_at')->first()
        : null;
@endphp

<div class="mb-3">
    <a href="{{ route('procurement.ppmp.index') }}" class="text-decoration-none small text-muted">
        <i class="fas fa-arrow-left me-1"></i> Back to PPMP List
    </a>
</div>

{{-- Header --}}
<div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="small text-muted text-uppercase fw-semibold" style="letter-spacing: .5px;">Project Procurement Management Plan</div>
                <h4 class="fw-bold mb-1">
                    {{ $ppmp->ppmp_no }}
                    <span class="badge bg-{{ $statusColors[$ppmp->status->value] ?? 'secondary' }} align-middle ms-1" style="font-size: .7rem;">{{ $ppmp->status->label() }}</span>
                </h4>
                <div class="text-muted small">{{ $ppmp->office->label() }}</div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('procurement.ppmp.print', $ppmp) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-print me-1"></i> Print
                </a>
                @if ($canEdit)
                    <button class="btn btn-sm btn-success" id="btn_add_item"><i class="fas fa-plus me-1"></i> Add Project</button>
                    <button class="btn btn-sm btn-primary" id="btn_submit"><i class="fas fa-paper-plane me-1"></i> Submit for Approval</button>
                @endif
                @if ($canApprove)
                    <button class="btn btn-sm btn-success" id="btn_approve"><i class="fas fa-check me-1"></i> Approve</button>
                    <button class="btn btn-sm btn-warning" id="btn_return"><i class="fas fa-undo me-1"></i> Return</button>
                @endif
                @if ($canAmend)
                    <button class="btn btn-sm btn-outline-primary" id="btn_amend"><i class="fas fa-code-branch me-1"></i> Amend</button>
                @endif
                @if ($canDelete)
                    <button class="btn btn-sm btn-outline-danger" id="btn_delete_ppmp"><i class="fas fa-trash-alt me-1"></i> Delete</button>
                @endif
            </div>
        </div>

        <hr class="my-3">

        <div class="row g-3 small">
            <div class="col-6 col-md-2">
                <div class="text-muted">Fiscal Year</div>
                <div class="fw-semibold">{{ $ppmp->fiscal_year }}</div>
            </div>
            <div class="col-6 col-md-2">
                <div class="text-muted">Type</div>
                <div class="fw-semibold">{{ $ppmp->type->label() }}</div>
            </div>
            <div class="col-6 col-md-2">
                <div class="text-muted">Version</div>
                <div class="fw-semibold">
                    @foreach ($versions as $version)
                        @if ($version->uuid === $ppmp->uuid)
                            <span class="badge bg-dark">{{ str_pad($version->version, 2, '0', STR_PAD_LEFT) }}</span>
                        @else
                            <a href="{{ route('procurement.ppmp.show', $version->uuid) }}" class="badge bg-light text-dark border text-decoration-none"
                               title="{{ $version->ppmp_no }} ({{ $version->status->label() }})">{{ str_pad($version->version, 2, '0', STR_PAD_LEFT) }}</a>
                        @endif
                    @endforeach
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted">Approving Head</div>
                <div class="fw-semibold">{{ $approver?->fullname ?? 'Not set — set the head in Offices' }}</div>
            </div>
            <div class="col-12 col-md-3 text-md-end">
                <div class="text-muted">Total Estimated Budget</div>
                <div class="fw-bold fs-5">₱ {{ number_format((float) $ppmp->total_budget, 2) }}</div>
            </div>
        </div>

        @if ($returned)
            <div class="alert alert-warning small mt-3 mb-0">
                <i class="fas fa-undo me-1"></i>
                <strong>Returned by {{ $returned->name_snapshot }}</strong> on {{ $returned->signed_at->format('M d, Y h:i A') }}:
                {{ $returned->remarks }}
            </div>
        @endif
        @if ($ppmp->status === \App\Enums\PpmpStatus::Superseded)
            <div class="alert alert-secondary small mt-3 mb-0">
                <i class="fas fa-info-circle me-1"></i> This version was replaced by a newer approved amendment.
            </div>
        @endif
    </div>
</div>

{{-- Procurement projects --}}
<div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white pt-3 pb-2 px-4" style="border-bottom: 1px solid #f1f5f9;">
        <h6 class="m-0 fw-bold"><i class="fas fa-list text-muted me-2"></i>Procurement Projects ({{ $ppmp->items->count() }})</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr class="text-nowrap">
                        <th class="ps-4">#</th>
                        @if ($canEdit)<th></th>@endif
                        <th style="min-width: 240px;">Procurement Project</th>
                        <th>Type</th>
                        <th>Qty &amp; Size</th>
                        <th>Mode</th>
                        <th class="text-center" title="Pre-Procurement Conference">Pre-Proc</th>
                        <th title="Start and end of procurement activity">Proc. Period</th>
                        <th>Delivery</th>
                        <th title="Source of Funds">Funds</th>
                        <th class="text-end pe-4">Estimated Budget</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ppmp->items as $i => $item)
                        <tr>
                            <td class="ps-4 text-muted">{{ $i + 1 }}</td>
                            @if ($canEdit)
                                <td class="text-nowrap">
                                    <button class="btn btn-sm btn-link p-0 me-2 btn-edit-item" data-id="{{ $item->id }}" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-link p-0 text-danger btn-delete-item" data-id="{{ $item->id }}" title="Remove"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            @endif
                            <td>
                                {{ $item->description }}
                                @if ($item->supporting_documents)
                                    <div class="text-muted mt-1"><i class="fas fa-paperclip me-1"></i>{{ $item->supporting_documents }}</div>
                                @endif
                                @if ($item->remarks)
                                    <div class="text-muted"><i class="fas fa-comment-alt me-1"></i>{{ $item->remarks }}</div>
                                @endif
                            </td>
                            <td class="text-nowrap" title="{{ $item->project_type->label() }}">{{ ucfirst(\Illuminate\Support\Str::before($item->project_type->label(), ' ')) }}</td>
                            <td class="text-nowrap">
                                @if ($item->quantity !== null)
                                    {{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} {{ $item->unit?->name }}
                                @endif
                                @if ($item->quantity_size)<div class="{{ $item->quantity !== null ? 'text-muted' : '' }}">{{ $item->quantity_size }}</div>@endif
                            </td>
                            <td class="text-nowrap" title="{{ $item->procurementMode->name }}">{{ $item->procurementMode->code }}</td>
                            <td class="text-center">{!! $item->pre_proc_conference ? '<i class="fas fa-check text-success"></i>' : '—' !!}</td>
                            <td class="text-nowrap">
                                @if ($item->proc_start->year === $item->proc_end->year)
                                    {{ $item->proc_start->format('M') }}{{ $item->proc_start->month !== $item->proc_end->month ? '–' . $item->proc_end->format('M') : '' }} {{ $item->proc_end->format('Y') }}
                                @else
                                    {{ $item->proc_start->format('M Y') }}–{{ $item->proc_end->format('M Y') }}
                                @endif
                            </td>
                            <td>{{ $item->delivery_period }}</td>
                            <td class="text-nowrap" title="{{ $item->fundSource->name }}">{{ $item->fundSource->code }}</td>
                            <td class="text-end text-nowrap fw-semibold pe-4">
                                {{ number_format((float) $item->estimated_budget, 2) }}
                                @if ((float) $item->committed_amount > 0)
                                    <div class="text-muted fw-normal" title="Charged by PRs">PR: {{ number_format((float) $item->committed_amount, 2) }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canEdit ? 11 : 10 }}" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                                No procurement projects yet.
                                @if ($canEdit) Click <strong>Add Project</strong> to start. @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($ppmp->items->isNotEmpty())
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="{{ $canEdit ? 10 : 9 }}" class="text-end fw-bold ps-4">TOTAL</td>
                            <td class="text-end fw-bold text-nowrap pe-4">{{ number_format((float) $ppmp->total_budget, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

{{-- Signatories / history --}}
<div class="card border-0 shadow-sm" style="border-radius: 12px;">
    <div class="card-header bg-white pt-3 pb-2 px-4" style="border-bottom: 1px solid #f1f5f9;">
        <h6 class="m-0 fw-bold"><i class="fas fa-history text-muted me-2"></i>History</h6>
    </div>
    <ul class="list-group list-group-flush small">
        @foreach ($ppmp->signatories->sortBy('signed_at') as $signatory)
            <li class="list-group-item px-4">
                <span class="badge bg-light text-dark border text-capitalize me-2">{{ $signatory->role }}</span>
                <strong>{{ $signatory->name_snapshot }}</strong>
                @if ($signatory->designation_snapshot)<span class="text-muted">, {{ $signatory->designation_snapshot }}</span>@endif
                <span class="text-muted ms-2">{{ $signatory->signed_at->format('M d, Y h:i A') }}</span>
                @if ($signatory->remarks)<div class="text-muted mt-1"><i class="fas fa-comment-alt me-1"></i>{{ $signatory->remarks }}</div>@endif
            </li>
        @endforeach
    </ul>
</div>

<div id="modal-body"></div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';
    const itemModalName = 'PPMP_ITEM_MODAL';
    let isModalOpen = false;

    // POST/DELETE helper: on success show the message, then reload (or go to `redirect`)
    function sendAction(url, data, method = 'POST') {
        return $.ajax({
            url: url,
            type: 'POST',
            data: Object.assign({ _token: csrf, _method: method }, data || {}),
            success: function (response) {
                toastr.success(response.message, 'Success');
                setTimeout(function () { window.location.href = response.url || window.location.href; }, 600);
            },
            error: function (xhr) {
                const res = xhr.responseJSON || {};
                const firstError = res.errors ? Object.values(res.errors)[0][0] : null;
                toastr.error(firstError || res.message || 'Something went wrong.', 'Error');
            }
        });
    }

    // ---------- Add / edit procurement project ----------
    function loadItemModal(itemId = null) {
        if (isModalOpen) return;
        isModalOpen = true;

        $.ajax({
            url: '{{ route("procurement.ppmp.items.entry", $ppmp) }}',
            type: 'GET',
            data: itemId ? { id: itemId } : {},
            success: function (html) {
                $('#modal-body').html(html);
                const modalElement = document.getElementById(itemModalName);
                new bootstrap.Modal(modalElement).show();
                modalElement.addEventListener('hidden.bs.modal', function () {
                    isModalOpen = false;
                    $('#modal-body').html('');
                });
            },
            error: function (xhr) {
                isModalOpen = false;
                toastr.error(xhr.responseJSON?.message ?? 'Could not open form.', 'Error');
            }
        });
    }

    $('#btn_add_item').on('click', function (e) { e.preventDefault(); loadItemModal(); });
    $(document).on('click', '.btn-edit-item', function (e) { e.preventDefault(); loadItemModal($(this).data('id')); });

    $(document).off('submit', '#form_ppmp_item').on('submit', '#form_ppmp_item', function (e) {
        e.preventDefault();

        const form = $(this);
        const saveBtn = $('#btn_save_item');
        const originalBtnHtml = saveBtn.html();
        const errorSummary = $('#item_error_summary');

        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback').text('');
        errorSummary.addClass('d-none');
        saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

        $.ajax({
            url: '{{ route("procurement.ppmp.items.store", $ppmp) }}',
            type: 'POST',
            data: form.serialize(),
            success: function (response) {
                toastr.success(response.message, 'Success');
                bootstrap.Modal.getInstance(document.getElementById(itemModalName)).hide();
                setTimeout(function () { window.location.reload(); }, 500);
            },
            error: function (xhr) {
                saveBtn.prop('disabled', false).html(originalBtnHtml);
                const res = xhr.responseJSON || {};

                if (xhr.status === 422 && res.errors) {
                    errorSummary.removeClass('d-none').find('span').text('Please correct the highlighted errors below.');
                    $.each(res.errors, function (key, messages) {
                        const input = form.find('[name="' + key + '"]');
                        input.addClass('is-invalid');
                        input.siblings('.invalid-feedback').text(messages[0]);
                    });
                } else if (xhr.status === 422 && res.message) {
                    errorSummary.removeClass('d-none').find('span').text(res.message);
                } else {
                    toastr.error(res.message ?? 'Something went wrong.', 'Error');
                }
            }
        });
    });

    $(document).on('click', '.btn-delete-item', function (e) {
        e.preventDefault();
        const itemId = $(this).data('id');

        Swal.fire({
            title: 'Remove this project?', icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#ef4444', cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, remove', reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) sendAction('{{ route("procurement.ppmp.items.destroy", $ppmp) }}', { id: itemId }, 'DELETE');
        });
    });

    // ---------- Workflow ----------
    $('#btn_submit').on('click', function () {
        Swal.fire({
            title: 'Submit for approval?',
            html: 'The PPMP will be locked and sent to <b>{{ $approver?->fullname ?? "the approving head" }}</b>.',
            input: 'textarea', inputPlaceholder: 'Remarks (optional)',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: '#0d6efd', cancelButtonColor: '#64748b',
            confirmButtonText: 'Submit', reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) sendAction('{{ route("procurement.ppmp.submit", $ppmp) }}', { remarks: result.value });
        });
    });

    $('#btn_approve').on('click', function () {
        Swal.fire({
            title: 'Approve this PPMP?', input: 'textarea', inputPlaceholder: 'Remarks (optional)',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: '#10b981', cancelButtonColor: '#64748b',
            confirmButtonText: 'Approve', reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) sendAction('{{ route("procurement.ppmp.approve", $ppmp) }}', { remarks: result.value });
        });
    });

    $('#btn_return').on('click', function () {
        Swal.fire({
            title: 'Return to office', input: 'textarea', inputPlaceholder: 'Reason for returning (required)',
            inputValidator: (value) => !value ? 'Please state the reason.' : undefined,
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#f59e0b', cancelButtonColor: '#64748b',
            confirmButtonText: 'Return', reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) sendAction('{{ route("procurement.ppmp.return", $ppmp) }}', { remarks: result.value });
        });
    });

    $('#btn_amend').on('click', function () {
        Swal.fire({
            title: 'Amend this PPMP?',
            text: 'A new draft version will be created from this approved PPMP. This version stays in effect until the amendment is approved.',
            input: 'textarea', inputPlaceholder: 'Reason for amendment (optional)',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: '#0d6efd', cancelButtonColor: '#64748b',
            confirmButtonText: 'Create amendment', reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) sendAction('{{ route("procurement.ppmp.amend", $ppmp) }}', { remarks: result.value });
        });
    });

    $('#btn_delete_ppmp').on('click', function () {
        Swal.fire({
            title: 'Delete this draft PPMP?', text: 'All its procurement projects will be removed.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#ef4444', cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete', reverseButtons: true
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: '{{ route("procurement.ppmp.destroy") }}', type: 'POST',
                data: { _token: csrf, _method: 'DELETE', id: '{{ $ppmp->uuid }}' },
                success: function (response) {
                    toastr.success(response.message, 'Deleted');
                    setTimeout(function () { window.location.href = '{{ route("procurement.ppmp.index") }}'; }, 600);
                },
                error: function (xhr) { toastr.error(xhr.responseJSON?.message ?? 'Could not delete.', 'Error'); }
            });
        });
    });
});
</script>
@endpush
