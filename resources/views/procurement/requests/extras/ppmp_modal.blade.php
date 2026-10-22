{{-- The PPMP a request is charged to, read-only: its details and projects by PAP (the request's PAP first and marked).
     Expects $ppmp (with office, paps.items.unit / procurementMode / fundSource), $pr, $canOpen (may open the PPMP page). --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $paps = $ppmp->paps->sortBy(fn ($pap) => $pap->id === $pr->ppmp_pap_id ? 0 : 1)->values();
    $details = [
        'Office'       => $ppmp->office?->shortName(),
        'Fiscal year'  => $ppmp->fiscal_year,
        'Type'         => $ppmp->type?->label(),
        'Status'       => $ppmp->status?->label(),
        'Total budget' => '₱' . $money($ppmp->total_budget),
        'Approved'     => $ppmp->approved_at?->format('M d, Y'),
        'Prepared by'  => trim($ppmp->prepared_by_name . ($ppmp->prepared_by_designation ? ', ' . $ppmp->prepared_by_designation : '')),
        'Submitted by' => trim($ppmp->submitted_by_name . ($ppmp->submitted_by_designation ? ', ' . $ppmp->submitted_by_designation : '')),
    ];
@endphp
<div class="modal fade" id="PPMP_DETAILS_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0">PPMP {{ $ppmp->ppmp_no }}</h5>
                    <div class="small text-muted">{{ $ppmp->office?->pathLabel() }} · FY {{ $ppmp->fiscal_year }}</div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 small">
                <div class="row g-3">
                    @foreach ($details as $label => $value)
                        <div class="col-6 col-md-3">
                            <div class="text-uppercase text-muted fw-semibold" style="font-size: .68rem; letter-spacing: .4px;">{{ $label }}</div>
                            <div class="fw-semibold">{{ filled($value) ? $value : '—' }}</div>
                        </div>
                    @endforeach
                </div>

                @foreach ($paps as $pap)
                    @php $mine = $pap->id === $pr->ppmp_pap_id; @endphp
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-4 mb-2">
                        <span class="fw-bold">{{ $pap->code }}</span> <span>{{ $pap->title }}</span>
                        @if ($mine)<span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">This {{ $pr->kind->short() }} is charged here</span>@endif
                    </div>
                    <div class="table-responsive border rounded-3 {{ $mine ? 'border-primary' : '' }}">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr class="text-nowrap">
                                    <th class="ps-3">Project</th><th class="text-center">Qty</th><th>Mode</th><th>Schedule</th><th>Fund</th>
                                    <th class="text-end">Estimated budget</th><th class="text-end pe-3">Requested</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pap->items as $item)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-semibold">{{ $item->description }}</div>
                                            @if ($item->quantity_size)<div class="text-muted text-truncate" style="max-width: 360px;" title="{{ $item->quantity_size }}">{{ $item->quantity_size }}</div>@endif
                                        </td>
                                        <td class="text-center text-nowrap">{{ $qty($item->quantity) }} {{ $item->unit?->name }}</td>
                                        <td>{{ $item->procurementMode?->name ?? '—' }}</td>
                                        <td class="text-nowrap">{{ $item->proc_start?->format('M Y') }}{{ $item->proc_end ? ' – ' . $item->proc_end->format('M Y') : '' }}</td>
                                        <td>{{ $item->fundSource?->code ?? '—' }}</td>
                                        <td class="text-end text-nowrap">{{ $money($item->estimated_budget) }}</td>
                                        <td class="text-end text-nowrap pe-3">{{ $money($item->committed_amount) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-3">No projects.</td></tr>
                                @endforelse
                            </tbody>
                            @if ($pap->items->isNotEmpty())
                                <tfoot>
                                    <tr class="table-light fw-bold">
                                        <td colspan="5" class="text-end">Total</td>
                                        <td class="text-end text-nowrap">{{ $money($pap->items->sum('estimated_budget')) }}</td>
                                        <td class="text-end text-nowrap pe-3">{{ $money($pap->items->sum('committed_amount')) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                @endforeach
                <div class="text-muted mt-2">"Requested" is what submitted PR / JRs have charged to each project so far.</div>
            </div>
            <div class="modal-footer bg-body-tertiary border-0 py-2">
                @if ($canOpen)
                    <a href="{{ route('procurement.ppmp.show', $ppmp) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i> Open PPMP page</a>
                @endif
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
