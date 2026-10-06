@extends('BackEnd.layouts.master')

@section('content')
@php
    $statusColors = ['draft' => 'secondary', 'submitted' => 'primary', 'returned' => 'warning',
                     'approved' => 'success', 'superseded' => 'dark'];
    $returned = $ppmp->status === \App\Enums\PpmpStatus::Returned
        ? $ppmp->signatories->where('role', 'returned')->sortByDesc('signed_at')->first()
        : null;
    $budgetOver = $budgetRows->where('over', true)->isNotEmpty();
    $pesoC = fn ($c) => number_format($c / 100, 2);
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
                <div class="text-muted small">{{ $ppmp->office->label() }} · {{ $ppmp->region->label() }}</div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('procurement.ppmp.print', $ppmp) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-print me-1"></i> Print
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#PPMP_HISTORY_MODAL">
                    <i class="fas fa-history me-1"></i> History <span class="badge bg-secondary ms-1">{{ $ppmp->signatories->count() }}</span>
                </button>
                @if ($canEdit)
                    <button class="btn btn-sm btn-success" id="btn_add_pap"><i class="fas fa-folder-plus me-1"></i> Add PAP</button>
                    <button class="btn btn-sm btn-primary" id="btn_submit" @disabled($budgetOver) title="{{ $budgetOver ? 'Over budget allocation' : '' }}"><i class="fas fa-paper-plane me-1"></i> Submit for Approval</button>
                @endif
                @if ($canReturn)
                    <a href="{{ route('procurement.division-ppmp.index', ['fy' => $ppmp->fiscal_year]) }}" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i> Approve in Division PPMP</a>
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
                <div class="text-muted">Included in</div>
                <div class="fw-semibold">
                    @forelse ($ppmp->divisionPpmps as $divisionPpmp)
                        <a href="{{ route('procurement.division-ppmp.show', $divisionPpmp) }}" class="badge {{ $divisionPpmp->isCurrent() ? 'bg-success' : 'bg-light text-dark border' }} text-decoration-none">PPMP No. {{ $divisionPpmp->ppmp_number }}</a>
                    @empty
                        <span class="text-muted fw-normal">Not yet approved</span>
                    @endforelse
                </div>
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
                <div class="text-muted">Division / Approving Head</div>
                <div class="fw-semibold">{{ $approver?->fullname ?? 'Not set — set the head in Offices' }}</div>
                @if ($division && $division->id !== $ppmp->office_id)<div class="text-muted">{{ $division->shortName() }}</div>@endif
            </div>
            <div class="col-12 col-md-3 text-md-end">
                <div class="text-muted">Total Estimated Budget</div>
                <div class="fw-bold fs-5">₱ {{ number_format((float) $ppmp->total_budget, 2) }}</div>
            </div>
        </div>

        @if ($budgetOver && $canEdit)
            <div class="alert alert-danger small mt-3 mb-0">
                <i class="fas fa-exclamation-triangle me-1"></i>
                @foreach ($budgetRows->where('over', true)->where('wrong_fund', true) as $row)
                    <strong>No {{ $row['fund']->label() }} budget.</strong> {{ $row['department']->shortName() }} is budgeted under {{ $row['department']->budget_fund?->label() }};
                    change the source of funds of its {{ $row['fund']->label() }} projects.
                @endforeach
                @if ($budgetRows->where('over', true)->where('wrong_fund', false)->isNotEmpty())
                    <strong>Over budget.</strong> This PPMP goes over its department's budget, so it cannot be submitted.
                    <a href="#budget_card" class="alert-link">See the budget summary</a> and lower the estimated budgets.
                @endif
            </div>
        @endif
        @php $noScoping = $ppmp->paps->flatMap->items->reject->marketScopingComplete()->count(); @endphp
        @if ($canEdit && $noScoping)
            <div class="alert alert-info small mt-3 mb-0">
                <i class="fas fa-clipboard-check me-1"></i>
                {{ $noScoping }} {{ \Illuminate\Support\Str::plural('project', $noScoping) }} still {{ $noScoping === 1 ? 'needs' : 'need' }} the Market Scoping Checklist (RA 12009 Sec. 10). Open a project and fill in its checklist; attach the market survey there too.
            </div>
        @endif
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

{{-- Budget allocation: the department's budget, shared by its offices --}}
@php $department = $ppmp->office->department; @endphp
@if ($budgetRows->isNotEmpty() || $canEdit)
    <div class="card border-0 shadow-sm mb-3 {{ $budgetOver ? 'border border-danger' : '' }}" style="border-radius: 12px; overflow: hidden;" id="budget_card">
        <div class="card-header bg-white pt-3 pb-2 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2" style="border-bottom: 1px solid #f1f5f9;">
            <div>
                <h6 class="m-0 fw-bold"><i class="fas fa-coins text-muted me-2"></i>Budget Allocation — FY {{ $ppmp->fiscal_year }}</h6>
                @if ($department)<div class="small text-muted">{{ $department->shortName() }} — {{ $department->name }} budget, shared by its units</div>@endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($budgetLimits as $fundValue => $limit)
                    @php $left = $limit['available'] - $limit['mine']; @endphp
                    <span class="badge rounded-pill {{ $left < 0 ? 'bg-danger' : 'bg-success' }} px-3 py-2" style="font-size: .75rem;">
                        {{ \App\Enums\FundGroup::from($fundValue)->label() }}:
                        @if ($left < 0) over by ₱{{ $pesoC(-$left) }} @else ₱{{ $pesoC($left) }} left to plan @endif
                    </span>
                @endforeach
            </div>
        </div>
        @if (! $department)
            <div class="small text-muted px-4 py-3">{{ $ppmp->office->shortName() }} is not under a department, so it has no budget allocation. Fix it in Settings → Organization.</div>
        @elseif ($budgetRows->isEmpty())
            <div class="small text-muted px-4 py-3">No budget allocation set for {{ $department->shortName() }} for FY {{ $ppmp->fiscal_year }} yet. The PPMP is not checked against a budget until the Budget officer sets one.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle small mb-0">
                    <thead class="table-light"><tr class="text-nowrap"><th class="ps-4">Fund</th><th class="text-end">Department budget</th><th class="text-end">Used by other offices</th><th class="text-end">This PPMP</th><th class="text-end pe-4">Remaining</th></tr></thead>
                    <tbody>
                        @foreach ($budgetRows as $row)
                            <tr class="{{ $row['over'] ? 'table-danger' : '' }}">
                                <td class="ps-4 fw-semibold">{{ $row['fund']->label() }}</td>
                                @if ($row['allocation'])
                                    <td class="text-end">{{ $pesoC($row['amount']) }}</td>
                                    <td class="text-end">{{ $pesoC($row['others']) }}</td>
                                    <td class="text-end">{{ $pesoC($row['mine']) }}</td>
                                    <td class="text-end pe-4 fw-semibold {{ $row['over'] ? 'text-danger' : 'text-success' }}">{{ $pesoC($row['remaining']) }}</td>
                                @elseif ($row['wrong_fund'])
                                    <td class="text-end text-danger" colspan="2">No {{ $row['fund']->label() }} budget (department is {{ $department->budget_fund?->label() }})</td>
                                    <td class="text-end">{{ $pesoC($row['mine']) }}</td>
                                    <td class="text-danger pe-4 text-end fw-semibold">Not allowed</td>
                                @else
                                    <td class="text-end text-muted">Not set</td>
                                    <td></td>
                                    <td class="text-end">{{ $pesoC($row['mine']) }}</td>
                                    <td class="text-muted pe-4 text-end">Not checked</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="small text-muted px-4 py-2">The budget covers CO and MOOE together. Used by other offices = their latest submitted or approved PPMPs (drafts do not hold budget), first come, first served.</div>
        @endif
    </div>
@endif

{{-- Procurement projects, grouped by PAP --}}
@php $colspan = $canEdit ? 11 : 10; $n = 0; @endphp
<div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white pt-3 pb-2 px-4" style="border-bottom: 1px solid #f1f5f9;">
        <h6 class="m-0 fw-bold"><i class="fas fa-list text-muted me-2"></i>PAPs and Procurement Projects ({{ $ppmp->paps->count() }} PAP, {{ $ppmp->items->count() }} projects)</h6>
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
                        <th>Qty &amp; Specs</th>
                        <th>Mode</th>
                        <th class="text-center" title="Pre-Procurement Conference">Pre-Proc</th>
                        <th title="Start and end of procurement activity">Proc. Period</th>
                        <th>Delivery</th>
                        <th title="Source of Funds">Funds</th>
                        <th class="text-end pe-4">Estimated Budget</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ppmp->paps as $pap)
                        <tr class="table-secondary">
                            <td colspan="{{ $colspan - 1 }}" class="ps-4 fw-bold">
                                PAP CODE: {{ $pap->code }} - {{ $pap->title }}
                                @if ($canEdit)
                                    <button class="btn btn-sm btn-link p-0 ms-2 btn-add-item" data-pap="{{ $pap->id }}" title="Add a project under this PAP"><i class="fas fa-plus-circle"></i> Add Project</button>
                                    <button class="btn btn-sm btn-link p-0 ms-2 btn-edit-pap" data-id="{{ $pap->id }}" title="Edit PAP"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-link p-0 ms-1 text-danger btn-delete-pap" data-id="{{ $pap->id }}" title="Remove PAP"><i class="fas fa-trash-alt"></i></button>
                                @endif
                            </td>
                            <td class="text-end fw-bold text-nowrap pe-4">{{ number_format($pap->items->sum(fn ($i) => (float) $i->estimated_budget), 2) }}</td>
                        </tr>
                        @forelse ($pap->items as $item)
                            <tr>
                                <td class="ps-4 text-muted">{{ ++$n }}</td>
                                @if ($canEdit)
                                    <td class="text-nowrap">
                                        <button class="btn btn-sm btn-link p-0 me-2 btn-edit-item" data-id="{{ $item->id }}" title="Edit"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm btn-link p-0 text-danger btn-delete-item" data-id="{{ $item->id }}" title="Remove"><i class="fas fa-trash-alt"></i></button>
                                    </td>
                                @endif
                                <td>
                                    {{ $item->description }}
                                    <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                                        <a href="{{ route('procurement.ppmp.items.market-scoping', [$ppmp, $item]) }}" target="_blank"
                                           class="badge text-decoration-none {{ $item->marketScopingComplete() ? 'bg-success-subtle text-success border' : 'bg-warning-subtle text-dark border' }}"
                                           title="Print the Market Scoping Checklist"><i class="fas fa-clipboard-check me-1"></i>Market scoping {{ $item->marketScopingComplete() ? 'complete' : 'not complete' }}</a>
                                        @foreach ($item->attachments as $attachment)
                                            <a href="{{ route('procurement.ppmp.attachments.show', [$ppmp, $attachment]) }}" target="_blank" class="badge bg-light text-dark border text-decoration-none" title="{{ $attachment->kindLabel() }}">
                                                <i class="fas fa-paperclip me-1"></i>{{ \Illuminate\Support\Str::limit($attachment->original_name, 28) }}
                                            </a>
                                        @endforeach
                                        @if ($item->supporting_documents)<span class="text-muted small"><i class="fas fa-sticky-note me-1"></i>{{ $item->supporting_documents }}</span>@endif
                                    </div>
                                    @if ($item->remarks)
                                        <div class="text-muted"><i class="fas fa-comment-alt me-1"></i>{{ $item->remarks }}</div>
                                    @endif
                                </td>
                                <td class="text-nowrap" title="{{ $item->project_type->label() }}">{{ ucfirst(\Illuminate\Support\Str::before($item->project_type->label(), ' ')) }}</td>
                                <td style="min-width: 120px;">
                                    @if ($item->quantity !== null)
                                        <span class="text-nowrap">QTY: {{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} {{ $item->unit?->name }}</span>
                                        @if ($item->unit_cost !== null)<span class="text-nowrap text-muted">@ ₱{{ number_format((float) $item->unit_cost, 2) }}</span>@endif
                                    @endif
                                    @if ($item->quantity_size)<div class="text-muted" style="white-space: pre-line;">{{ $item->quantity_size }}</div>@endif
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
                                <td class="text-nowrap" title="{{ $item->fundSource->name }} · {{ $item->allotment_class->label() }}">
                                    {{ $item->fundSource->code }}
                                    <span class="badge {{ $item->allotment_class === \App\Enums\AllotmentClass::Co ? 'bg-warning-subtle text-warning-emphasis' : 'bg-light text-dark' }} border">{{ $item->allotment_class->short() }}</span>
                                </td>
                                <td class="text-end text-nowrap fw-semibold pe-4">
                                    {{ number_format((float) $item->estimated_budget, 2) }}
                                    @if ((float) $item->committed_amount > 0)
                                        <div class="text-muted fw-normal" title="Charged by PRs">PR: {{ number_format((float) $item->committed_amount, 2) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $colspan }}" class="ps-5 text-muted fst-italic">No projects under this PAP yet.</td></tr>
                        @endforelse
                    @empty
                        <tr>
                            <td colspan="{{ $colspan }}" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                                No PAPs yet.
                                @if ($canEdit) Click <strong>Add PAP</strong> (e.g. 26-05012-01 ICT Infrastructure Management), then add its projects. @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($ppmp->items->isNotEmpty())
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="{{ $colspan - 1 }}" class="text-end fw-bold ps-4">TOTAL BUDGET</td>
                            <td class="text-end fw-bold text-nowrap pe-4">{{ number_format((float) $ppmp->total_budget, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

{{-- Signatories / history, opened from the History button --}}
<div class="modal fade" id="PPMP_HISTORY_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-history text-muted me-2"></i>History — {{ $ppmp->ppmp_no }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
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
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
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
    function loadItemModal(itemId = null, papId = null) {
        if (isModalOpen) return;
        isModalOpen = true;

        $.ajax({
            url: '{{ route("procurement.ppmp.items.entry", $ppmp) }}',
            type: 'GET',
            data: itemId ? { id: itemId } : { pap_id: papId },
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

    $(document).on('click', '.btn-add-item', function (e) { e.preventDefault(); loadItemModal(null, $(this).data('pap')); });
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
            data: new FormData(this),   // includes attached files
            processData: false,
            contentType: false,
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
                        // "market_scoping.period_from" -> market_scoping[period_from]; "attachments.1" -> 2nd file
                        const parts = key.split('.');
                        let input = parts[0] === 'attachments' && parts.length === 2
                            ? form.find('input[name="attachments[]"]').eq(parseInt(parts[1], 10))
                            : form.find('[name="' + parts[0] + parts.slice(1).map((p) => '[' + p + ']').join('') + '"]');
                        if (!input.length) input = form.find('[name="' + key + '"]');
                        input.addClass('is-invalid');
                        input.siblings('.invalid-feedback').text(messages[0]);
                        if (!input.siblings('.invalid-feedback').length) toastr.error(messages[0]);
                    });
                    if (Object.keys(res.errors).some((k) => k.startsWith('market_scoping'))) {
                        bootstrap.Collapse.getOrCreateInstance(document.getElementById('ms_body'), { toggle: false }).show();
                    }
                } else if (xhr.status === 422 && res.message) {
                    errorSummary.removeClass('d-none').find('span').text(res.message);
                } else {
                    toastr.error(res.message ?? 'Something went wrong.', 'Error');
                }
            }
        });
    });

    // Remove an attachment (draft / returned PPMPs only)
    $(document).on('click', '.btn-delete-attachment', function (e) {
        e.preventDefault();
        const button = $(this);
        Swal.fire({ title: 'Remove this file?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Remove' }).then((result) => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: '{{ route("procurement.ppmp.attachments.destroy", $ppmp) }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', _method: 'DELETE', id: button.data('id') },
                success: (res) => { toastr.success(res.message); button.closest('li').remove(); },
                error: (xhr) => toastr.error(xhr.responseJSON?.message ?? 'Could not remove the file.')
            });
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

    // ---------- PAPs ----------
    function loadPapModal(papId = null) {
        if (isModalOpen) return;
        isModalOpen = true;

        $.ajax({
            url: '{{ route("procurement.ppmp.paps.entry", $ppmp) }}',
            type: 'GET',
            data: papId ? { id: papId } : {},
            success: function (html) {
                $('#modal-body').html(html);
                const modalElement = document.getElementById('PPMP_PAP_MODAL');
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

    $('#btn_add_pap').on('click', function (e) { e.preventDefault(); loadPapModal(); });
    $(document).on('click', '.btn-edit-pap', function (e) { e.preventDefault(); loadPapModal($(this).data('id')); });

    $(document).off('submit', '#form_ppmp_pap').on('submit', '#form_ppmp_pap', function (e) {
        e.preventDefault();
        const form = $(this);
        const errorSummary = $('#pap_error_summary');
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback').text('');
        errorSummary.addClass('d-none');
        $('#btn_save_pap').prop('disabled', true);

        $.ajax({
            url: '{{ route("procurement.ppmp.paps.store", $ppmp) }}',
            type: 'POST',
            data: form.serialize(),
            success: function (response) {
                toastr.success(response.message, 'Success');
                bootstrap.Modal.getInstance(document.getElementById('PPMP_PAP_MODAL')).hide();
                setTimeout(function () { window.location.reload(); }, 500);
            },
            error: function (xhr) {
                $('#btn_save_pap').prop('disabled', false);
                const res = xhr.responseJSON || {};
                errorSummary.removeClass('d-none');
                if (res.errors) {
                    $.each(res.errors, function (key, messages) {
                        const input = form.find('[name="' + key + '"]');
                        input.addClass('is-invalid');
                        input.siblings('.invalid-feedback').text(messages[0]);
                    });
                } else {
                    errorSummary.find('span').text(res.message ?? 'Something went wrong.');
                }
            }
        });
    });

    $(document).on('click', '.btn-delete-pap', function (e) {
        e.preventDefault();
        const papId = $(this).data('id');
        Swal.fire({
            title: 'Remove this PAP?', text: 'Only a PAP with no projects can be removed.', icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#ef4444', cancelButtonColor: '#64748b', confirmButtonText: 'Yes, remove', reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) sendAction('{{ route("procurement.ppmp.paps.destroy", $ppmp) }}', { id: papId }, 'DELETE');
        });
    });

    // ---------- Workflow ----------
    $('#btn_submit').on('click', function () {
        Swal.fire({
            title: 'Submit for approval?',
            html: 'The PPMP will be locked and sent to <b>{{ $approver?->fullname ?? "the approving head" }}</b> to be combined into the Division PPMP.',
            input: 'textarea', inputPlaceholder: 'Remarks (optional)',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: '#0d6efd', cancelButtonColor: '#64748b',
            confirmButtonText: 'Submit', reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) sendAction('{{ route("procurement.ppmp.submit", $ppmp) }}', { remarks: result.value });
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
